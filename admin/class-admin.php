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
 * Administration : menu, layout partagé (sidebar + topbar), assets
 * chargés uniquement sur les pages du plugin, distribution AJAX.
 */
final class IMP_Admin {

	/** @var string SVG du logo officiel WooCommerce (menu). */
	private static $woo_icon_svg = '';

	/**
	 * Pages du plugin : slug => {title, capability, class, method}.
	 *
	 * @return array[]
	 */
	public static function pages() {
		return array(
			'dashboard'    => array( __( 'Dashboard', 'infinity-migratex-pro' ), 'IMP_Admin_Dashboard', 'render' ),
			'migration'    => array( __( 'Migration', 'infinity-migratex-pro' ), 'IMP_Admin_Migration', 'render' ),
			'backups'      => array( __( 'Backups', 'infinity-migratex-pro' ), 'IMP_Admin_Backup', 'render' ),
			'restore'      => array( __( 'Restore', 'infinity-migratex-pro' ), 'IMP_Admin_Restore', 'render' ),
			'import'       => array( __( 'Import', 'infinity-migratex-pro' ), 'IMP_Admin_Import', 'render' ),
			'packages'     => array( __( 'Packages', 'infinity-migratex-pro' ), 'IMP_Admin_Packages', 'render' ),
			'scanner'      => array( __( 'Scanner', 'infinity-migratex-pro' ), 'IMP_Admin_Scanner', 'render' ),
			'database'     => array( __( 'Database', 'infinity-migratex-pro' ), 'IMP_Admin_Database', 'render' ),
			'urlreplace'   => array( __( 'URL Replace', 'infinity-migratex-pro' ), 'IMP_Admin_URLReplace', 'render' ),
			'logs'         => array( __( 'Logs', 'infinity-migratex-pro' ), 'IMP_Admin_Logs', 'render' ),
			'security'     => array( __( 'Security', 'infinity-migratex-pro' ), 'IMP_Admin_Security', 'render' ),
			'tools'        => array( __( 'Tools', 'infinity-migratex-pro' ), 'IMP_Admin_Tools', 'render' ),
			'woocommerce'  => array( __( 'WooCommerce', 'infinity-migratex-pro' ), 'IMP_Admin_WooCommerce', 'render' ),
			'integrations' => array( __( 'Integrations', 'infinity-migratex-pro' ), 'IMP_Admin_Integrations', 'render' ),
			'settings'     => array( __( 'Settings', 'infinity-migratex-pro' ), 'IMP_Admin_Settings', 'render' ),
			'about'        => array( __( 'About', 'infinity-migratex-pro' ), 'IMP_Admin_About', 'render' ),
		);
	}

	/**
	 * Boot admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon_css' ) );
		self::register_ajax();
		self::register_post_endpoints();
	}

	/**
	 * AJAX : applique RÉELLEMENT la mise à jour disponible (canal wp.org
	 * prioritaire, sinon GitHub — l'offre vient du transient injecté par
	 * nos deux systèmes). Upgrader core complet : vérifications de
	 * compatibilité, filesystem, hook upgrader_process_complete (re-scellement).
	 *
	 * @return void
	 */
	public static function ajax_update_run() {
		IMP_Security::ajax_guard( 'manage' );
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to update plugins.', 'infinity-migratex-pro' ) ), 403 );
		}

		$basename = plugin_basename( IMP_FILE );
		$updates  = get_site_transient( 'update_plugins' );
		$offer    = null;
		if ( is_object( $updates ) && ! empty( $updates->response[ $basename ] ) && ! empty( $updates->response[ $basename ]->package ) ) {
			$offer = $updates->response[ $basename ];
		}
		if ( null === $offer ) {
			// L'offre du dernier check (12 h) : le transient core a pu être
			// purgé entre le check et le clic (autre admin, cron, TTL…) —
			// on la réinjecte pour que l'upgrader la consomme.
			$saved = get_transient( 'imp_update_offer' );
			if ( is_array( $saved ) && ! empty( $saved['package'] ) ) {
				$updates = is_object( $updates ) ? $updates : new stdClass();
				$updates->response = ( isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
				$updates->response[ $basename ] = (object) array(
					'slug'         => dirname( $basename ),
					'plugin'       => $basename,
					'new_version'  => (string) $saved['tag'],
					'package'      => (string) $saved['package'],
					'url'          => isset( $saved['url'] ) ? (string) $saved['url'] : '',
					'requires'     => '5.8',
					'requires_php' => '7.4',
				);
				set_site_transient( 'update_plugins', $updates );
				$offer = $updates->response[ $basename ];
			}
		}
		if ( null === $offer || empty( $offer->package ) ) {
			wp_send_json_error( array( 'message' => __( 'No update is currently offered — run the check first.', 'infinity-migratex-pro' ) ), 409 );
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Filesystem : une mise à jour headless exige un accès direct
		// (la plupart des hébergements) — sinon on renvoie vers Plugins.
		$creds = request_filesystem_credentials( '', '', false, WP_PLUGIN_DIR, array() );
		if ( false === $creds ) {
			wp_send_json_error( array( 'message' => __( 'WordPress needs your FTP credentials for this update — use the Plugins page once, then this button will work directly.', 'infinity-migratex-pro' ) ), 409 );
		}
		WP_Filesystem( $creds );

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $basename );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		if ( true !== $result ) {
			wp_send_json_error( array( 'message' => __( 'The update failed — open the Plugins page to retry with the full screen.', 'infinity-migratex-pro' ) ), 500 );
		}

		// upgrader_process_complete a déjà re-scellé le code et purgé les
		// caches (IMP_Updater::after_update). La version exécutée en mémoire
		// reste l'ancienne : le rechargement côté navigateur la ramène.
		wp_send_json_success( array( 'message' => __( 'Update installed — reloading…', 'infinity-migratex-pro' ) ) );
	}

