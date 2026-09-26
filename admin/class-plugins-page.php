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
 * Enrichit la ligne du plugin dans wp-admin/plugins.php :
 * - version installée / dernière version disponible ;
 * - fiche « Détails & historique » complète (modal) ;
 * - bandeau « Passer à Pro » ;
 * - liens officiels. La suppression reste l'action standard de
 * WordPress (visible plugin désactivé) — notre uninstall.php nettoie
 * proprement sans jamais toucher aux backups.
 */
final class IMP_Admin_Plugins_Page {

	/**
	 * Hook d'enregistrement.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( IMP_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'after_plugin_row_' . plugin_basename( IMP_FILE ), array( __CLASS__, 'pro_banner' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'thickbox_assets' ) );
		add_action( 'admin_footer-plugins.php', array( __CLASS__, 'footer_script' ) );
		add_action( 'admin_post_imp_plugin_details', array( __CLASS__, 'render_details_page' ) );
		add_action( 'admin_post_imp_delete_plugin', array( __CLASS__, 'delete_plugin' ) );
	}

	/**
	 * Action « Supprimer » toujours visible (WordPress la masque quand le
	 * plugin est actif) : désactive puis supprime en un clic, avec les
	 * vérifications standard (nonce + capability delete_plugins).
	 *
	 * @param array $links Liens d'action existants.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = wp_nonce_url(
			add_query_arg( 'action', 'imp_delete_plugin', admin_url( 'admin-post.php' ) ),
			'imp-delete-plugin'
		);
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '" style="color:#b32d2e;" onclick="return confirm(\'' .
			esc_js( __( 'This permanently deletes the plugin (settings and logs — backups are KEPT). Continue?', 'infinity-migratex-pro' ) ) .
			'\');">' . esc_html__( 'Delete', 'infinity-migratex-pro' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Suppression : désactive puis supprime le plugin (données de backup
	 * conservées — voir uninstall.php).
	 *
	 * @return void
	 */
	public static function delete_plugin() {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete plugins.', 'infinity-migratex-pro' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'imp-delete-plugin' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'infinity-migratex-pro' ), '', array( 'response' => 403 ) );
		}

