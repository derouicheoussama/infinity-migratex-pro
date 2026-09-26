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
 * Page Integrations : détection réelle WooCommerce / Elementor / caches,
 * compatibilité de migration, actions post-migration.
 */
final class IMP_Admin_Integrations {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'integrations' );

		$wc_active  = IMP_Integrations_WooCommerce::is_active();
		$el_active  = IMP_Integrations_Elementor::is_active();
		$wc_stats   = IMP_Integrations_WooCommerce::data_stats();
		$el_stats   = IMP_Integrations_Elementor::data_stats();
		$caches     = IMP_Integrations_WordPress::active_cache_plugins();
		?>
		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head">
					<h3>WooCommerce</h3>
					<?php echo $wc_active ? IMP_Admin::badge( 'pass', __( 'Detected', 'infinity-migratex-pro' ) ) : IMP_Admin::badge( 'neutral', __( 'Not installed', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="imp-panel-body">
					<?php if ( $wc_active ) : ?>
						<p class="imp-lead"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'WooCommerce %s detected. Migration compatibility:', 'infinity-migratex-pro' ), IMP_Integrations_WooCommerce::version() ) ); ?></p>
						<ul class="imp-compat">
							<?php foreach ( IMP_Integrations_WooCommerce::migration_compat() as $item ) : ?>
								<li class="is-ok"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php echo esc_html( $item['label'] ); ?></li>
							<?php endforeach; ?>
						</ul>
						<table class="imp-table imp-table-info">
							<tbody>
								<tr><th scope="row"><?php esc_html_e( 'Products', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $wc_stats['products'] ) ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Orders', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $wc_stats['orders'] ) ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Customers', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $wc_stats['customers'] ) ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Dedicated tables', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $wc_stats['tables'] ) ); ?></td></tr>
							</tbody>
						</table>
						<div class="imp-panel-actions">
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="integration-wc-transients"><?php esc_html_e( 'Clear WooCommerce transients', 'infinity-migratex-pro' ); ?></button>
						</div>
						<p class="imp-hint"><?php esc_html_e( 'Orders and customer data are migrated as regular database tables — serialized-safe URL replacement keeps them intact.', 'infinity-migratex-pro' ); ?></p>
					<?php else : ?>
						<p class="imp-muted"><?php esc_html_e( 'WooCommerce is not active. If you install it later, migrations will cover its data automatically.', 'infinity-migratex-pro' ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head">
					<h3>Elementor</h3>
					<?php echo $el_active ? IMP_Admin::badge( 'pass', __( 'Detected', 'infinity-migratex-pro' ) ) : IMP_Admin::badge( 'neutral', __( 'Not installed', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
				<div class="imp-panel-body">
					<?php if ( $el_active ) : ?>
						<p class="imp-lead"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Elementor %s detected.', 'infinity-migratex-pro' ), IMP_Integrations_Elementor::version() ) ); ?></p>
						<ul class="imp-compat">
							<?php foreach ( IMP_Integrations_Elementor::migration_compat() as $item ) : ?>
								<li class="is-ok"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php echo esc_html( $item['label'] ); ?></li>
							<?php endforeach; ?>
						</ul>
						<table class="imp-table imp-table-info">
							<tbody>
								<tr><th scope="row"><?php esc_html_e( 'Elementor pages', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $el_stats['documents'] ) ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Saved templates', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $el_stats['templates'] ) ); ?></td></tr>
							</tbody>
						</table>
						<div class="imp-panel-actions">
							<button type="button" class="imp-btn imp-btn-primary" data-imp-action="integration-elementor-regen"><?php esc_html_e( 'Regenerate Elementor CSS & Data', 'infinity-migratex-pro' ); ?></button>
						</div>
						<p class="imp-hint"><?php esc_html_e( 'After a migration or restore, regenerate the CSS once: Elementor rebuilds its styles for the new URLs automatically.', 'infinity-migratex-pro' ); ?></p>
					<?php else : ?>
						<p class="imp-muted"><?php esc_html_e( 'Elementor is not active on this site.', 'infinity-migratex-pro' ); ?></p>
					<?php endif; ?>
				</div>
			</section>
		</div>

		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'Cache systems', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<?php if ( empty( $caches ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No known cache plugin detected. WordPress object cache transients of this plugin are cleared automatically after migrations.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Detected cache systems:', 'infinity-migratex-pro' ); ?> <strong><?php echo esc_html( implode( ', ', $caches ) ); ?></strong></p>
					<div class="imp-panel-actions">
						<button type="button" class="imp-btn imp-btn-primary" data-imp-action="integration-clear-cache"><?php esc_html_e( 'Clear Cache', 'infinity-migratex-pro' ); ?></button>
						<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="integration-flush"><?php esc_html_e( 'Flush WordPress Rewrite Rules', 'infinity-migratex-pro' ); ?></button>
					</div>
					<p class="imp-hint"><?php esc_html_e( 'Clear caches right after a domain migration or a restore so visitors get the new URLs immediately.', 'infinity-migratex-pro' ); ?></p>
				<?php endif; ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : actions intégrations.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'manage' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		switch ( $do ) {
			case 'elementor_regen':
				$result = IMP_Integrations_Elementor::regenerate();
				if ( ! $result['ok'] ) {
					wp_send_json_error( array( 'code' => 'IMP-240', 'message' => $result['message'] ), 422 );
				}
				wp_send_json_success( array( 'message' => $result['message'] ) );
				break;

			case 'wc_transients':
				$count = IMP_Integrations_WooCommerce::clear_transients();
				wp_send_json_success(
					array(
						/* translators: %d: transients count */
						'message' => sprintf( __( '%d WooCommerce transients deleted.', 'infinity-migratex-pro' ), $count ),
					)
				);
				break;

			case 'clear_cache':
				$done = IMP_Integrations_WordPress::clear_known_caches();
				wp_send_json_success(
					array(
						'message' => empty( $done ) ? __( 'No cache plugin action needed.', 'infinity-migratex-pro' ) : implode( ' · ', $done ),
					)
				);
				break;

			case 'flush':
				IMP_Integrations_WordPress::flush_rewrites();
				wp_send_json_success( array( 'message' => __( 'Rewrite rules flushed.', 'infinity-migratex-pro' ) ) );
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