	/**
	 * AJAX : vérification immédiate des DEUX canaux de mise à jour —
	 * WordPress.org (natif, via l'API officielle) et GitHub Releases
	 * (direct/proxy quand le canal est activé). Un seul bouton, les deux
	 * systèmes.
	 *
	 * @return void
	 */
	public static function ajax_update_check() {
		IMP_Security::ajax_guard( 'manage' );

		$status = array(
			'installed' => IMP_VERSION,
			'github'    => null,
			'wporg'     => array(
				'available' => false,
				'latest'    => IMP_VERSION,
			),
			'checked_at' => time(),
		);

		// Canal GitHub : classe absente du paquet wp.org (canal natif).
		// Inactif => aucun appel API, simple constat d'état. La valeur
		// fraîche vient du cache ETag (le check forcé unique est fait
		// dans IMP_Cron::detect_update ci-dessous).
		if ( class_exists( 'IMP_Updater' ) && INFINITY_MIGRATEX_PRO_GH_UPDATES ) {
			$release = IMP_Updater::latest_release();
			$gh      = array(
				'active'    => true,
				'latest'    => '',
				'available' => false,
				'error'     => '',
			);
			$cache = get_transient( 'imp_gh_latest' );
			if ( is_array( $cache ) && ! empty( $cache['__error'] ) ) {
				$gh['error'] = isset( $cache['error_message'] ) ? (string) $cache['error_message']
					: __( 'GitHub API unreachable — retry in one hour.', 'infinity-migratex-pro' );
			} elseif ( is_array( $release ) ) {
				$gh['latest']    = $release['tag'];
				$gh['available'] = version_compare( $release['tag'], IMP_VERSION, '>' ) && '' !== $release['download'];
			}
			$status['github'] = $gh;
		} elseif ( class_exists( 'IMP_Updater' ) ) {
			$status['github'] = array(
				'active'    => false,
				'latest'    => '',
				'available' => false,
				'error'     => '',
			);
		}

		// Détection CIBLÉE des deux canaux (une requête chacun max) —
		// le résultat est routé selon sa source réelle.
		$basename = plugin_basename( IMP_FILE );
		$check    = class_exists( 'IMP_Cron' ) ? IMP_Cron::detect_update() : null;
		$updates  = get_site_transient( 'update_plugins' );

		if ( is_array( $check ) && ! empty( $check['new_version'] ) ) {
			$slot = ( isset( $check['source'] ) && 'github' === $check['source'] ) ? 'github' : 'wporg';
			$status[ $slot ] = array(
				'available' => true,
				'latest'    => (string) $check['new_version'],
			);
			if ( 'github' === $slot && is_array( $status['github'] ) ) {
				$status['github']['active']    = true;
				$status['github']['latest']    = (string) $check['new_version'];
				$status['github']['available'] = true;
				$status['github']['error']     = '';
			}
		} elseif ( is_object( $updates ) && ! empty( $updates->response[ $basename ]->new_version ) ) {
			$status['wporg'] = array(
				'available' => true,
				'latest'    => (string) $updates->response[ $basename ]->new_version,
			);
		} else {
			$cached_offer = get_transient( 'imp_update_offer' );
			if ( is_array( $cached_offer ) && ! empty( $cached_offer['tag'] ) && version_compare( (string) $cached_offer['tag'], IMP_VERSION, '>' ) ) {
				$slot = ( isset( $cached_offer['source'] ) && 'github' === $cached_offer['source'] ) ? 'github' : 'wporg';
				$status[ $slot ] = array(
					'available' => true,
					'latest'    => (string) $cached_offer['tag'],
				);
			}
		}

		/* PERSISTANCE DE L'OFFRE : « Update now » doit pouvoir s'exécuter
		 * même si le transient core est purgé entre le check et le clic.
		 * L'offre wp.org est déjà gérée par IMP_Cron::detect_update() —
		 * ce bloc ne fait que compléter avec GitHub si wp.org n'a RIEN
		 * (jamais l'inverse : priorité officielle). 12 h de validité. */
		$gh_release = isset( $release ) && is_array( $release ) ? $release : null;
		if ( empty( $status['wporg']['available'] ) && ! empty( $status['github']['available'] ) && null !== $gh_release && '' !== $gh_release['download'] ) {
			set_transient(
				'imp_update_offer',
				array(
					'source'  => 'github',
					'tag'     => (string) $gh_release['tag'],
					'package' => (string) $gh_release['download'],
					'url'     => (string) $gh_release['url'],
				),
				12 * HOUR_IN_SECONDS
			);
		}

		wp_send_json_success( $status );
	}

