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
 * Page Tools : outils réels — nettoyage tmp, caches, rewrite, permissions,
 * infos système, export diagnostics, mode diagnostic System Check.
 */
final class IMP_Admin_Tools {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'tools' );

		$diagnostics_url = wp_nonce_url( add_query_arg( 'action', 'imp_download_diagnostics', admin_url( 'admin-post.php' ) ), 'imp-download' );
		?>
		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Maintenance tools', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<div class="imp-tools-list">
						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Cleanup temporary files', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Delete working files (indexes, partial dumps) from the protected storage.', 'infinity-migratex-pro' ); ?></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="tool-cleanup"><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>

						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Clear cache', 'infinity-migratex-pro' ); ?></strong>
								<em id="imp-cached-plugins">
									<?php
									$caches = IMP_Integrations_WordPress::active_cache_plugins();
									echo esc_html( empty( $caches ) ? __( 'No cache plugin detected — clears the plugin caches only.', 'infinity-migratex-pro' ) : implode( ', ', $caches ) );
									?>
								</em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="tool-clearcache"><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>

						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Flush rewrite rules', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Regenerate WordPress permalinks rules (use after a restore or domain change).', 'infinity-migratex-pro' ); ?></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="tool-flush"><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>

						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Check permissions', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Verify that key directories are writable (root, wp-content, uploads, storage).', 'infinity-migratex-pro' ); ?></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="tool-permissions"><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>

						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Clear Elementor CSS', 'infinity-migratex-pro' ); ?></strong>
								<em><?php echo esc_html( IMP_Integrations_Elementor::is_active() ? sprintf( /* translators: %s: version */ __( 'Elementor %s detected.', 'infinity-migratex-pro' ), IMP_Integrations_Elementor::version() ) : __( 'Elementor not active.', 'infinity-migratex-pro' ) ); ?></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="tool-elementor" <?php disabled( ! IMP_Integrations_Elementor::is_active() ); ?>><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>
					</div>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'System information', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<?php $report = IMP_Integrations_WordPress::system_report(); ?>
					<table class="imp-table imp-table-info">
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'WordPress', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['site']['wordpress'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'PHP', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['server']['php'] . ' (' . $report['server']['sapi'] . ')' ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Database', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['database']['server'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Web server', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['server']['software'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Memory limit', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['server']['memory'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Upload max', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['server']['upload_max'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Max execution', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['server']['max_execution'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Site', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( $report['site']['files'] . ' ' . __( 'files', 'infinity-migratex-pro' ) . ' · ' . imp_format_bytes( $report['site']['size'] ) ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Storage', 'infinity-migratex-pro' ); ?></th><td><code><?php echo esc_html( IMP_Plugin::storage_dir() ); ?></code></td></tr>
						</tbody>
					</table>
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( $diagnostics_url ); ?>"><?php esc_html_e( 'Export diagnostics (JSON)', 'infinity-migratex-pro' ); ?></a>
				</div>
			</section>
		</div>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Diagnostic mode — System Check', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<button type="button" class="imp-btn imp-btn-primary" data-imp-action="system-check"><?php esc_html_e( 'Run full system check', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
			<div class="imp-panel-body">
				<p class="imp-muted"><?php esc_html_e( 'Runs real checks: PHP, WordPress, database, filesystem, permissions, disk space, memory, uploads and cron — then produces an exportable report.', 'infinity-migratex-pro' ); ?></p>
				<div data-imp-systemcheck hidden>
					<table class="imp-table imp-table-health"><tbody data-imp-systemcheck-body></tbody></table>
				</div>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : actions outils + system check.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'manage' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		switch ( $do ) {
			case 'cleanup':
				$result = IMP_Security::cleanup_tmp( true );
				wp_send_json_success(
					array(
						/* translators: 1: entries 2: bytes */
						'message' => sprintf( __( '%1$d temporary entries deleted (%2$s freed).', 'infinity-migratex-pro' ), $result['deleted'], imp_format_bytes( $result['bytes'] ) ),
					)
				);
				break;

			case 'clearcache':
				$done = IMP_Integrations_WordPress::clear_known_caches();
				wp_send_json_success(
					array(
						'message' => empty( $done )
							? __( 'No external cache plugin detected — plugin caches cleared.', 'infinity-migratex-pro' )
							: implode( ' · ', $done ),
					)
				);
				break;

			case 'flush':
				IMP_Integrations_WordPress::flush_rewrites();
				wp_send_json_success( array( 'message' => __( 'Rewrite rules flushed and regenerated.', 'infinity-migratex-pro' ) ) );
				break;

			case 'permissions':
				$paths = array(
					__( 'Site root', 'infinity-migratex-pro' )   => ABSPATH,
					__( 'wp-content', 'infinity-migratex-pro' )  => WP_CONTENT_DIR,
					__( 'Uploads', 'infinity-migratex-pro' )     => wp_get_upload_dir()['basedir'],
					__( 'Plugin storage', 'infinity-migratex-pro' ) => IMP_Plugin::storage_dir(),
				);
				$results = array();
				foreach ( $paths as $label => $path ) {
					$exists    = is_dir( $path );
					$writable  = $exists && wp_is_writable( $path );
					$results[] = array(
						'label' => $label,
						'path'  => $path,
						'ok'    => $writable,
						/* translators: %s: path */
						'msg'   => ! $exists ? sprintf( __( '%s: missing', 'infinity-migratex-pro' ), $path ) : ( $writable ? sprintf( __( '%s: writable', 'infinity-migratex-pro' ), $path ) : sprintf( __( '%s: NOT writable', 'infinity-migratex-pro' ), $path ) ),
					);
				}
				wp_send_json_success( array( 'results' => $results ) );
				break;

			case 'elementor':
				$result = IMP_Integrations_Elementor::regenerate();
				if ( ! $result['ok'] ) {
					wp_send_json_error( array( 'code' => 'IMP-240', 'message' => $result['message'] ), 422 );
				}
				wp_send_json_success( array( 'message' => $result['message'] ) );
				break;

			case 'resecure':
				IMP_Security::bootstrap_storage();
				wp_send_json_success(
					array(
						'message' => IMP_Security::storage_protected()
							? __( 'Storage guards rewritten: .htaccess denies all web access, index files in place.', 'infinity-migratex-pro' )
							: __( 'Storage recreated — on nginx servers, deny access to the storage folder in your vhost config.', 'infinity-migratex-pro' ),
						'report' => IMP_Security::protection_report(),
					)
				);
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}

	/**
	 * AJAX : diagnostic complet exportable.
	 */
	public static function ajax_system_check() {
		IMP_Security::ajax_guard( 'manage' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		delete_transient( 'imp_site_stats' );
		$checks = IMP_Compatibility::health_checks();

		wp_send_json_success(
			array(
				'checks'  => $checks,
				'overall' => IMP_Compatibility::overall_status( $checks ),
				'date'    => current_time( 'mysql' ),
			)
		);
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
