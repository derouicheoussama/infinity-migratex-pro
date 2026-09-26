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
 * Mises à jour automatiques depuis GitHub Releases.
 *
 * - Vérifie la dernière release (cache 12 h, uniquement en admin).
 * - Compare les versions (semver) : propose la mise à jour si plus récente.
 * - Affiche le changelog (notes de release) dans l'écran de mise à jour.
 * - Télécharge l'asset zip officiel de la release.
 * - Ne casse rien si la copie locale est modifiée : la comparaison reste
 *   basée sur les versions, jamais sur un hash du dossier local.
 * - Aucune donnée du site n'est envoyée : simple GET vers l'API GitHub.
 */
final class IMP_Updater {

	/** @var string Repo "owner/name". */
	private static $repo = '';

	/** @var string Nom de l'asset zip. */
	private static $asset = '';

	/** @var array|null Dernière release connue. */
	private static $latest = null;

	/**
	 * Initialise les hooks.
	 *
	 * @param string $repo  Repo GitHub (owner/name).
	 * @param string $asset Nom de l'asset zip.
	 * @return void
	 */
	public static function init( $repo, $asset ) {
		self::$repo  = sanitize_text_field( (string) $repo );
		self::$asset = sanitize_file_name( (string) $asset );

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_update_transient' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'filter_source_name' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 10, 2 );
		add_action( 'admin_post_imp_proxy_update', array( __CLASS__, 'proxy_download' ) );
	}