	/**
	 * Icône du menu WordPress : l'image personnalisée est contrainte à la
	 * taille standard (20 px) — sans ce verrou global à l'admin, certains
	 * thèmes d'admin l'affichent à sa taille naturelle (trop grand).
	 *
	 * @return void
	 */
	public static function menu_icon_css() {
		?>
		<style>
			#adminmenu #toplevel_page_infinity-migratex-pro .wp-menu-image img {
				width: 20px;
				height: 20px;
				object-fit: contain;
				padding: 7px 0 0;
				opacity: .85;
			}
			#adminmenu #toplevel_page_infinity-migratex-pro.current .wp-menu-image img,
			#adminmenu #toplevel_page_infinity-migratex-pro.wp-has-current-submenu .wp-menu-image img {
				opacity: 1;
			}
		</style>
		<?php
	}

	/**
	 * Icônes (dashicons) par page.
	 *
	 * @param string $slug Slug de page.
	 * @return string
	 */
	public static function icon( $slug ) {
		$icons = array(
			'dashboard'    => 'dashicons-dashboard',
			'migration'    => 'dashicons-migrate',
			'backups'      => 'dashicons-database-export',
			'restore'      => 'dashicons-database-import',
			'import'       => 'dashicons-download',
			'packages'     => 'dashicons-archive',
			'scanner'      => 'dashicons-shield-alt',
			'database'     => 'dashicons-editor-table',
			'urlreplace'   => 'dashicons-randomize',
			'logs'         => 'dashicons-list-view',
			'security'     => 'dashicons-lock',
			'tools'        => 'dashicons-admin-tools',
			'woocommerce'  => 'dashicons-cart',
			'integrations' => 'dashicons-plugins-checked',
			'settings'     => 'dashicons-admin-generic',
			'about'        => 'dashicons-info-outline',
		);
		return isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'dashicons-menu';
	}

	/**
	 * Enregistre le menu et les sous-pages.
	 */
	public static function menu() {
		$pages = self::pages();
		$first = true;

		// Icône du sous-menu WooCommerce : logo officiel WooCommerce
		// (bulle violette #7F54B3), en SVG inline.
		self::$woo_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 90"><path fill="#7F54B3" d="M8 0h104c4.4 0 8 3.6 8 8v54c0 4.4-3.6 8-8 8H70l-10 20-10-20H8c-4.4 0-8-3.6-8-8V8C0 3.6 3.6 0 8 0z"/><text x="60" y="48" font-family="Helvetica,Arial,sans-serif" font-size="30" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Woo</text></svg>';

		// Icône du menu : symbole seul (sans texte) — lisible à 20 px.
		add_menu_page(
			'Infinity MigrateX Pro',
			'Infinity MigrateX',
			'infinity_migrate_manage',
			'infinity-migratex-pro',
			'__return_null',
			IMP_URL . 'assets/images/imp-icon-64.png',
			76
		);

		foreach ( $pages as $slug => $page ) {
			list( $title, $class, $method ) = $page;

			// Menu title avec logo officiel WooCommerce pour cette page.
			$menu_title = ( 'woocommerce' === $slug )
				? self::woo_icon_img() . ' ' . esc_html( $title )
				: $title;

			if ( $first ) {
				// La première entrée remplace l'entrée automatique du menu parent.
				add_submenu_page(
					'infinity-migratex-pro',
					'Infinity MigrateX Pro — ' . $title,
					$title,
					'infinity_migrate_manage',
					'infinity-migratex-pro',
					array( $class, $method )
				);
				$first = false;
				continue;
			}

			add_submenu_page(
				'infinity-migratex-pro',
				'Infinity MigrateX Pro — ' . $title,
				$menu_title,
				'infinity_migrate_manage',
				'infinity-migratex-pro-' . $slug,
				array( $class, $method )
			);
		}
	}

	/**
	 * Logo officiel WooCommerce (bulle violette) en <img> pour les menus.
	 *
	 * @param int    $size Taille en px.
	 * @param string $css_class Classes additionnelles.
	 * @return string
	 */
	private static function woo_icon_img( $size = 16, $css_class = '' ) {
		$b64 = base64_encode( (string) self::$woo_icon_svg );
		$cls = '' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '';
		return '<img src="data:image/svg+xml;base64,' . $b64 . '" width="' . (int) $size . '" height="' . (int) $size . '"' . $cls . ' style="vertical-align:text-bottom;border-radius:6px;" alt="Woo" />';
	}

	/**
	 * Slug de page courante.
	 *
	 * @return string
	 */
	public static function current_page() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$slug   = $screen ? (string) $screen->id : '';
		foreach ( array_keys( self::pages() ) as $key ) {
			if ( 'infinity-migratex-pro' === $slug ) {
				return isset( $_GET['page'] ) ? 'dashboard' : 'dashboard';
			}
			if ( false !== strpos( $slug, 'infinity-migratex-pro-' . $key ) ) {
				return $key;
			}
		}
		// Fallback fiable : paramètre page.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 === strpos( $page, 'infinity-migratex-pro-' ) ) {
			return substr( $page, strlen( 'infinity-migratex-pro-' ) );
		}
		return 'dashboard';
	}

	/**
	 * Assets — uniquement sur les pages du plugin.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'infinity-migratex-pro' ) ) {
			return;
		}

		// Versions minifiées pour la distribution — SOURCE lisible avec
		// SCRIPT_DEBUG (standard WordPress), .min sinon (~3× plus léger à
		// télécharger ET à ré-exécuter, ce qui compte à chaque navigation).
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_style(
			'imp-admin',
			IMP_URL . "assets/css/admin{$suffix}.css",
			array(),
			IMP_VERSION
		);
		wp_enqueue_script(
			'imp-admin',
			IMP_URL . "assets/js/admin{$suffix}.js",
			array(),
			IMP_VERSION,
			true
		);
		$job_status = IMP_Job::public_status();

		wp_localize_script(
			'imp-admin',
			'IMP_Admin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'postUrl'   => admin_url( 'admin-post.php' ),
				'nonce'     => wp_create_nonce( 'imp-admin' ),
				'page'      => self::current_page(),
				'job'       => $job_status,
				'confirm'   => (int) imp_setting( 'confirm_destructive', 1 ),
				'debug'     => (int) imp_setting( 'debug_mode', 0 ),
				'isPro'     => IMP_License::is_pro() ? 1 : 0,
				'theme'     => (string) imp_setting( 'ui_theme', 'light' ),
				'proUrl'    => IMP_License::checkout_url(),
				'i18n'      => array(
					'confirmDelete'   => __( 'Delete this item? This cannot be undone.', 'infinity-migratex-pro' ),
					'confirmRestore'  => __( 'Restoring will OVERWRITE the selected files and database tables on this site. Continue?', 'infinity-migratex-pro' ),
					'confirmImport'   => __( 'Importing this package will OVERWRITE existing files and database tables. Continue?', 'infinity-migratex-pro' ),
					'confirmReplace'  => __( 'Apply the search & replace to the database now?', 'infinity-migratex-pro' ),
					'working'         => __( 'Working…', 'infinity-migratex-pro' ),
					'paused'          => __( 'Paused', 'infinity-migratex-pro' ),
					'resume'          => __( 'Resume', 'infinity-migratex-pro' ),
					'cancel'          => __( 'Cancel', 'infinity-migratex-pro' ),
					'pause'           => __( 'Pause', 'infinity-migratex-pro' ),
					'close'           => __( 'Close', 'infinity-migratex-pro' ),
					'error'           => __( 'Error', 'infinity-migratex-pro' ),
					'networkError'    => __( 'Connection lost — the operation is paused automatically. Click Resume to continue.', 'infinity-migratex-pro' ),
					'jobRunningOther' => __( 'Another operation is in progress on this site.', 'infinity-migratex-pro' ),
					'starting'        => __( 'Starting…', 'infinity-migratex-pro' ),
				),
			)
		);
	}

	/**
	 * Notices d'activation / mise à jour / échec cron.
	 */
	public static function notices() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_imp_page = $screen && false !== strpos( (string) $screen->id, 'infinity-migratex-pro' );

		if ( get_transient( 'imp_activation_notice' ) && $on_imp_page ) {
			delete_transient( 'imp_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>Infinity MigrateX Pro</strong> — ' .
				esc_html__( 'ready. Start with a full backup before your first migration.', 'infinity-migratex-pro' ) . '</p></div>';
		}

		// Erreur non fatale survenue à l'activation : expliquée ici.
		$activation_error = get_option( 'imp_activation_error' );
		if ( is_array( $activation_error ) && ! empty( $activation_error['message'] ) ) {
			echo '<div class="notice notice-error"><p><strong>Infinity MigrateX Pro</strong> — ' .
				esc_html__( 'an error occurred during activation (the plugin is active, but some features may need attention):', 'infinity-migratex-pro' ) .
				' <code>' . esc_html( (string) $activation_error['message'] ) . '</code> (' . esc_html( (string) $activation_error['file'] ) . ')</p></div>';
		}

		// Dernière erreur FATALE capturée (trap boot) : l'erreur réelle,
		// sans passer par l'e-mail de debug.
		$fatal = get_option( 'imp_fatal_trap' );
		if ( is_array( $fatal ) && ! empty( $fatal['message'] ) ) {
			$clear_url = wp_nonce_url(
				add_query_arg( 'action', 'imp_clear_fatal', admin_url( 'admin-post.php' ) ),
				'imp-clear-fatal'
			);
			echo '<div class="notice notice-error"><p><strong>Infinity MigrateX Pro — ' .
				esc_html__( 'LAST FATAL ERROR CAPTURED', 'infinity-migratex-pro' ) . ':</strong><br>' .
				'<code>' . esc_html( (string) $fatal['message'] ) . '</code><br>' .
				esc_html( $fatal['file'] ) . ':' . esc_html( (string) $fatal['line'] ) . ' — v' . esc_html( (string) $fatal['version'] ) .
				'</p><p>' . esc_html__( 'If the plugin works now, this is an old report — clear it:', 'infinity-migratex-pro' ) .
				' <a href="' . esc_url( $clear_url ) . '">' . esc_html__( 'Clear this report', 'infinity-migratex-pro' ) . '</a></p></div>';
		}

		if ( get_transient( 'imp_updated_notice' ) ) {
			delete_transient( 'imp_updated_notice' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>Infinity MigrateX Pro</strong> — ' .
				esc_html__( 'updated successfully. Settings, backups and logs preserved.', 'infinity-migratex-pro' ) . '</p></div>';
		}

		if ( imp_setting( 'admin_notifications', 1 ) ) {
			// Alerte opérationnelle : uniquement dans les pages du plugin.
			// Inutile de déranger l'admin sur Articles, Pages, etc.
			$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'un indicateur d'affichage.
			if ( 0 === strpos( $current_page, 'infinity-migratex-pro' ) ) {
				$failed = IMP_Logger::query( array( 'type' => '', 'status' => 'failed', 'limit' => 1 ) );
				if ( ! empty( $failed ) && ( time() - strtotime( (string) $failed[0]['created'] ) ) < DAY_IN_SECONDS ) {
					echo '<div class="notice notice-warning is-dismissible"><p><strong>Infinity MigrateX Pro</strong> — ' .
						esc_html( sprintf( /* translators: %s: action */ __( 'the last operation “%1$s” failed: %2$s. Check the Logs page for details.', 'infinity-migratex-pro' ), $failed[0]['action'], $failed[0]['message'] ) ) .
						'</p></div>';
				}
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Layout partagé
	 * ------------------------------------------------------------------ */

	/**
	 * Ouvre le layout (sidebar + topbar).
	 *
	 * @param string $page_slug Slug courant.
	 * @return void
	 */
	public static function page_open( $page_slug ) {
		$health  = IMP_Compatibility::overall_status();
		$badges  = array(
			'healthy' => array( 'pass', __( 'System Healthy', 'infinity-migratex-pro' ) ),
			'warning' => array( 'warn', __( 'System Warning', 'infinity-migratex-pro' ) ),
			'error'   => array( 'fail', __( 'System Error', 'infinity-migratex-pro' ) ),
		);
		$badge   = isset( $badges[ $health ] ) ? $badges[ $health ] : $badges['healthy'];

		// Thème d'interface : light | dark | auto (décidé aussi côté JS).
		$theme = (string) imp_setting( 'ui_theme', 'light' );
		if ( ! in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ) {
			$theme = 'light';
		}

		// Job en cours : barre de progression fine persistante sur toutes
		// les pages du plugin (cliquable pour rouvrir le suivi).
		$job        = IMP_Job::get_active();
		$top_percent = $job ? (float) IMP_Job::public_status()['percent'] : 0.0;

		$base = admin_url( 'admin.php?page=infinity-migratex-pro' );
		?>
		<a class="imp-skip" href="#imp-main-content"><?php esc_html_e( 'Skip to main content', 'infinity-migratex-pro' ); ?></a>
		<div class="imp-wrap imp-theme-<?php echo esc_attr( $theme ); ?>" data-imp-page="<?php echo esc_attr( $page_slug ); ?>" data-imp-theme="<?php echo esc_attr( $theme ); ?>">
			<button type="button" class="imp-nav-toggle" data-imp-nav-toggle aria-label="<?php esc_attr_e( 'Open menu', 'infinity-migratex-pro' ); ?>" aria-expanded="false">
				<span class="dashicons dashicons-menu" aria-hidden="true"></span>
			</button>
				<aside class="imp-sidebar">
					<div class="imp-sidebar-brand">
						<img class="imp-brand-mark-img" src="<?php echo esc_url( IMP_URL . 'assets/images/imp-icon-64.png' ); ?>" alt="Infinity MigrateX Pro" />
						<div class="imp-brand-text">
							<strong>Infinity MigrateX Pro</strong>
							<em>Migration &amp; Backup Suite</em>
							<?php /* Identité d'édition : PRO affiché en or, Free en
							 * neutre — visible en permanence dans la navigation. */ ?>
							<span class="imp-edition-tag<?php echo IMP_License::is_pro() ? ' is-pro' : ''; ?>">
								<?php echo IMP_License::is_pro() ? '★ ' . esc_html__( 'PRO EDITION', 'infinity-migratex-pro' ) : esc_html__( 'FREE EDITION', 'infinity-migratex-pro' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</span>
					</div>
				</div>
				<nav class="imp-nav" aria-label="<?php esc_attr_e( 'Infinity MigrateX Pro menu', 'infinity-migratex-pro' ); ?>">
					<?php foreach ( self::pages() as $slug => $page ) : ?>
						<?php
						$url = ( 'dashboard' === $slug )
							? $base
							: $base . '-' . $slug;
						$active = ( $slug === $page_slug );
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="imp-nav-item<?php echo $active ? ' is-active' : ''; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>>
							<?php if ( 'woocommerce' === $slug ) : ?>
								<?php echo self::woo_icon_img( 27, 'imp-nav-woo' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG base64 du logo officiel WooCommerce. ?>
							<?php else : ?>
								<span class="dashicons <?php echo esc_attr( self::icon( $slug ) ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="imp-nav-label"><?php echo esc_html( $page[0] ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="imp-sidebar-footer">
					<?php if ( IMP_License::is_pro() ) : ?>
						<?php
						$pro_license = IMP_License::get();
						$pro_until   = (int) $pro_license['expires'] > 0
							? mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $pro_license['expires'] ) )
							: '';
						?>
						<span class="imp-edition-license">★ <?php echo esc_html( sprintf( /* translators: %s: plan */ __( 'PRO — %s', 'infinity-migratex-pro' ), (string) $pro_license['plan'] ) ); ?></span>
						<?php if ( '' !== $pro_until ) : ?>
							<span class="imp-byline-sub"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'valid until %s', 'infinity-migratex-pro' ), $pro_until ) ); ?></span>
						<?php else : ?>
							<span class="imp-byline-sub"><?php esc_html_e( 'lifetime license', 'infinity-migratex-pro' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<button type="button" class="imp-gopro-side" data-imp-checkout="personal">★ <?php esc_html_e( 'Upgrade to PRO', 'infinity-migratex-pro' ); ?></button>
						<span class="imp-byline">∞ Infinity Coder</span>
						<span class="imp-byline-sub"><?php esc_html_e( 'by Derouiche Oussama', 'infinity-migratex-pro' ); ?></span>
					<?php endif; ?>
				</div>
			</aside>
			<div class="imp-backdrop" data-imp-backdrop aria-hidden="true"></div>
			<div class="imp-main">
				<?php if ( $job ) : ?>
					<div class="imp-top-progress <?php echo esc_attr( $job['status'] ); ?>" data-imp-top-progress role="status">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-' . $job['type'] ) ); ?>" class="imp-top-progress-bar">
							<span class="imp-top-progress-fill" style="width:<?php echo esc_attr( $top_percent ); ?>%;"></span>
						</a>
						<span class="imp-top-progress-label">
							<span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
							<?php echo esc_html( $job['title'] ); ?> — <strong data-imp-top-pct><?php echo esc_html( $top_percent ); ?>%</strong>
						</span>
					</div>
				<?php endif; ?>
				<header class="imp-topbar">
					<div class="imp-topbar-left">
						<strong class="imp-topbar-title">Infinity MigrateX Pro</strong>
						<span class="imp-version">v<?php echo esc_html( IMP_VERSION ); ?></span>
					</div>
					<div class="imp-topbar-right">
						<?php if ( IMP_License::is_pro() ) : ?>
							<span class="imp-pill imp-edition-pill-pro" title="<?php esc_attr_e( 'PRO edition active on this site', 'infinity-migratex-pro' ); ?>">★ PRO</span>
						<?php else : ?>
							<span class="imp-pill imp-edition-pill-free">FREE</span>
							<button type="button" class="imp-pill imp-pill-upgrade" data-imp-checkout="personal">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></button>
						<?php endif; ?>
						<?php
						// Badge de mise à jour : alimenté par la détection
						// passive (cron) ou le dernier check — visible sur
						// toutes les pages du plugin, clic = Update Center.
						$upd_offer = get_transient( 'imp_update_offer' );
						if ( is_array( $upd_offer ) && ! empty( $upd_offer['tag'] ) && version_compare( (string) $upd_offer['tag'], IMP_VERSION, '>' ) ) :
							?>
							<a class="imp-pill imp-pill-update" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-about' ) ); ?>">
								⟳ <?php
								echo esc_html( sprintf(
									/* translators: %s: version */
									__( 'Update %s', 'infinity-migratex-pro' ),
									(string) $upd_offer['tag']
								) );
								?>
							</a>
						<?php endif; ?>
						<span class="imp-pill imp-pill-<?php echo esc_attr( $badge[0] ); ?>">
							<span class="imp-dot" aria-hidden="true"></span><?php echo esc_html( $badge[1] ); ?>
						</span>
					</div>
				</header>
				<main class="imp-content" id="imp-main-content" tabindex="-1">
		<?php
	}

	/**
	 * Ferme le layout.
	 */
	public static function page_close() {
		?>
				</main>
				<footer class="imp-footer">
					<span>∞ Infinity MigrateX Pro — <?php
					/* translators: %s: plugin version. */
					echo esc_html( sprintf( __( 'version %s', 'infinity-migratex-pro' ), IMP_VERSION ) );
					?></span>
					<span><?php esc_html_e( 'by Derouiche Oussama · Infinity Coder', 'infinity-migratex-pro' ); ?></span>
				</footer>
			</div>
			<?php IMP_Checkout::render(); // Tunnel d'achat Pro intégré (dans .imp-wrap : hérite du thème). ?>
		</div>
		<?php
	}

	/**
	 * En-tête de section interne.
	 *
	 * @param string $title    Titre.
	 * @param string $subtitle Sous-titre.
	 * @param string $actions  HTML d'actions (déjà échappé).
	 * @return void
	 */
	public static function section_header( $title, $subtitle = '', $actions = '' ) {
		echo '<div class="imp-section-head">';
		echo '<div><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $subtitle ) {
			echo '<p>' . esc_html( $subtitle ) . '</p>';
		}
		echo '</div>';
		if ( '' !== $actions ) {
			echo '<div class="imp-section-actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML contrôlé en amont.
		}
		echo '</div>';
	}

	/**
	 * Badge d'état.
	 *
	 * @param string $level pass|warn|fail|info|neutral.
	 * @param string $label Texte.
	 * @return string HTML.
	 */
	public static function badge( $level, $label ) {
		return '<span class="imp-badge imp-badge-' . esc_attr( $level ) . '">' . esc_html( $label ) . '</span>';
	}

	/* ------------------------------------------------------------------ *
	 * AJAX
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre toutes les actions AJAX.
	 */
	private static function register_ajax() {
		$actions = array(
			'imp_job_start'            => array( __CLASS__, 'ajax_job_start' ),
			'imp_job_step'             => array( __CLASS__, 'ajax_job_step' ),
			'imp_job_cancel'           => array( __CLASS__, 'ajax_job_cancel' ),
			'imp_job_status'           => array( __CLASS__, 'ajax_job_status' ),
			'imp_migration_preflight'  => array( 'IMP_Admin_Migration', 'ajax_preflight' ),
			'imp_migration_start'      => array( 'IMP_Admin_Migration', 'ajax_start' ),
			'imp_backups_action'       => array( 'IMP_Admin_Backup', 'ajax_action' ),
			'imp_package_scan'         => array( 'IMP_Admin_Packages', 'ajax_scan' ),
			'imp_package_start_import' => array( 'IMP_Admin_Packages', 'ajax_start_import' ),
			'imp_package_action'       => array( 'IMP_Admin_Packages', 'ajax_action' ),
			'imp_replace_preview'      => array( 'IMP_Admin_URLReplace', 'ajax_preview' ),
			'imp_replace_start'        => array( 'IMP_Admin_URLReplace', 'ajax_start' ),
			'imp_db_tables'            => array( 'IMP_Admin_Database', 'ajax_tables' ),
			'imp_db_action'            => array( 'IMP_Admin_Database', 'ajax_action' ),
			'imp_tools_action'         => array( 'IMP_Admin_Tools', 'ajax_action' ),
			'imp_integrations_action'  => array( 'IMP_Admin_Integrations', 'ajax_action' ),
			'imp_health_refresh'       => array( 'IMP_Admin_Dashboard', 'ajax_health_refresh' ),
			'imp_logs_action'          => array( 'IMP_Admin_Logs', 'ajax_action' ),
			'imp_settings_save'        => array( 'IMP_Admin_Settings', 'ajax_save' ),
			'imp_settings_save_all'    => array( 'IMP_Admin_Settings', 'ajax_save_all' ),
			'imp_settings_export'      => array( 'IMP_Admin_Settings', 'ajax_settings_export' ),
			'imp_settings_import'      => array( 'IMP_Admin_Settings', 'ajax_settings_import' ),
			'imp_settings_reset'       => array( 'IMP_Admin_Settings', 'ajax_settings_reset' ),
			'imp_update_check'         => array( 'IMP_Admin', 'ajax_update_check' ),
			'imp_update_run'           => array( 'IMP_Admin', 'ajax_update_run' ),
			'imp_cloud_test'           => array( 'IMP_Admin_Settings', 'ajax_cloud_test' ),
			'imp_security_action'      => array( 'IMP_Admin_Security', 'ajax_action' ),
			'imp_wc_action'            => array( 'IMP_Admin_WooCommerce', 'ajax_action' ),
			'imp_import_upload'        => array( 'IMP_Admin_Import', 'ajax_upload' ),
			'imp_import_start'         => array( 'IMP_Admin_Import', 'ajax_start' ),
			'imp_system_check'         => array( 'IMP_Admin_Tools', 'ajax_system_check' ),
		);

		foreach ( $actions as $tag => $callback ) {
			add_action( 'wp_ajax_' . $tag, $callback );
		}
	}

	/**
	 * Endpoints admin-post (téléchargements).
	 */
	private static function register_post_endpoints() {
		add_action( 'admin_post_imp_download_backup', array( __CLASS__, 'download_backup' ) );
		add_action( 'admin_post_imp_download_package', array( __CLASS__, 'download_package' ) );
		add_action( 'admin_post_imp_download_log', array( __CLASS__, 'download_log' ) );
		add_action( 'admin_post_imp_download_diagnostics', array( __CLASS__, 'download_diagnostics' ) );
		add_action( 'admin_post_imp_clear_fatal', array( __CLASS__, 'clear_fatal' ) );
	}

	/**
	 * Efface le rapport d'erreur fatale capturée (résolu).
	 */
	public static function clear_fatal() {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'imp-clear-fatal' ) || ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'infinity-migratex-pro' ), '', array( 'response' => 403 ) );
		}
		delete_option( 'imp_fatal_trap' );
		wp_safe_redirect( admin_url( 'admin.php?page=infinity-migratex-pro' ) );
		exit;
	}

	/**
	 * Démarrage d'un job (type selon whitelist + capability adaptée).
	 */
	public static function ajax_job_start() {
		IMP_Security::ajax_guard( 'manage' );

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$data = isset( $_POST['data'] ) ? json_decode( wp_unslash( (string) $_POST['data'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON structuré validé ensuite.
		$data = is_array( $data ) ? $data : array();

		$capability_map = array(
			'backup'         => 'backup',
			'restore'        => 'restore',
			'migration'      => 'manage',
			'scan'           => 'manage',
			'replace'        => 'manage',
			'package_import' => 'restore',
			'db_import'      => 'restore',
			'remote'         => 'backup',
		);

		if ( ! isset( $capability_map[ $type ] ) ) {
			wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
		}
		if ( ! IMP_Capabilities::user_can( $capability_map[ $type ] ) ) {
			wp_send_json_error( array( 'code' => 'IMP-403', 'message' => IMP_Job::error_text( 'IMP-403' ) ), 403 );
		}
		if ( 'remote' === $type && ! IMP_License::is_pro() ) {
			wp_send_json_error( array( 'code' => 'IMP-241', 'message' => IMP_Job::error_text( 'IMP-241' ) ), 402 );
		}

		// Le démarrage de migration clone porte des identifiants DB :
		// traités par le wizard (AJAX dédié), jamais ici.
		if ( 'migration' === $type ) {
			unset( $data['db'], $data['db_overwrite'] );
		}

		$result = IMP_Job::start( $type, $data );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'code'    => $result['error'],
					'message' => IMP_Job::error_text( $result['error'] ),
				),
				409
			);
		}

		wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
	}

	/**
	 * Un step de job.
	 */
	public static function ajax_job_step() {
		IMP_Security::ajax_guard( 'manage' );
		wp_send_json_success( array( 'status' => IMP_Job::step() ) );
	}

	/**
	 * Annulation.
	 */
	public static function ajax_job_cancel() {
		IMP_Security::ajax_guard( 'manage' );
		wp_send_json_success( array( 'status' => IMP_Job::cancel() ) );
	}

	/**
	 * Statut seul (polling léger).
	 */
	public static function ajax_job_status() {
		IMP_Security::ajax_guard( 'manage' );
		wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
	}

	/* ------------------------------------------------------------------ *
	 * Téléchargements (admin-post, streaming)
	 * ------------------------------------------------------------------ */

	/**
	 * Garde commune admin-post : nonce + capability.
	 *
	 * @param string $capability_action Action capability.
	 * @return void
	 */
	private static function post_guard( $capability_action = 'manage' ) {
		// Anti-cracker : limite sur les téléchargements sensibles.
		if ( class_exists( 'IMP_Hardening' ) && ! IMP_Hardening::rate_ok( 'download', 30, 60 ) ) {
			wp_die( esc_html__( 'Too many requests — wait a minute (anti brute-force protection).', 'infinity-migratex-pro' ), '', array( 'response' => 429 ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'imp-download' ) || ! IMP_Capabilities::user_can( $capability_action ) ) {
			if ( class_exists( 'IMP_Hardening' ) ) {
				IMP_Hardening::log_event( 'unauthorized-download', __( 'Blocked download attempt (nonce/capability failed).', 'infinity-migratex-pro' ) );
			}
			wp_die( esc_html__( 'Unauthorized request.', 'infinity-migratex-pro' ), '', array( 'response' => 403 ) );
		}
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'infinity-migratex-pro' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Stream générique par blocs.
	 *
	 * @param string $file     Chemin.
	 * @param string $filename Nom téléchargé.
	 * @return void
	 */
	private static function stream_file( $file, $filename ) {
		if ( ! is_readable( $file ) ) {
			wp_die( esc_html__( 'File not found.', 'infinity-migratex-pro' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $file ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:disable WordPress.WP.AlternativeFunctions -- Streaming d'archives de plusieurs centaines de Mo : WP_Filesystem ne sait pas lire par blocs.
		$in = fopen( $file, 'rb' );
		if ( ! is_resource( $in ) ) {
			wp_die( esc_html__( 'File not readable.', 'infinity-migratex-pro' ), '', array( 'response' => 500 ) );
		}
		while ( ! feof( $in ) ) {
			$chunk = fread( $in, 1024 * 1024 );
			if ( false === $chunk ) {
				break;
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput -- binaire brut.
			flush();
		}
		fclose( $in );
		exit;
	}

	/**
	 * Téléchargement d'un backup (zip construit à la volée puis supprimé).
	 */
	public static function download_backup() {
		self::post_guard( 'backup' );

		$id = isset( $_GET['id'] ) ? IMP_Backup_Engine::sanitize_id( wp_unslash( $_GET['id'] ) ) : '';
		if ( '' === $id ) {
			wp_die( esc_html__( 'Invalid backup reference.', 'infinity-migratex-pro' ), '', array( 'response' => 400 ) );
		}

		$manifest = IMP_Backup_Engine::read_manifest( $id );
		if ( null === $manifest ) {
			wp_die( esc_html__( 'Backup not found.', 'infinity-migratex-pro' ), '', array( 'response' => 404 ) );
		}

		$zip = IMP_Backup_Engine::build_download_zip( $id );
		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ), '', array( 'response' => 500 ) );
		}

		self::stream_file( $zip, 'infinitymigrate-backup-' . $id . '.zip' );

		// Le exit de stream_file coupe avant — suppression préalable impossible
		// en toute sécurité : on planifie le nettoyage via cron maintenance.
	}

	/**
	 * Téléchargement d'un package.
	 */
	public static function download_package() {
		self::post_guard( 'backup' );

		$file = isset( $_GET['file'] ) ? basename( sanitize_file_name( wp_unslash( (string) $_GET['file'] ) ) ) : '';
		$path = IMP_Package::dir() . $file;
		if ( '' === $file || ! is_file( $path ) ) {
			wp_die( esc_html__( 'Package not found.', 'infinity-migratex-pro' ), '', array( 'response' => 404 ) );
		}

		self::stream_file( $path, $file );
	}

	/**
	 * Export des logs (CSV / JSON).
	 */
	public static function download_log() {
		self::post_guard( 'logs' );

		$format = ( isset( $_GET['format'] ) && 'json' === $_GET['format'] ) ? 'json' : 'csv';
		$rows   = IMP_Logger::query( array( 'limit' => 200 ) );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="infinitymigrate-logs-' . gmdate( 'Ymd-His' ) . '.json"' );
			echo wp_json_encode( $rows );
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="infinitymigrate-logs-' . gmdate( 'Ymd-His' ) . '.csv"' );

		// Construit en mémoire (pas de flux php://output) avec échappement
		// CSV correct et neutralisation de l'injection de formule tableur.
		echo 'id,date,action,type,status,user,duration,files,rows,bytes,message' . "\r\n";
		foreach ( $rows as $row ) {
			$fields = array(
				(string) $row['id'],
				(string) $row['created'],
				(string) $row['action'],
				(string) $row['type'],
				(string) $row['status'],
				(string) $row['user_id'],
				(string) $row['duration'],
				(string) $row['files_processed'],
				(string) $row['db_rows'],
				(string) $row['size_bytes'],
				(string) $row['message'],
			);
			echo implode( ',', array_map( array( __CLASS__, 'csv_field' ), $fields ) ) . "\r\n";
		}
		exit;
	}

	/**
	 * Échappe un champ CSV (guillemets, séparateurs) et neutralise
	 * l'injection de formule pour les tableurs (= + - @ en début).
	 *
	 * @param string $value Valeur brute.
	 * @return string
	 */
	private static function csv_field( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		if ( preg_match( '/[",\r\n]/', $value ) ) {
			$value = '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	/**
	 * Export du diagnostic système (JSON, sans secrets).
	 */
	public static function download_diagnostics() {
		self::post_guard( 'manage' );

		$report = IMP_Integrations_WordPress::system_report();
		$report['health'] = IMP_Compatibility::health_checks();
		$last_scan        = IMP_Scanner::last_report();
		if ( $last_scan ) {
			$report['last_scan_summary'] = $last_scan['summary'];
		}

		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="infinitymigrate-diagnostics-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT );
		exit;
	}
}

/* L'initialisation (IMP_Admin::init) est faite dans le fichier principal
 * après le chargement de toutes les classes admin — jamais ici, pour
 * éviter une double initialisation des hooks et du menu. */
