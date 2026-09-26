<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity MigrateX Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — signature et mentions à conserver.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Sheets (Pro) — journal automatique des opérations.
 *
 * Chaque backup / restauration / migration / import terminé est ajouté
 * comme LIGNE dans une Google Sheet (suivi centralisé, jamais de
 * doublon : une ligne par opération). API officielle Google via un
 * COMPTE DE SERVICE (JWT RS256) — aucun Apps Script, aucun OAuth
 * interactif. Le JSON du compte de service est chiffré au repos.
 */
final class IMP_Sheets {

	/**
	 * Intégration prête (Pro + activée + JSON + ID de spreadsheet) ?
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) {
			return false;
		}
		$settings = imp_settings();
		return ! empty( $settings['sheets_enabled'] )
			&& ! empty( $settings['sheets_id'] )
			&& ! empty( $settings['sheets_json'] );
	}

	/**
	 * Journalise une opération terminée (jamais bloquant, jamais fatal).
	 *
	 * @param array $job Job terminé.
	 * @return void
	 */
	public static function log_job( array $job ) {
		try {
			if ( ! self::enabled() ) {
				return;
			}
			/* Défensive : valeurs tronquées/typées — une ligne malformée ne
			 * doit jamais refuser les suivantes ni corrompre la Sheet. */
			$duration = isset( $job['finished_at'], $job['started_at'] )
				? max( 1, (int) round( (float) $job['finished_at'] - (float) $job['started_at'] ) )
				: 0;
			$rows     = 0;
			if ( isset( $job['state']['meta']['dump']['db_rows'] ) ) {
				$rows = (int) $job['state']['meta']['dump']['db_rows'];
			} elseif ( isset( $job['state']['meta']['db_rows'] ) ) {
				$rows = (int) $job['state']['meta']['db_rows'];
			}
			$bytes = isset( $job['result']['sizes']['total'] ) ? (int) $job['result']['sizes']['total'] : 0;

			$row = array(
				gmdate( 'Y-m-d H:i:s', time() + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) ),
				substr( sanitize_text_field( (string) ( $job['title'] ?? '' ) ), 0, 120 ),
				substr( sanitize_key( (string) ( $job['type'] ?? '' ) ), 0, 20 ),
				substr( sanitize_text_field( (string) ( $job['data']['name'] ?? '' ) ), 0, 120 ),
				(string) home_url(),
				(string) $rows,
				$bytes > 0 ? imp_format_bytes( $bytes ) : '',
				$duration ? imp_human_duration( $duration ) : '',
				(string) IMP_VERSION,
				'OK',
			);

			self::append_row( $row );
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Le suivi Sheets ne doit JAMAIS casser une opération réussie.
		}
	}

	/**
	 * Ajoute une ligne (utilisé aussi par le bouton de test).
	 *
	 * @param string[] $values Valeurs de la ligne.
	 * @return array{ok:bool,message:string}
	 */
	public static function append_row( array $values ) {
		$settings = imp_settings();
		$id       = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $settings['sheets_id'] ?? '' ) );
		$sheet    = sanitize_key( (string) ( $settings['sheets_name'] ?? 'Backups' ) );

		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'ok' => false, 'message' => $token->get_error_message() );
		}

		$url     = 'https://sheets.googleapis.com/v4/spreadsheets/' . $id . '/values/' . rawurlencode( "'" . $sheet . "'!A:J" ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
		$post    = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'values' => array( $values ) ) ),
			)
		);
		if ( is_wp_error( $post ) ) {
			return array( 'ok' => false, 'message' => $post->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $post );
		if ( $code < 200 || $code >= 300 ) {
			return array( 'ok' => false, 'message' => 'Google Sheets: HTTP ' . $code . ' — ' . substr( (string) wp_remote_retrieve_body( $post ), 0, 140 ) );
		}
		return array( 'ok' => true, 'message' => __( 'Row added to the Google Sheet.', 'infinity-migratex-pro' ) );
	}

	/**
	 * Access token OAuth2 via JWT du compte de service (cache 55 min).
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$cached = get_transient( 'imp_sheets_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$creds = self::credentials();
		if ( is_wp_error( $creds ) ) {
			return $creds;
		}

		$header    = self::b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$now       = time();
		$claims    = self::b64( wp_json_encode( array(
			'iss'   => $creds['email'],
			'scope' => 'https://www.googleapis.com/auth/spreadsheets',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		) ) );
		$assertion_input = $header . '.' . $claims;
		$key = openssl_pkey_get_private( $creds['key'] );
		if ( ! $key ) {
			return new WP_Error( 'IMP-260', __( 'The service account private key is invalid (JSON pasted correctly?).', 'infinity-migratex-pro' ) );
		}
		openssl_sign( $assertion_input, $signature, $key, 'sha256WithRSAEncryption' );
		$jwt = $assertion_input . '.' . self::b64( $signature );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 10,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'IMP-261', __( 'Google rejected the service account credentials.', 'infinity-migratex-pro' ) );
		}
		set_transient( 'imp_sheets_token', (string) $body['access_token'], 55 * MINUTE_IN_SECONDS );
		return (string) $body['access_token'];
	}

	/**
	 * Décode le JSON du compte de service (email + clé privée).
	 *
	 * @return array{email:string,key:string}|WP_Error
	 */
	private static function credentials() {
		$raw = IMP_Security::decrypt( (string) imp_setting( 'sheets_json', '' ) );
		$doc = json_decode( (string) $raw, true );
		if ( ! is_array( $doc ) || empty( $doc['client_email'] ) || empty( $doc['private_key'] ) ) {
			return new WP_Error( 'IMP-260', __( 'The service account JSON is incomplete (client_email / private_key).', 'infinity-migratex-pro' ) );
		}
		return array(
			'email' => (string) $doc['client_email'],
			'key'   => (string) $doc['private_key'],
		);
	}

	/**
	 * Base64 URL-safe.
	 *
	 * @param string $data Données.
	 * @return string
	 */
	private static function b64( $data ) {
		return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
