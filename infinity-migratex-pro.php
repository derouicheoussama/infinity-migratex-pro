<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity MigrateX Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * GitHub   : https://github.com/derouicheoussama
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — toute copie ou modification de ce
 *            fichier DOIT conserver la présente signature et les mentions
 *            de licence et d'attribution (article 2(c) de la GPL).
 */
/**
 * Plugin Name:       Infinity MigrateX Pro
 * Plugin URI:        https://github.com/derouicheoussama/infinity-migratex-pro
 * Description:       Migrer, sauvegarder et restaurer un site WordPress sans timeout : changement de domaine avec URLs réécrites sans casser les données sérialisées, clonage staging, sauvegardes automatiques avec rétention, restauration vérifiée par checksums, scanner de sécurité et journal détaillé. Moteur par chunks avec reprise après interruption — WooCommerce et Elementor inclus.
 * Version:           3.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            Derouiche Oussama
 * Author URI:        https://www.derouicheoussama.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       infinity-migratex-pro
 * Domain Path:       /languages
 *
 * @package InfinityMigratePro
 */

defined( 'ABSPATH' ) || exit;

define( 'IMP_VERSION', '3.5.0' );
define( 'IMP_DB_VERSION', '1.0.0' );
define( 'IMP_FILE', __FILE__ );
define( 'IMP_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Mise à jour depuis GitHub Releases (côté développeur).
 * Définir ces constantes dans wp-config.php pour changer de dépôt :
 *   define( 'INFINITY_MIGRATEX_PRO_GH_REPO', 'derouicheoussama/infinity-migratex-pro' );
 *   define( 'INFINITY_MIGRATEX_PRO_GH_ASSET', 'infinity-migratex-pro.zip' );
 */
if ( ! defined( 'INFINITY_MIGRATEX_PRO_GH_REPO' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_GH_REPO', 'derouicheoussama/infinity-migratex-pro' );
}
if ( ! defined( 'INFINITY_MIGRATEX_PRO_GH_ASSET' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_GH_ASSET', 'infinity-migratex-pro.zip' );
}

/**
 * Architecture licence Pro (fonctionne déjà, vide = mode gratuit).
 *   define( 'INFINITY_MIGRATEX_PRO_CHECKOUT_URL', 'https://…' );
 *   define( 'INFINITY_MIGRATEX_PRO_LICENSE_API', 'https://…' );
 */
if ( ! defined( 'INFINITY_MIGRATEX_PRO_CHECKOUT_URL' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_CHECKOUT_URL', '' );
}
if ( ! defined( 'INFINITY_MIGRATEX_PRO_LICENSE_API' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_LICENSE_API', '' );
}

/**
 * Secret partagé de signature des réponses de licence (anti-contrefaçon).
 * Défini côté serveur de licence ET ici : les réponses non signées sont
 * refusées. Voir docs/PRO-LICENSING.md.
 */
if ( ! defined( 'INFINITY_MIGRATEX_PRO_LICENSE_SECRET' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_LICENSE_SECRET', '' );
}

/**
 * Canal de mise à jour : 'free' (repo public) ou 'pro' (releases du dépôt
 * Pro — privé — accessibles via INFINITY_MIGRATEX_PRO_GH_TOKEN).
 */
if ( ! defined( 'INFINITY_MIGRATEX_PRO_UPDATE_CHANNEL' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_UPDATE_CHANNEL', 'free' );
}
if ( ! defined( 'INFINITY_MIGRATEX_PRO_GH_TOKEN' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_GH_TOKEN', '' );
}

/* ------------------------------------------------------------------ *
 * Diagnostics : capture de toute erreur fatale provenant des fichiers
 * du plugin (y compris parse et mémoire) et stockage pour affichage
 * dans l'administration — l'utilisateur voit l'erreur RÉELLE sans
 * avoir à ouvrir l'e-mail de debug.
 * ------------------------------------------------------------------ */

register_shutdown_function(
	static function () {
		$e = function_exists( 'error_get_last' ) ? error_get_last() : null;
		if ( ! is_array( $e ) || empty( $e['message'] ) || empty( $e['file'] ) ) {
			return;
		}
		if ( false === strpos( (string) $e['file'], 'infinity-migratex-pro' ) ) {
			return; // ne concerne pas ce plugin.
		}
		if ( ! in_array( (int) $e['type'], array( 1, 4, 16, 64, 256 ), true ) ) {
			return; // E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR.
		}
		$payload = array(
			'message' => mb_substr( (string) $e['message'], 0, 300 ),
			'file'    => '…' . substr( (string) $e['file'], -100 ),
			'line'    => (int) $e['line'],
			'time'    => time(),
			'version' => defined( 'IMP_VERSION' ) ? IMP_VERSION : '?',
		);
		if ( function_exists( 'update_option' ) ) {
			update_option( 'imp_fatal_trap', $payload, false );
		}
	}
);

/**
 * Auto-installe un mu-plugin de diagnostic : il affiche l'erreur fatale
 * RÉELLE dans l'administration même quand le plugin n'arrive pas à
 * s'activer (l'admin continue de fonctionner car le mu-plugin est
 * indépendant). Supprimé automatiquement dès que le plugin démarre
 * correctement, et par uninstall.php.
 */
if ( is_admin() ) {
	$imp_mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : trailingslashit( WP_CONTENT_DIR ) . 'mu-plugins';
	$imp_mu_dir = trailingslashit( $imp_mu_dir );
	if ( ! is_dir( $imp_mu_dir ) ) {
		@wp_mkdir_p( $imp_mu_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$imp_diag_php = $imp_mu_dir . 'imp-diag.php';
	if ( ! file_exists( $imp_diag_php ) ) {
		@wp_mkdir_p( $imp_mu_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$imp_diag_content = <<<'MUDIAG'
<?php
/* Infinity MigrateX Pro - diagnostic mu-plugin (auto-genere, supprimable sans risque). */
add_action( 'admin_notices', static function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$t = get_option( 'imp_fatal_trap' );
	$a = get_option( 'imp_activation_error' );
	if ( is_array( $t ) && ! empty( $t['message'] ) ) {
		echo '<div class="notice notice-error"><p><strong>Infinity MigrateX Pro - DERNIERE ERREUR FATALE :</strong><br><code>' . esc_html( $t['message'] ) . '</code><br>' . esc_html( $t['file'] ) . ':' . (int) $t['line'] . ' (v' . esc_html( $t['version'] ) . ')</p>';
		if ( false !== stripos( (string) $t['message'], 'memory' ) ) {
			echo '<p>Solution probable : augmentez WP_MEMORY_LIMIT dans wp-config.php ( define( \'WP_MEMORY_LIMIT\', \'256M\' ); ).</p>';
		}
		echo '</div>';
	}
	if ( is_array( $a ) && ! empty( $a['message'] ) ) {
		echo '<div class="notice notice-warning"><p><strong>Infinity MigrateX Pro - erreur d\'activation :</strong> <code>' . esc_html( $a['message'] ) . '</code> (' . esc_html( $a['file'] ) . ')</p></div>';
	}
} );
MUDIAG;
		@file_put_contents( $imp_diag_php, $imp_diag_content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		unset( $imp_diag_content );
	}
	unset( $imp_mu_dir, $imp_diag_php );
}

/**
 * Le plugin a démarré correctement : retirer le mu-plugin de diagnostic.
 */
add_action(
	'admin_init',
	static function () {
		// On ne retire le diagnostic QUE s'il n'y a rien à signaler.
		if ( get_option( 'imp_fatal_trap' ) || get_option( 'imp_activation_error' ) ) {
			return;
		}
		$mu = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : trailingslashit( WP_CONTENT_DIR ) . 'mu-plugins' ) . '/imp-diag.php';
		if ( file_exists( $mu ) ) {
			wp_delete_file( $mu );
		}
	},
	99
);

/* ------------------------------------------------------------------ *
 * Chargement des modules
 * ------------------------------------------------------------------ */

require_once IMP_DIR . 'includes/compatibility.php';
require_once IMP_DIR . 'includes/helpers.php';
require_once IMP_DIR . 'includes/logger.php';
require_once IMP_DIR . 'includes/license.php';

/**
 * Canal de mise à jour « direct GitHub » (édition distribuée hors wp.org).
 * Le paquet officiel WordPress.org n'inclut PAS ce module (règle Plugin
 * Check respectée par exclusion du fichier) : les mises à jour de cette
 * édition passent par le dépôt officiel.
 *
 * DÉFAUT INTELLIGENT : le canal GitHub est ACTIF dès que le module
 * updater est présent (édition GitHub téléchargée depuis les releases) —
 * plus aucune constante à ajouter pour recevoir les mises à jour.
 * La constante reste disponible pour le DÉSACTIVER explicitement :
 *   define( 'INFINITY_MIGRATEX_PRO_GH_UPDATES', false );
 *
 * La CLASSE est chargée dès que le fichier existe (le panneau « Mises à
 * jour » affiche l'état des deux canaux) ; seuls les HOOKS d'update sont
 * branchés quand le canal GitHub est actif.
 */
if ( ! defined( 'INFINITY_MIGRATEX_PRO_GH_UPDATES' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_GH_UPDATES', file_exists( IMP_DIR . 'includes/class-updater.php' ) );
}
if ( file_exists( IMP_DIR . 'includes/class-updater.php' ) ) {
	require_once IMP_DIR . 'includes/class-updater.php';
}

/**
 * Mode sans échec (bissection) : define( 'INFINITY_MIGRATEX_PRO_SAFE_MODE', true );
 * dans wp-config.php pour charger le plugin SANS les modules lourds
 * (cloud, hardening, page Extensions enrichie). Le cœur (backup,
 * migration, restore) reste opérationnel — permet d'isoler une
 * incompatibilité d'hébergement.
 */
if ( ! defined( 'INFINITY_MIGRATEX_PRO_SAFE_MODE' ) ) {
	define( 'INFINITY_MIGRATEX_PRO_SAFE_MODE', false );
}

/**
 * PERFORMANCE — le plugin n'a AUCUNE fonctionnalité front-end : il ne se
 * charge que dans les contextes où il travaille (admin, AJAX, cron, CLI).
 * Une visite publique du site ne paie plus un octet de son code.
 */
if ( ! is_admin() && ! wp_doing_cron() && ! wp_doing_ajax()
	&& ( ! defined( 'WP_CLI' ) || ! WP_CLI ) ) {
	return;
}

if ( ! INFINITY_MIGRATEX_PRO_SAFE_MODE ) {
	require_once IMP_DIR . 'core/class-remote.php';
	require_once IMP_DIR . 'core/class-hardening.php';
}

require_once IMP_DIR . 'core/class-security.php';
require_once IMP_DIR . 'core/class-files.php';
require_once IMP_DIR . 'core/class-database.php';
require_once IMP_DIR . 'core/class-url-replacer.php';
require_once IMP_DIR . 'core/class-integrity.php';
require_once IMP_DIR . 'core/class-package.php';
require_once IMP_DIR . 'core/class-scanner.php';
require_once IMP_DIR . 'core/class-migrator.php';
require_once IMP_DIR . 'core/class-job.php';
require_once IMP_DIR . 'core/class-crypto.php';
require_once IMP_DIR . 'core/class-backup-engine.php';

require_once IMP_DIR . 'integrations/class-wordpress.php';
require_once IMP_DIR . 'integrations/class-woocommerce.php';
require_once IMP_DIR . 'integrations/class-elementor.php';

// Garde-fou des mises à jour WordPress (sauvegarde de sécurité avant
// update) — doit être chargé en admin ET en cron (mises à jour auto).
require_once IMP_DIR . 'includes/class-upgrade-guard.php';
IMP_Upgrade_Guard::init();

if ( is_admin() ) {
	global $pagenow;

	// PERFORMANCE — contexte du plugin ? Hors contexte (Articles, Médias,
	// Réglages WP…) seules la coquille admin et le checkout sont chargés :
	// les 16 classes de pages ne se chargent que lorsqu'elles servent à
	// quelque chose (page du plugin, AJAX imp_*, page Extensions).
	$imp_context = isset( $_REQUEST['page'] ) && 0 === strpos( sanitize_key( wp_unslash( (string) $_REQUEST['page'] ) ), 'infinity-migratex-pro' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule d'un indicateur de routage.
	if ( ! $imp_context && isset( $_REQUEST['action'] ) && 0 === strpos( sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ), 'imp_' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$imp_context = true;
	}
	if ( ! $imp_context && 'plugins.php' === $pagenow ) {
		$imp_context = true;
	}

	require_once IMP_DIR . 'admin/class-admin.php';
	require_once IMP_DIR . 'admin/class-checkout.php';

	if ( $imp_context ) {
		require_once IMP_DIR . 'admin/class-dashboard.php';
		require_once IMP_DIR . 'admin/class-migration.php';
		require_once IMP_DIR . 'admin/class-backup.php';
		require_once IMP_DIR . 'admin/class-restore.php';
		require_once IMP_DIR . 'admin/class-packages.php';
		require_once IMP_DIR . 'admin/class-scanner.php';
		require_once IMP_DIR . 'admin/class-database.php';
		require_once IMP_DIR . 'admin/class-urlreplace.php';
		require_once IMP_DIR . 'admin/class-logs.php';
		require_once IMP_DIR . 'admin/class-security.php';
		require_once IMP_DIR . 'admin/class-tools.php';
		require_once IMP_DIR . 'admin/class-woocommerce.php';
		require_once IMP_DIR . 'admin/class-import.php';
		require_once IMP_DIR . 'admin/class-integrations.php';
		require_once IMP_DIR . 'admin/class-settings.php';
		require_once IMP_DIR . 'admin/class-about.php';
	}

	// Initialisation après le chargement de toutes les classes admin.
	IMP_Admin::init();
	IMP_Checkout::init();
	if ( ! INFINITY_MIGRATEX_PRO_SAFE_MODE && 'plugins.php' === $pagenow ) {
		require_once IMP_DIR . 'admin/class-plugins-page.php';
		IMP_Admin_Plugins_Page::init();
	}
}

/* ------------------------------------------------------------------ *
 * Activation / désactivation
 * ------------------------------------------------------------------ */

register_activation_hook(
	__FILE__,
	static function () {
		// L'activation ne doit JAMAIS provoquer d'erreur critique :
		// toute exception est capturée et expliquée dans l'admin.
		try {
			IMP_Logger::install();
			IMP_Settings_defaults::seed();

			// Capabilities dédiées, réservées aux administrateurs.
			$admin = get_role( 'administrator' );
			if ( $admin ) {
				foreach ( IMP_Capabilities::all() as $cap ) {
					$admin->add_cap( $cap );
				}
			}

			IMP_Security::bootstrap_storage();
			IMP_Cron::activate();
			if ( class_exists( 'IMP_Hardening' ) ) {
				IMP_Hardening::activate();
				IMP_Hardening::rebuild_baseline();
			}

			set_transient( 'imp_activation_notice', 1, 60 );
			delete_option( 'imp_activation_error' );
		} catch ( Throwable $e ) {
			// Le plugin reste activé : l'erreur sera affichée proprement
			// dans l'administration au lieu de casser le site.
			update_option(
				'imp_activation_error',
				array(
					'message' => $e->getMessage(),
					'file'    => basename( (string) $e->getFile() ) . ':' . $e->getLine(),
					'time'    => time(),
				),
				false
			);
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		IMP_Cron::deactivate();
		if ( class_exists( 'IMP_Hardening' ) ) {
			IMP_Hardening::deactivate();
		}
		IMP_Job::cancel_all();
		// Les capabilities dédiées sont retirées ; les backups ne sont
		// JAMAIS supprimés à la désactivation.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( IMP_Capabilities::all() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}
);

/* ------------------------------------------------------------------ *
 * Mise à jour interne du schéma (safe update)
 * ------------------------------------------------------------------ */

add_action(
	'plugins_loaded',
	static function () {
		IMP_Logger::maybe_upgrade();
	}
);

/* ------------------------------------------------------------------ *
 * Cron : backups programmés, nettoyage, maintenance
 * ------------------------------------------------------------------ */

final class IMP_Cron {

	public static function hooks() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'imp_cron_backup', array( __CLASS__, 'run_scheduled_backup' ) );
		add_action( 'imp_cron_maintenance', array( __CLASS__, 'run_maintenance' ) );
	}

	public static function schedules( $schedules ) {
		if ( ! isset( $schedules['imp_weekly'] ) ) {
			$schedules['imp_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly (Infinity MigrateX Pro)', 'infinity-migratex-pro' ),
			);
		}
		if ( ! isset( $schedules['imp_monthly'] ) ) {
			$schedules['imp_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly (Infinity MigrateX Pro)', 'infinity-migratex-pro' ),
			);
		}
		return $schedules;
	}

	public static function activate() {
		self::hooks();
		self::sync();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'imp_cron_backup' );
		wp_clear_scheduled_hook( 'imp_cron_maintenance' );
	}

	/**
	 * Replanifie les événements selon les réglages actuels.
	 *
	 * @return void
	 */
	public static function sync() {
		$settings = imp_settings();

		$schedule = '';
		if ( ! empty( $settings['backup_schedule_enabled'] ) ) {
			switch ( $settings['backup_schedule'] ) {
				case 'daily':
					$schedule = 'daily';
					break;
				case 'weekly':
					$schedule = 'imp_weekly';
					break;
				case 'monthly':
					$schedule = 'imp_monthly';
					break;
			}
		}

		if ( '' !== $schedule ) {
			if ( ! wp_next_scheduled( 'imp_cron_backup' ) ) {
				// Première exécution à l'heure configurée (ou +1 h au plus tôt).
				$hour = max( 0, min( 23, (int) $settings['backup_schedule_hour'] ) );
				$next = strtotime( 'today ' . sprintf( '%02d:00', $hour ) );
				if ( $next <= time() ) {
					$next += DAY_IN_SECONDS;
				}
				wp_schedule_event( $next, $schedule, 'imp_cron_backup' );
			}
		} else {
			wp_clear_scheduled_hook( 'imp_cron_backup' );
		}

		if ( ! wp_next_scheduled( 'imp_cron_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'imp_cron_maintenance' );
		}
	}

	/**
	 * Backup programmé : uniquement si l'heure configurée est atteinte
	 * pour la journée en cours (WP-Cron ne garantit pas l'heure exacte).
	 *
	 * @return void
	 */
	public static function run_scheduled_backup() {
		// Backups programmés = fonctionnalité Pro (vérifiée serveur).
		if ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) {
			return;
		}

		$settings = imp_settings();
		$hour     = max( 0, min( 23, (int) $settings['backup_schedule_hour'] ) );

		$last = (int) get_option( 'imp_last_scheduled_backup', 0 );
		$now  = time();
		if ( $last > 0 && ( $now - $last ) < DAY_IN_SECONDS ) {
			return; // déjà fait aujourd'hui
		}
		if ( (int) current_time( 'H' ) < $hour ) {
			return; // pas encore l'heure (local)
		}

		update_option( 'imp_last_scheduled_backup', $now, false );

		$components = 'full';
		if ( 'database' === $settings['backup_schedule_components'] || 'files' === $settings['backup_schedule_components'] ) {
			$components = $settings['backup_schedule_components'];
		}

		IMP_Job::start(
			'backup',
			array(
				'name'         => __( 'Scheduled backup', 'infinity-migratex-pro' ),
				'components'   => $components,
				'exclusions'   => imp_default_exclusions(),
				'origin'       => 'cron',
				'scheduled'    => true,
				// Pro : envoi vers la destination cloud après le backup.
				'remote_after' => ( ! empty( $settings['cloud_enabled'] ) && IMP_License::is_pro() ) ? 1 : 0,
			)
		);

		// Le job tourne en arrière-plan au fil des requêtes ; replanifier.
		self::sync();
	}

	/**
	 * Maintenance quotidienne : nettoyage tmp, rétention backups,
	 * rétention logs, suppression packages expirés.
	 *
	 * @return void
	 */
	public static function run_maintenance() {
		$settings = imp_settings();

		if ( ! empty( $settings['auto_cleanup'] ) ) {
			IMP_Security::cleanup_tmp( true );
		}

		IMP_Backup_Engine::apply_retention( (int) $settings['backup_retention'] );
		IMP_Package::delete_expired();
		IMP_Logger::cleanup( (int) $settings['logs_retention_days'] );

		// Détection passive des mises à jour : le cron quotidien lance la
		// vérification ciblée (1 requête) — le badge « Update » de la
		// topbar apparaît sans que l'utilisateur ait à ouvrir la page.
		self::detect_update();
	}

	/**
	 * Détection ciblée wp.org : UNE requête pour CE plugin (endpoint
	 * update-check 1.1) au lieu de purger le transient global — purger
	 * déclencherait un re-scan de TOUTES les extensions installées.
	 * L'offre est fusionnée dans le transient SANS effacer celles des
	 * autres extensions, et l'offre persistée alimente le badge/bouton.
	 *
	 * @return array|null Données d'update wp.org ou null.
	 */
	public static function detect_update() {
		$basename = plugin_basename( IMP_FILE );
		$updates  = get_site_transient( 'update_plugins' );

		/* --- Canal 1 : WordPress.org (ciblé, une requête) --- */
		$update = null;
		$response = wp_remote_post(
			'https://api.wordpress.org/plugins/update-check/1.1/',
			array(
				'timeout' => 8,
				'body'    => array(
					'plugins'      => wp_json_encode( array(
						$basename => array(
							'Name'      => 'Infinity MigrateX Pro',
							'Title'     => 'Infinity MigrateX Pro',
							'Version'   => IMP_VERSION,
							'PluginURI' => 'https://github.com/derouicheoussama/infinity-migratex-pro',
							'Author'    => 'Derouiche Oussama',
						),
					) ),
					'translations' => wp_json_encode( array() ),
					'locale'       => wp_json_encode( array( get_locale() ) ),
				),
			)
		);
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $parsed ) && isset( $parsed['plugins'][ $basename ] ) && is_array( $parsed['plugins'][ $basename ] ) ) {
				$candidate = $parsed['plugins'][ $basename ];
				if ( ! empty( $candidate['new_version'] ) && version_compare( (string) $candidate['new_version'], IMP_VERSION, '>' ) ) {
					$update = array(
						'source'     => 'wporg',
						'new_version' => (string) $candidate['new_version'],
						'package'    => isset( $candidate['package'] ) ? (string) $candidate['package'] : '',
						'url'        => isset( $candidate['url'] ) ? (string) $candidate['url'] : '',
						'requires'   => isset( $candidate['requires'] ) ? (string) $candidate['requires'] : '5.8',
						'requires_php' => isset( $candidate['requires_php'] ) ? (string) $candidate['requires_php'] : '7.4',
						'tested'     => isset( $candidate['tested'] ) ? (string) $candidate['tested'] : '7.1',
					);
				}
			}
		}

		/* --- Canal 2 : GitHub Releases (édition complète, passif) --- */
		if ( null === $update && class_exists( 'IMP_Updater' ) && INFINITY_MIGRATEX_PRO_GH_UPDATES ) {
			$release = IMP_Updater::force_check(); // 1 requête ETag/jour.
			if ( is_array( $release ) && '' !== $release['download']
				&& version_compare( (string) $release['tag'], IMP_VERSION, '>' ) ) {
				$update = array(
					'source'       => 'github',
					'new_version'  => (string) $release['tag'],
					'package'      => (string) $release['download'],
					'url'          => (string) $release['url'],
					'requires'     => '5.8',
					'requires_php' => '7.4',
					'tested'       => '7.1',
				);
			}
		}

		/* --- Offre persistée (badge, « Update now », Update Center) ---
		 * priorité wp.org ; purgée si elle n'est plus plus récente. */
		if ( null !== $update ) {
			set_transient( 'imp_update_offer', $update, 12 * HOUR_IN_SECONDS );
		} else {
			$offer = get_transient( 'imp_update_offer' );
			if ( is_array( $offer ) && ! empty( $offer['tag'] ) && version_compare( (string) $offer['tag'], IMP_VERSION, '<=' ) ) {
				delete_transient( 'imp_update_offer' );
			}
		}

		/* --- Injection PRÉSERVATIVE dans le transient core --- : jamais
		 * de création d'un transient vide, jamais d'effacement de notre
		 * entrée (le canal GitHub peut l'avoir posée juste avant). */
		if ( is_object( $updates ) && null !== $update && '' !== $update['package'] ) {
			$response_map = ( isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();
			$response_map[ $basename ] = (object) array(
				'slug'         => dirname( $basename ),
				'plugin'       => $basename,
				'new_version'  => (string) $update['new_version'],
				'url'          => (string) $update['url'],
				'package'      => (string) $update['package'],
				'requires'     => (string) $update['requires'],
				'requires_php' => (string) $update['requires_php'],
				'tested'       => (string) $update['tested'],
			);
			$updates->response = $response_map;
			set_site_transient( 'update_plugins', $updates );
		}

		return $update;
	}
}
IMP_Cron::hooks();
if ( class_exists( 'IMP_Hardening' ) ) {
	IMP_Hardening::admin_hooks();
}

/* ------------------------------------------------------------------ *
 * Boot
 * ------------------------------------------------------------------ */

add_action(
	'plugins_loaded',
	static function () {
		// Mise à jour auto via GitHub Releases — uniquement en mode
		// GH_UPDATES (édition directe), jamais sur la distribution wp.org.
		if ( INFINITY_MIGRATEX_PRO_GH_UPDATES && class_exists( 'IMP_Updater' ) && apply_filters( 'imp_enable_github_updates', true ) ) {
			IMP_Updater::init( INFINITY_MIGRATEX_PRO_GH_REPO, INFINITY_MIGRATEX_PRO_GH_ASSET );
		}
	},
	20
);

/**
 * Instance principale (usage lectures : réglages, storage).
 */
final class IMP_Plugin {

	/** @var array|null Réglages chargés. */
	private static $settings = null;

	/**
	 * Réglages du plugin (fusion defaults + sauvegarde).
	 *
	 * @return array
	 */
	public static function settings() {
		if ( null === self::$settings ) {
			$saved = get_option( 'imp_settings', array() );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}
			self::$settings = wp_parse_args( $saved, imp_default_settings() );
		}
		return self::$settings;
	}

	/**
	 * Recharge les réglages (après sauvegarde).
	 *
	 * @return void
	 */
	public static function flush_settings() {
		self::$settings = null;
	}

	/**
	 * Racine de stockage (backups/packages/tmp), protégée.
	 *
	 * @return string Chemin absolu avec slash final.
	 */
	public static function storage_dir() {
		$settings = self::settings();
		$custom   = isset( $settings['backup_location'] ) ? trim( (string) $settings['backup_location'] ) : '';
		$base     = '';
		if ( '' !== $custom ) {
			$base = imp_normalize_path( $custom );
		}
		if ( '' === $base ) {
			$base = trailingslashit( WP_CONTENT_DIR ) . 'infinity-migratex-pro';
		}

		/**
		 * Filtre l'emplacement de stockage des backups/packages.
		 *
		 * @param string $base Racine du stockage.
		 */
		$base = (string) apply_filters( 'imp_storage_dir', $base );
		$base = imp_normalize_path( $base );

		// Sécurité : jamais dans ABSPATH un niveau au-dessus du site ? autorisé
		// (hors webroot recommandé), mais JAMAIS à l'intérieur du dossier
		// du plugin lui-même (auto-inclusion dans les backups).
		$plugin_root = imp_normalize_path( IMP_DIR );
		if ( 0 === strpos( $base, $plugin_root ) ) {
			$base = trailingslashit( WP_CONTENT_DIR ) . 'infinity-migratex-pro';
		}

		return trailingslashit( $base );
	}
}

/**
 * Réglages courants (raccourci).
 *
 * @return array
 */
function imp_settings() {
	return IMP_Plugin::settings();
}

/**
 * Valeur d'un réglage (raccourci avec défaut).
 *
 * @param string $key Clé.
 * @param mixed  $fallback Valeur par défaut.
 * @return mixed
 */
function imp_setting( $key, $fallback = null ) {
	$settings = imp_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
}