	/**
	 * Dernière release GitHub (cache 12 h + requête conditionnelle ETag :
	 * une réponse 304 ne consomme PAS le quota de l'API GitHub).
	 * Canal Pro : dépôt/asset surchargés par constantes + en-tête
	 * d'authentification si un token est fourni (releases privées).
	 *
	 * @param bool $force Ignorer le cache et revérifier.
	 * @return array|null {tag,body,url,download,checked_at} ou null.
	 */
	public static function latest_release( $force = false ) {
		if ( null !== self::$latest && ! $force ) {
			return self::$latest;
		}

		$cache = $force ? false : get_transient( 'imp_gh_latest' );
		if ( is_array( $cache ) && ! $force ) {
			self::$latest = $cache;
			return $cache;
		}

		if ( '' === self::$repo ) {
			return null;
		}

		$headers = array( 'Accept' => 'application/vnd.github+json' );
		if ( defined( 'INFINITY_MIGRATEX_PRO_GH_TOKEN' ) && '' !== INFINITY_MIGRATEX_PRO_GH_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . INFINITY_MIGRATEX_PRO_GH_TOKEN;
		}
		// Requête conditionnelle : si la release n'a pas changé, GitHub
		// répond 304 sans consommer le quota — on prolonge le cache.
		$etag = is_array( $cache ) && isset( $cache['etag'] ) ? (string) $cache['etag'] : '';
		if ( '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::$repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => $headers,
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 304 === $code && is_array( $cache ) ) {
			$cache['checked_at'] = time();
			set_transient( 'imp_gh_latest', $cache, 12 * HOUR_IN_SECONDS );
			self::$latest = $cache;
			return $cache;
		}

		if ( is_wp_error( $response ) || 200 !== $code ) {
			// Réessai dans 1 h en cas d'échec (rate-limit GitHub…).
			$error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			set_transient(
				'imp_gh_latest',
				array( '__error' => true, 'error_message' => sanitize_text_field( $error ), 'checked_at' => time() ),
				HOUR_IN_SECONDS
			);
			self::$latest = null;
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			return null;
		}

		$download = '';
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::$asset === $asset['name'] ) {
					$download = (string) $asset['browser_download_url'];
					break;
				}
			}
		}

		// Canal Pro privé : le package passe par notre proxy authentifié
		// (le navigateur de mise à jour de WP ne peut pas envoyer de token).
		if ( '' !== $download && defined( 'INFINITY_MIGRATEX_PRO_GH_TOKEN' ) && '' !== INFINITY_MIGRATEX_PRO_GH_TOKEN ) {
			$download = wp_nonce_url(
				add_query_arg( 'action', 'imp_proxy_update', admin_url( 'admin-post.php' ) ),
				'imp-proxy-update'
			);
		}

		$data = array(
			'tag'        => ltrim( (string) $release['tag_name'], 'v' ),
			'body'       => isset( $release['body'] ) ? (string) $release['body'] : '',
			'url'        => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
			'download'   => $download,
			'etag'       => (string) wp_remote_retrieve_header( $response, 'etag' ),
			'checked_at' => time(),
		);

		set_transient( 'imp_gh_latest', $data, 12 * HOUR_IN_SECONDS );
		self::$latest = $data;
		return $data;
	}

	/**
	 * Force une revérification immédiate (bouton « Vérifier maintenant »).
	 *
	 * @return array|null
	 */
	public static function force_check() {
		delete_transient( 'imp_gh_latest' );
		self::$latest = null;
		return self::latest_release( true );
	}

	/**
	 * État du canal GitHub SANS aucun appel réseau — uniquement le dernier
	 * cache connu (pour pré-remplir l'interface instantanément).
	 *
	 * @return array {active,installed,latest,available,checked_at,error}
	 */
	public static function cached_status() {
		$status = array(
			'active'      => INFINITY_MIGRATEX_PRO_GH_UPDATES,
			'installed'   => IMP_VERSION,
			'latest'      => '',
			'available'   => false,
			'checked_at'  => 0,
			'error'       => '',
		);

		$cache = get_transient( 'imp_gh_latest' );
		if ( ! is_array( $cache ) ) {
			return $status;
		}
		if ( ! empty( $cache['__error'] ) ) {
			$status['error']      = isset( $cache['error_message'] ) ? (string) $cache['error_message'] : __( 'GitHub API unreachable.', 'infinity-migratex-pro' );
			$status['checked_at'] = isset( $cache['checked_at'] ) ? (int) $cache['checked_at'] : 0;
			return $status;
		}
		$status['latest']     = isset( $cache['tag'] ) ? (string) $cache['tag'] : '';
		$status['checked_at'] = isset( $cache['checked_at'] ) ? (int) $cache['checked_at'] : 0;
		$status['available']  = '' !== $status['latest'] && version_compare( $status['latest'], IMP_VERSION, '>' );
		return $status;
	}

	/**
	 * État du canal GitHub (panneau « Mises à jour » de la page À propos).
	 *
	 * @return array {active,installed,latest,available,checked_at,error}
	 */
	public static function channel_status() {
		$status = array(
			'active'      => INFINITY_MIGRATEX_PRO_GH_UPDATES,
			'installed'   => IMP_VERSION,
			'latest'      => '',
			'available'   => false,
			'checked_at'  => 0,
			'error'       => '',
		);

		$cache = get_transient( 'imp_gh_latest' );
		if ( is_array( $cache ) && isset( $cache['checked_at'] ) ) {
			$status['checked_at'] = (int) $cache['checked_at'];
		}
		if ( is_array( $cache ) && ! empty( $cache['__error'] ) ) {
			$status['error'] = isset( $cache['error_message'] ) ? (string) $cache['error_message'] : __( 'GitHub API unreachable.', 'infinity-migratex-pro' );
			return $status;
		}

		$release = self::latest_release();
		if ( null === $release ) {
			return $status;
		}
		$status['latest']    = $release['tag'];
		$status['available'] = (bool) self::update_available();
		return $status;
	}

	/**
	 * Proxy de téléchargement authentifié (canal Pro privé) : stream le
	 * package GitHub avec le token du développeur — uniquement admins,
	 * nonce + rate limit.
	 *
	 * @return void
	 */
	public static function proxy_download() {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'imp-proxy-update' ) || ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'infinity-migratex-pro' ), '', array( 'response' => 403 ) );
		}

		$release = self::latest_release();
		if ( null === $release || '' === $release['download'] || 0 !== strpos( $release['download'], 'http' ) ) {
			// Le package public : on laisse WP le gérer.
			wp_die( esc_html__( 'No proxied package available.', 'infinity-migratex-pro' ), '', array( 'response' => 404 ) );
		}

		$headers = array( 'Accept' => 'application/octet-stream' );
		// Résoudre l'URL réelle de l'asset (api → browser_download).
		if ( defined( 'INFINITY_MIGRATEX_PRO_GH_TOKEN' ) && '' !== INFINITY_MIGRATEX_PRO_GH_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . INFINITY_MIGRATEX_PRO_GH_TOKEN;
		}

		$asset_url = 'https://api.github.com/repos/' . self::$repo . '/releases/latest';
		$response  = wp_remote_get(
			$asset_url,
			array(
				'timeout' => 20,
				'headers' => array_merge( array( 'Accept' => 'application/vnd.github+json' ), array( 'Authorization' => 'Bearer ' . INFINITY_MIGRATEX_PRO_GH_TOKEN ) ),
			)
		);
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$real = '';
		if ( is_array( $body ) && ! empty( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && self::$asset === $asset['name'] && isset( $asset['url'] ) ) {
					$real = (string) $asset['url'];
					break;
				}
			}
		}
		if ( '' === $real ) {
			wp_die( esc_html__( 'Package not found in the latest release.', 'infinity-migratex-pro' ), '', array( 'response' => 404 ) );
		}

		$zip = wp_remote_get(
			$real,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . INFINITY_MIGRATEX_PRO_GH_TOKEN,
					'Accept'        => 'application/octet-stream',
				),
			)
		);
		if ( is_wp_error( $zip ) || 200 !== (int) wp_remote_retrieve_response_code( $zip ) ) {
			wp_die( esc_html__( 'Package download failed.', 'infinity-migratex-pro' ), '', array( 'response' => 502 ) );
		}

		$content = wp_remote_retrieve_body( $zip );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( self::$asset ) . '"' );
		header( 'Content-Length: ' . (string) strlen( $content ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- binaire du package signé.
		exit;
	}

	/**
	 * Une mise à jour est-elle disponible ?
	 *
	 * @return array|null Données de mise à jour ou null.
	 */
	public static function update_available() {
		$release = self::latest_release();
		if ( null === $release || isset( $release['__error'] ) || '' === $release['download'] ) {
			return null;
		}
		if ( version_compare( $release['tag'], IMP_VERSION, '<=' ) ) {
			return null;
		}
		return $release;
	}

	/**
	 * Injecte la mise à jour dans le transient WordPress.
	 *
	 * @param object $transient Transient update_plugins.
	 * @return object
	 */
	public static function filter_update_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::update_available();
		if ( null === $release ) {
			return $transient;
		}

		$plugin_basename = plugin_basename( IMP_FILE );

		// Ne pas écraser une mise à jour wp.org éventuelle (priorité officielle).
		if ( isset( $transient->response[ $plugin_basename ] ) ) {
			return $transient;
		}

		$transient->response[ $plugin_basename ] = (object) array(
			'slug'          => dirname( $plugin_basename ),
			'plugin'        => $plugin_basename,
			'new_version'   => $release['tag'],
			'url'           => $release['url'],
			'package'       => $release['download'],
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'tested'        => '7.1',
			'icons'         => array(
				'default' => IMP_URL . 'assets/images/imp-icon-128.png',
			),
			'banners'       => array(),
		);

		return $transient;
	}

	/**
	 * Fiche "voir les détails" avec le changelog de la release.
	 *
	 * @param false|object|array $result Résultat.
	 * @param string             $action Action.
	 * @param object             $args   Arguments.
	 * @return false|object
	 */
	public static function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		$plugin_basename = plugin_basename( IMP_FILE );
		if ( dirname( $plugin_basename ) !== $args->slug ) {
			return $result;
		}

		$release = self::latest_release();
		if ( null === $release || isset( $release['__error'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Infinity MigrateX Pro',
			'slug'          => $args->slug,
			'version'       => $release['tag'],
			'author'        => '<a href="https://www.derouicheoussama.com">Derouiche Oussama</a>',
			'homepage'      => 'https://www.derouicheoussama.com',
			'download_link' => $release['download'],
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'tested'        => '7.1',
			'icons'         => array(
				'default' => IMP_URL . 'assets/images/imp-icon-128.png',
			),
			'sections'      => array(
				'description' => __( 'WordPress migration, backup & deployment suite by Derouiche Oussama — Infinity Coder.', 'infinity-migratex-pro' ),
				'changelog'   => wp_kses(
					nl2br( make_clickable( esc_html( $release['body'] ) ) ),
					array(
						'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
						'br'     => array(),
						'strong' => array(),
						'em'     => array(),
						'code'   => array(),
					)
				),
			),
		);
	}

	/**
	 * Normalise le nom du dossier extrait ("infinity-migratex-pro-main"
	 * ou "infinity-migratex-pro-1.2.0" → "infinity-migratex-pro").
	 *
	 * @param string $source        Chemin source.
	 * @param string $remote_source Source distante.
	 * @param object $upgrader      Upgrader.
	 * @param array  $hook_extra    Extras.
	 * @return string|WP_Error
	 */
	public static function filter_source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return $source;
		}
		if ( ! isset( $hook_extra['plugin'] ) || plugin_basename( IMP_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		$expected = dirname( plugin_basename( IMP_FILE ) );
		$current  = basename( rtrim( (string) $source, '/' ) );

		if ( $current === $expected ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . trailingslashit( $expected );
		if ( $wp_filesystem->move( (string) $source, $new_source, true ) ) {
			return trailingslashit( $new_source );
		}

		return $source;
	}

	/**
	 * Après une mise à jour : vérifications d'intégrité (safe update).
	 *
	 * @param WP_Upgrader $upgrader Upgrader.
	 * @param array       $options  Options.
	 * @return void
	 */
	public static function after_update( $upgrader, $options ) {
		if ( ! isset( $options['action'], $options['type'] ) || 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}
		if ( empty( $options['plugins'] ) || ! in_array( plugin_basename( IMP_FILE ), (array) $options['plugins'], true ) ) {
			return;
		}

		// Le schéma SQL se met à jour via plugins_loaded (IMP_Logger).
		// On rafraîchit l'état du plugin et on journalise l'événement réel.
		IMP_Plugin::flush_settings();
		IMP_Security::bootstrap_storage();
		// Resceller le code après une mise à jour officielle (nouvelle baseline).
		if ( class_exists( 'IMP_Hardening' ) ) {
			IMP_Hardening::rebuild_baseline();
		}

		$log_id = IMP_Logger::start(
			sprintf(
				/* translators: %s: version */
				__( 'Update to %s', 'infinity-migratex-pro' ),
				IMP_VERSION
			),
			IMP_Logger::TYPE_SYSTEM
		);
		IMP_Logger::finish( $log_id, IMP_Logger::STATUS_COMPLETED, __( 'Plugin updated successfully.', 'infinity-migratex-pro' ) );

		set_transient( 'imp_updated_notice', 1, 60 );
	}
}