		deactivate_plugins( plugin_basename( IMP_FILE ), true );
		$result = delete_plugins( array( plugin_basename( IMP_FILE ) ) );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
		}

		wp_safe_redirect( add_query_arg( 'imp_deleted', '1', admin_url( 'plugins.php' ) ) );
		exit;
	}

	/**
	 * Lien Pro (checkout si configuré, sinon site de l'auteur).
	 *
	 * @return string
	 */
	private static function pro_url() {
		if ( '' !== INFINITY_MIGRATEX_PRO_CHECKOUT_URL ) {
			return INFINITY_MIGRATEX_PRO_CHECKOUT_URL;
		}
		return 'https://www.derouicheoussama.com';
	}

	/**
	 * Liens de la ligne du plugin (gris sous la description).
	 *
	 * @param array  $links Liens existants.
	 * @param string $file  Fichier du plugin en cours.
	 * @return array
	 */
	public static function row_meta( $links, $file ) {
		if ( plugin_basename( IMP_FILE ) !== $file ) {
			return $links;
		}

		// 1. Version installée / mise à jour disponible (canal GitHub si
		// la classe existe — absente du paquet wp.org, canalisé natif).
		$update = class_exists( 'IMP_Updater' ) ? IMP_Updater::update_available() : null;
		if ( null !== $update ) {
			$links[] = '<strong style="color:#b32d2e;">' . esc_html( sprintf( /* translators: 1: version 2: version */ __( 'v%1$s — update %2$s available', 'infinity-migratex-pro' ), IMP_VERSION, $update['tag'] ) ) . '</strong>';
		} else {
			$links[] = '<span style="color:#22714f;">v' . esc_html( IMP_VERSION ) . ' — ' . esc_html__( 'up to date', 'infinity-migratex-pro' ) . '</span>';
		}

		// 2. Fiche détaillée + historique des versions (modal).
		$details = wp_nonce_url(
			add_query_arg( 'action', 'imp_plugin_details', admin_url( 'admin-post.php' ) ),
			'imp-details'
		);
		$links[] = '<a href="#" class="imp-open-details" data-url="' . esc_url( $details ) . '">' . esc_html__( 'Details & version history', 'infinity-migratex-pro' ) . '</a>';

		// 3. GitHub.
		$links[] = '<a href="https://github.com/derouicheoussama/infinity-migratex-pro" target="_blank" rel="noopener noreferrer">GitHub</a>';

		// 4. Passer Pro (masqué si déjà Pro) — ouvre le tunnel d'achat intégré.
		if ( ! IMP_License::is_pro() ) {
			$links[] = '<a href="' . esc_url( self::pro_url() ) . '" data-imp-checkout="personal" target="_blank" rel="noopener noreferrer" style="color:#8a3be0;font-weight:700;">★ ' . esc_html__( 'Go Pro', 'infinity-migratex-pro' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Charge ThickBox uniquement sur la page des plugins.
	 *
	 * @param string $hook Hook courant.
	 * @return void
	 */
	public static function thickbox_assets( $hook ) {
		if ( 'plugins.php' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
	}

	/**
	 * JS d'ouverture de la fiche détaillée.
	 *
	 * @return void
	 */
	public static function footer_script() {
		?>
		<script>
		(function ($) {
			$(document).on('click', '.imp-open-details', function (e) {
				e.preventDefault();
				tb_show('Infinity MigrateX Pro', $(this).data('url') + '&TB_iframe=true&width=880&height=660');
			});
			/* Le tunnel d'achat Pro est géré par le handler global
			 * [data-imp-checkout] de admin.js — modale 4 étapes intégrée. */
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Bandeau « Passer à Pro » sous la ligne du plugin.
	 *
	 * @param string $plugin_file Fichier du plugin.
	 * @param array  $plugin_data Données du plugin.
	 * @return void
	 */
	public static function pro_banner( $plugin_file, $plugin_data ) {
		if ( IMP_License::is_pro() ) {
			return; // Rien à vendre à quelqu'un qui a déjà Pro.
		}

		static $styles_printed = false;
		if ( ! $styles_printed ) {
			$styles_printed = true;
			?>
			<style>
			tr.imp-pro-row > td { padding: 0 !important; border-left: 4px solid #8a3be0; }
			.imp-pro-banner {
				display: flex; align-items: center; justify-content: space-between;
				gap: 14px; flex-wrap: wrap;
				background: linear-gradient(120deg, #f6f0ff, #ffffff 60%);
				border-bottom: 1px solid #e3d9f5;
				padding: 12px 18px;
			}
			.imp-pro-banner strong { color: #5a2bbf; font-size: 13.5px; }
			.imp-pro-banner .imp-pro-text { color: #3c434a; font-size: 12.5px; line-height: 1.5; margin: 2px 0 0; }
			.imp-pro-banner a.imp-pro-btn {
				display: inline-flex; align-items: center; gap: 6px;
				background: linear-gradient(120deg, #7b2ff7, #a45cff);
				color: #fff; text-decoration: none; font-weight: 700; font-size: 12.5px;
				padding: 8px 16px; border-radius: 8px;
				box-shadow: 0 6px 14px -6px rgba(122, 47, 247, .5);
			}
			.imp-pro-banner a.imp-pro-btn:hover { opacity: .92; }
			</style>
			<?php
		}
		?>
		<tr class="active imp-pro-row">
			<td class="plugin-update colspanchange" colspan="4">
				<div class="imp-pro-banner">
					<div>
						<strong>★ <?php esc_html_e( 'Go Pro with Infinity MigrateX Pro', 'infinity-migratex-pro' ); ?></strong>
						<p class="imp-pro-text"><?php esc_html_e( 'Unlock the Pro roadmap: cloud backup destinations, advanced migration profiles, priority features and priority support — while keeping every free feature forever.', 'infinity-migratex-pro' ); ?></p>
					</div>
					<a class="imp-pro-btn" data-imp-checkout href="<?php echo esc_url( self::pro_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro', 'infinity-migratex-pro' ); ?></a>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Historique des versions parsé depuis readme.txt (source unique).
	 *
	 * @return array[] {version, lines[]}
	 */
	private static function changelog() {
		$readme = @file_get_contents( IMP_DIR . 'readme.txt' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( false === $readme || '' === $readme ) {
			return array();
		}

		if ( ! preg_match( '/== Changelog ==(.*?)== Upgrade Notice ==/s', $readme, $section ) ) {
			return array();
		}

		$entries = array();
		if ( preg_match_all( '/^\s*=\s*([\d.]+)\s*=\s*$(.*?)^(?=\s*=\s*[\d.]+\s*=|\z)/ms', $section[1], $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$lines = array();
				foreach ( preg_split( '/\r\n|\r|\n/', trim( $match[2] ) ) as $line ) {
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					$lines[] = preg_replace( '/^[\*\-]\s*/', '', $line );
				}
				$entries[] = array(
					'version' => $match[1],
					'lines'   => $lines,
				);
			}
		}
		return $entries;
	}

	/**
	 * Page de détails autonome (iframe ThickBox).
	 *
	 * @return void
	 */
	public static function render_details_page() {
		if ( ! current_user_can( 'infinity_migrate_manage' ) && ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'infinity-migratex-pro' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'imp-details' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'infinity-migratex-pro' ) );
		}

		nocache_headers();

		$update      = class_exists( 'IMP_Updater' ) ? IMP_Updater::update_available() : null;
		$entries     = self::changelog();
		$is_pro      = IMP_License::is_pro();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<title>Infinity MigrateX Pro — <?php esc_html_e( 'Details & history', 'infinity-migratex-pro' ); ?></title>
<style>
	body { margin: 0; background: #f3f5fa; color: #1e2a44; font: 13px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
	.wrap { max-width: 820px; margin: 0 auto; padding: 24px 20px 40px; }
	.hero { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #d9dfee; border-radius: 14px; padding: 20px 22px; }
	.hero .mark { width: 62px; height: 62px; border-radius: 16px; background: linear-gradient(120deg, #2f5fe0, #6d3ae8); color: #fff; font-size: 38px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
	.hero h1 { margin: 0 0 2px; font-size: 20px; }
	.hero p { margin: 0; color: #5c688a; }
	.badges { margin-left: auto; text-align: right; }
	.badge { display: inline-block; border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 700; border: 1px solid; margin-left: 6px; }
	.badge.ok { color: #0f7a45; background: #ecf8f1; border-color: #b7e2cb; }
	.badge.upd { color: #b32d2e; background: #fdeeec; border-color: #f2c4c0; }
	.card { background: #fff; border: 1px solid #d9dfee; border-radius: 14px; padding: 18px 22px; margin-top: 16px; }
	.card h2 { margin: 0 0 12px; font-size: 15px; }
	.features { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
	.feature { display: flex; gap: 10px; background: #fafbfe; border: 1px solid #e7ebf5; border-radius: 10px; padding: 11px 13px; font-size: 12.5px; }
	.feature b { display: block; margin-bottom: 2px; }
	.feature span { color: #5c688a; }
	.vers { border-left: 3px solid #c4d3f6; padding: 2px 0 2px 14px; margin: 0 0 16px; }
	.vers h3 { margin: 0 0 6px; font-size: 13.5px; color: #1a3fb8; }
	.vers.current h3 .tag { background: #ecf8f1; color: #0f7a45; border-radius: 6px; padding: 1px 8px; font-size: 11px; font-weight: 700; margin-left: 6px; }
	.vers ul { margin: 0; padding-left: 16px; color: #3c434a; }
	.vers li { margin-bottom: 3px; }
	a { color: #2f5fe0; }
	.cta { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(120deg, #7b2ff7, #a45cff); color: #fff; text-decoration: none; font-weight: 700; padding: 9px 18px; border-radius: 9px; margin-top: 10px; }
	.btn-blue { background: linear-gradient(120deg, #2f5fe0, #4f7cff); }
	.meta-line { color: #5c688a; font-size: 12px; margin-top: 4px; }
</style>
</head>
<body>
<div class="wrap">
	<div class="hero">
		<div class="mark">∞</div>
		<div>
			<h1>Infinity MigrateX Pro</h1>
			<p><?php esc_html_e( 'WordPress Migration, Backup &amp; Deployment Suite', 'infinity-migratex-pro' ); ?> — <?php esc_html_e( 'by Derouiche Oussama · Infinity Coder', 'infinity-migratex-pro' ); ?></p>
			<p class="meta-line">
				<?php
				echo esc_html( sprintf( /* translators: %s: version */ __( 'Installed version: %s', 'infinity-migratex-pro' ), IMP_VERSION ) );
				echo ' · ' . esc_html( sprintf( /* translators: 1: WP version 2: PHP version */ __( 'Requires WordPress %1$s+, PHP %2$s+', 'infinity-migratex-pro' ), '5.8', '7.4' ) );
				?>
			</p>
		</div>
		<div class="badges">
			<?php if ( $update ) : ?>
				<span class="badge upd"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Update %s available', 'infinity-migratex-pro' ), $update['tag'] ) ); ?></span>
			<?php else : ?>
				<span class="badge ok"><?php esc_html_e( 'Up to date', 'infinity-migratex-pro' ); ?></span>
			<?php endif; ?>
			<span class="badge <?php echo $is_pro ? 'ok' : 'upd'; ?>"><?php echo esc_html( $is_pro ? 'PRO' : 'FREE' ); ?></span>
		</div>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'What it does', 'infinity-migratex-pro' ); ?></h2>
		<div class="features">
			<div class="feature"><span><b><?php esc_html_e( 'Migrate & clone', 'infinity-migratex-pro' ); ?></b><?php esc_html_e( 'Domain change with serialized-safe URL rewrite, staging clone, .infinitymigrate packages.', 'infinity-migratex-pro' ); ?></span></div>
			<div class="feature"><span><b><?php esc_html_e( 'Automatic backups', 'infinity-migratex-pro' ); ?></b><?php esc_html_e( 'Full / files / database, scheduled daily-weekly-monthly with retention and SHA-256 checksums.', 'infinity-migratex-pro' ); ?></span></div>
			<div class="feature"><span><b><?php esc_html_e( 'Verified restore', 'infinity-migratex-pro' ); ?></b><?php esc_html_e( 'Integrity checked before anything is written, component selection, optional safety backup.', 'infinity-migratex-pro' ); ?></span></div>
			<div class="feature"><span><b><?php esc_html_e( 'Security scanner', 'infinity-migratex-pro' ); ?></b><?php esc_html_e( 'WordPress.org core checksums, PHP in uploads, suspicious patterns, permissions.', 'infinity-migratex-pro' ); ?></span></div>
			<div class="feature"><span><b><?php esc_html_e( 'Resumable engine', 'infinity-migratex-pro' ); ?></b><?php esc_html_e( 'Chunked operations: no PHP timeout, pause / resume / cancel everywhere, real progress.', 'infinity-migratex-pro' ); ?></span></div>
			<div class="feature"><span><b><?php esc_html_e( 'Database tools', 'infinity-migratex-pro' ); ?></b><?php esc_html_e( 'Tables, export, verified SQL import, optimize & repair — with confirmation.', 'infinity-migratex-pro' ); ?></span></div>
		</div>
		<p style="margin:12px 0 0;">
			<a class="cta btn-blue" href="https://github.com/derouicheoussama/infinity-migratex-pro/releases/latest" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download the latest version', 'infinity-migratex-pro' ); ?></a>
			<?php if ( ! $is_pro ) : ?>
				<a class="cta" href="<?php echo esc_url( self::pro_url() ); ?>" target="_blank" rel="noopener noreferrer">★ <?php esc_html_e( 'Upgrade to Pro', 'infinity-migratex-pro' ); ?></a>
			<?php endif; ?>
		</p>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Version history', 'infinity-migratex-pro' ); ?></h2>
		<?php if ( empty( $entries ) ) : ?>
			<p style="color:#5c688a;"><?php esc_html_e( 'No changelog available.', 'infinity-migratex-pro' ); ?></p>
		<?php else : ?>
			<?php foreach ( $entries as $entry ) : ?>
				<div class="vers<?php echo IMP_VERSION === $entry['version'] ? ' current' : ''; ?>">
					<h3>v<?php echo esc_html( $entry['version'] ); ?><?php if ( IMP_VERSION === $entry['version'] ) : ?><span class="tag"><?php esc_html_e( 'installed', 'infinity-migratex-pro' ); ?></span><?php endif; ?></h3>
					<ul>
						<?php foreach ( $entry['lines'] as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Links', 'infinity-migratex-pro' ); ?></h2>
		<p style="margin:0;">
			<a href="https://www.derouicheoussama.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Author website', 'infinity-migratex-pro' ); ?></a> ·
			<a href="https://github.com/derouicheoussama/infinity-migratex-pro" target="_blank" rel="noopener noreferrer">GitHub</a> ·
			<a href="https://profiles.wordpress.org/derouicheoussama/" target="_blank" rel="noopener noreferrer">WordPress.org</a> ·
			<a href="https://www.instagram.com/derouiche.oussama/" target="_blank" rel="noopener noreferrer">Instagram</a> ·
			<a href="https://www.facebook.com/derouiche.oussama" target="_blank" rel="noopener noreferrer">Facebook</a> ·
			<a href="https://www.tiktok.com/@derouiche.oussama" target="_blank" rel="noopener noreferrer">TikTok</a>
		</p>
	</div>
</div>
</body>
</html>
		<?php
		exit;
	}
}
