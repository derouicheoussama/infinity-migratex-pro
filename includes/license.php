<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity Migrate Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — signature et mentions à conserver.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Architecture licence Pro — prête à l'emploi, inactive par défaut.
 *
 * Pour activer l'édition Pro, définir dans wp-config.php :
 *
 *   define( 'INFINITY_MIGRATEX_PRO_CHECKOUT_URL', 'https://…/checkout/…' );
 *   define( 'INFINITY_MIGRATEX_PRO_LICENSE_API', 'https://…/api/licence' );
 *
 * CHECKOUT_URL : lien de paiement (Lemon Squeezy, Stripe, Gumroad…).
 * LICENSE_API  : endpoint qui reçoit { license_key, site_url } en POST et
 * renvoie { valid, plan, billing, expires, email }. Sans endpoint,
 * l'activation est acceptée localement (mode développement).
 *
 * En 1.0.0 toutes les fonctions implémentées appartiennent à l'édition
 * gratuite : aucune n'est verrouillée, aucun bouton factice. La matrice
 * ci-dessous prépare le futur découpage sans rien casser.
 */
final class IMP_License {

	/**
	 * Clé d'option.
	 */
	const OPTION = 'imp_license';

	/**
	 * État de la licence.
	 *
	 * @return array{key:string,status:string,plan:string,billing:string,expires:int,email:string,checked:int}
	 */
	public static function get() {
		$data = get_option( self::OPTION, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		return wp_parse_args(
			$data,
			array(
				'key'     => '',
				'status'  => 'inactive',
				'plan'    => 'free',
				'billing' => '',
				'expires' => 0,
				'email'   => '',
				'checked' => 0,
			)
		);
	}

	/**
	 * Mode Pro actif ?
	 *
	 * Vérifie le statut, l'expiration ET (en mode API signée) la liaison
	 * de domaine : une clé enregistrée pour un autre site ne donne pas
	 * accès aux fonctions Pro (anti-piratage).
	 *
	 * @return bool
	 */
	public static function is_pro() {
		$data = self::get();
		if ( 'active' !== $data['status'] ) {
			return false;
		}
		if ( $data['expires'] > 0 && $data['expires'] < time() ) {
			return false;
		}

		// Liaison de domaine : uniquement si l'API signée est configurée.
		if ( self::signed_api() ) {
			$bound = isset( $data['domain'] ) ? (string) $data['domain'] : '';
			if ( '' !== $bound ) {
				$bound_host = wp_parse_url( 'https://' . preg_replace( '#^https?://#', '', $bound ), PHP_URL_HOST );
				$this_host  = wp_parse_url( home_url(), PHP_URL_HOST );
				if ( ! is_string( $bound_host ) || ! is_string( $this_host )
					|| 0 !== strcasecmp( $bound_host, $this_host ) ) {
					return (bool) apply_filters( 'imp_is_pro', false );
				}
			}
		}

		/** Filtre le statut Pro (tests, licences spéciales…). */
		return (bool) apply_filters( 'imp_is_pro', true );
	}

	/**
	 * L'API de licence signée est-elle configurée (secret partagé présent) ?
	 *
	 * @return bool
	 */
	public static function signed_api() {
		return defined( 'INFINITY_MIGRATEX_PRO_LICENSE_SECRET' ) && '' !== INFINITY_MIGRATEX_PRO_LICENSE_SECRET;
	}

	/**
	 * URL d'achat Pro (checkout si configuré, sinon site de l'auteur).
	 *
	 * @return string
	 */
	public static function checkout_url() {
		if ( '' !== INFINITY_MIGRATEX_PRO_CHECKOUT_URL ) {
			return INFINITY_MIGRATEX_PRO_CHECKOUT_URL;
		}
		return 'https://www.derouicheoussama.com';
	}

	/**
	 * La vitesse Turbo est-elle disponible (Pro) ?
	 *
	 * @return bool
	 */
	public static function turbo_allowed() {
		return self::is_pro();
	}

	/**
	 * Libellé du mode de liaison (Security Center).
	 *
	 * @return string
	 */
	public static function binding_mode_label() {
		if ( self::signed_api() ) {
			return __( 'Signed license API active: keys are HMAC-verified and bound to the licensed domain — a key copied to another site loses Pro features.', 'infinity-migratex-pro' );
		}
		if ( self::configured() ) {
			return __( 'License API configured without shared secret — enable INFINITY_MIGRATEX_PRO_LICENSE_SECRET for domain binding and signed keys.', 'infinity-migratex-pro' );
		}
		return __( 'Local development mode: any key (8+ chars) activates Pro on this site only. Configure the license API + secret for production.', 'infinity-migratex-pro' );
	}

	/**
	 * Le système commercial est-il configuré ?
	 *
	 * @return bool
	 */
	public static function configured() {
		return '' !== INFINITY_MIGRATEX_PRO_CHECKOUT_URL || '' !== INFINITY_MIGRATEX_PRO_LICENSE_API;
	}

	/**
	 * Matrice des fonctionnalités (documentation honnête — aucun verrou
	 * factice : ce qui est implémenté fonctionne dans l'édition courante).
	 *
	 * @return array[] {feature, tier, available, note}
	 */
	public static function feature_matrix() {
		$pro_ready = self::is_pro();
		return array(
			array( 'feature' => __( 'Full / partial site migration', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Manual backups (files, database, full)', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Guided restore', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Database export / import', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Serialized-safe URL replacement', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Migration packages (.infinitymigrate)', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Security scanner', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Detailed activity logs', 'infinity-migratex-pro' ), 'tier' => 'free', 'available' => true ),
			array( 'feature' => __( 'Scheduled & automatic backups', 'infinity-migratex-pro' ), 'tier' => 'pro', 'available' => true ),
			array( 'feature' => __( 'Cloud backup destinations (Google Drive, Dropbox, FTP/FTPS)', 'infinity-migratex-pro' ), 'tier' => 'pro', 'available' => true ),
			array( 'feature' => __( 'Amazon S3 & OneDrive destinations', 'infinity-migratex-pro' ), 'tier' => 'pro', 'available' => false, 'note' => __( 'Coming in a future release.', 'infinity-migratex-pro' ) ),
			array( 'feature' => __( 'Advanced migration profiles', 'infinity-migratex-pro' ), 'tier' => 'pro', 'available' => false, 'note' => __( 'Coming in a future release.', 'infinity-migratex-pro' ) ),
			array( 'feature' => __( 'Priority support', 'infinity-migratex-pro' ), 'tier' => 'pro', 'available' => $pro_ready ),
		);
	}

	/**
	 * Active une clé de licence (appel AJAX).
	 * Anti brute-force : 5 tentatives / heure / utilisateur+IP.
	 *
	 * @param string $key Clé.
	 * @return array{ok:bool,message:string}
	 */
	public static function activate( $key ) {
		if ( ! IMP_Hardening::rate_ok( 'license', 5, HOUR_IN_SECONDS ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Too many activation attempts — try again in one hour (anti brute-force protection).', 'infinity-migratex-pro' ),
			);
		}

		$key = trim( sanitize_text_field( (string) $key ) );

		if ( '' === INFINITY_MIGRATEX_PRO_LICENSE_API ) {
			// Mode développement local : accepté sans serveur distant.
			if ( strlen( $key ) < 8 ) {
				return array(
					'ok'      => false,
					'message' => __( 'Enter a valid license key (at least 8 characters).', 'infinity-migratex-pro' ),
				);
			}
			self::save( $key, 'active', 'site1', 'lifetime', 0, '', '' );
			return array( 'ok' => true, 'message' => __( 'License activated (local development mode — no license server configured).', 'infinity-migratex-pro' ) );
		}

		$response = wp_remote_post(
			INFINITY_MIGRATEX_PRO_LICENSE_API,
			array(
				'timeout' => 15,
				'body'    => array(
					'license_key' => $key,
					'site_url'    => home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'License server unreachable. Try again later.', 'infinity-migratex-pro' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['valid'] ) ) {
			$message = isset( $body['message'] ) && is_string( $body['message'] )
				? $body['message']
				: __( 'Invalid license key for this site.', 'infinity-migratex-pro' );
			IMP_Hardening::log_event( 'license-refused', __( 'License activation refused by the license server.', 'infinity-migratex-pro' ) );
			return array( 'ok' => false, 'message' => $message );
		}

		$plan    = isset( $body['plan'] ) ? (string) $body['plan'] : 'site1';
		$expires = isset( $body['expires'] ) ? (int) $body['expires'] : 0;
		$domain  = isset( $body['domain'] ) && is_string( $body['domain'] ) ? (string) $body['domain'] : wp_parse_url( home_url(), PHP_URL_HOST );

		// Vérification de la signature HMAC du serveur (anti-contrefaçon
		// de réponse : seule une réponse signée avec le secret partagé
		// est acceptée quand le secret est configuré).
		if ( self::signed_api() ) {
			$expected = isset( $body['sig'] ) ? (string) $body['sig'] : '';
			$computed = hash_hmac(
				'sha256',
				implode( '|', array( $key, (string) $domain, $plan, (string) $expires ) ),
				INFINITY_MIGRATEX_PRO_LICENSE_SECRET
			);
			if ( '' === $expected || ! hash_equals( $computed, $expected ) ) {
				IMP_Hardening::log_event( 'license-bad-signature', __( 'License server response signature mismatch — activation refused.', 'infinity-migratex-pro' ) );
				return array(
					'ok'      => false,
					'message' => __( 'License response signature invalid — activation refused (possible tampering).', 'infinity-migratex-pro' ),
				);
			}
		}

		self::save(
			$key,
			'active',
			$plan,
			isset( $body['billing'] ) ? (string) $body['billing'] : 'yearly',
			$expires,
			isset( $body['email'] ) ? sanitize_email( (string) $body['email'] ) : '',
			(string) $domain
		);

		return array( 'ok' => true, 'message' => __( 'License activated. Thank you for supporting Infinity Migrate Pro!', 'infinity-migratex-pro' ) );
	}

	/**
	 * Désactive la licence courante.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$data        = self::get();
		$data['status'] = 'inactive';
		update_option( self::OPTION, $data, false );
	}

	/**
	 * Enregistre l'état de licence.
	 *
	 * @param string $key     Clé (jamais affichée en clair ailleurs).
	 * @param string $status  Statut.
	 * @param string $plan    Plan.
	 * @param string $billing Facturation.
	 * @param int    $expires Expiration (timestamp, 0 = à vie).
	 * @param string $email   E-mail.
	 * @param string $domain  Domaine lié (anti-piratage).
	 * @return void
	 */
	private static function save( $key, $status, $plan, $billing, $expires, $email, $domain = '' ) {
		update_option(
			self::OPTION,
			array(
				'key'     => $key,
				'status'  => $status,
				'plan'    => $plan,
				'billing' => $billing,
				'expires' => $expires,
				'email'   => $email,
				'domain'  => $domain,
				'checked' => time(),
			),
			false
		);
	}
}
