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
 * Page WooCommerce dédiée : statut technique en direct (HPOS, lookup
 * tables, passerelles, webhooks, clés API), outils de maintenance
 * réels, options de migration WooCommerce et vérification de
 * compatibilité — tout ce qui concerne la boutique dans une seule page.
 */
final class IMP_Admin_WooCommerce {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'woocommerce' );

		$wc_active = IMP_Integrations_WooCommerce::is_active();
		$stats     = IMP_Integrations_WooCommerce::data_stats();
		$is_pro    = IMP_License::is_pro();
		?>
		<?php if ( ! $wc_active ) : ?>
			<div class="imp-notice imp-notice-warning">
				<strong>WooCommerce</strong> — <?php esc_html_e( 'not detected on this site. Install and activate WooCommerce, then reload this page: the dedicated tools below unlock automatically.', 'infinity-migratex-pro' ); ?>
			</div>
		<?php endif; ?>

		<div class="imp-cards imp-cards-4">
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Products', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $stats['products'] ) ); ?></strong>
			</div>
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Orders', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $stats['orders'] ) ); ?></strong>
			</div>
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Customers', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $stats['customers'] ) ); ?></strong>
			</div>
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'WC tables', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $stats['tables'] ) ); ?></strong>
			</div>
		</div>

		<?php /* ⚠️ NOTE POUR DEROUICHE : flux Import/Export « depuis → vers » —
		       * export = snapshot DB ou package complet ; import = page
		       * Import drag & drop. L'inventaire détaille ce qui voyage. */
		$inventory = self::inventory();
		?>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Import & Export — from → to', 'infinity-migratex-pro' ); ?></h3>
				<p><?php esc_html_e( 'Move the whole store — catalog, orders, customers and settings — with a clear view of what travels where.', 'infinity-migratex-pro' ); ?></p>
			</div>
			<div class="imp-panel-body">
				<div class="imp-xfer">
					<div class="imp-xfer-card">
						<h4>⬆ <?php esc_html_e( 'Export', 'infinity-migratex-pro' ); ?></h4>
						<p class="imp-xfer-way">
							<span class="imp-xfer-node"><?php esc_html_e( 'This site', 'infinity-migratex-pro' ); ?><br><em class="description" style="font-size:11px;"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></em></span>
							<span class="imp-xfer-arrow">→</span>
							<span class="imp-xfer-node"><?php esc_html_e( 'Export file', 'infinity-migratex-pro' ); ?><br><em class="description" style="font-size:11px;"><?php esc_html_e( 'download & transfer', 'infinity-migratex-pro' ); ?></em></span>
						</p>
						<ul>
							<li><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s products (+ %s variations)', 'infinity-migratex-pro' ), number_format_i18n( $inventory['products'] ), number_format_i18n( $inventory['variations'] ) ) ); ?></li>
							<li><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s orders (%s)', 'infinity-migratex-pro' ), number_format_i18n( $inventory['orders'] ), $inventory['orders_hpos'] ? 'HPOS' : 'posts' ) ); ?></li>
							<li><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s customers', 'infinity-migratex-pro' ), number_format_i18n( $inventory['customers'] ) ) ); ?></li>
							<li><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s coupons · %s webhooks', 'infinity-migratex-pro' ), number_format_i18n( $inventory['coupons'] ), number_format_i18n( $inventory['webhooks'] ) ) ); ?></li>
							<li><?php echo esc_html( sprintf( /* translators: %s: count */ __( '%s WooCommerce tables + settings', 'infinity-migratex-pro' ), number_format_i18n( $inventory['tables'] ) ) ); ?></li>
						</ul>
						<p style="display:flex;gap:10px;flex-wrap:wrap;margin:0;">
							<button type="button" class="imp-btn imp-btn-primary" data-imp-action="wc-export-snapshot">🗄 <?php esc_html_e( 'Export database snapshot', 'infinity-migratex-pro' ); ?></button>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="wc-export-package">📦 <?php esc_html_e( 'Export full site package', 'infinity-migratex-pro' ); ?></button>
						</p>
						<p class="description" style="margin:10px 0 0;"><?php esc_html_e( 'Snapshot = fast SQL backup (all tables, WooCommerce included). Package = complete .infinitymigrate (files + database), restorable anywhere via drag & drop.', 'infinity-migratex-pro' ); ?></p>
					</div>
					<div class="imp-xfer-card">
						<h4>⬇ <?php esc_html_e( 'Import', 'infinity-migratex-pro' ); ?></h4>
						<p class="imp-xfer-way">
							<span class="imp-xfer-node"><?php esc_html_e( 'Package', 'infinity-migratex-pro' ); ?><br><em class="description" style="font-size:11px;"><?php esc_html_e( '.infinitymigrate / zip', 'infinity-migratex-pro' ); ?></em></span>
							<span class="imp-xfer-arrow">→</span>
							<span class="imp-xfer-node"><?php esc_html_e( 'This site', 'infinity-migratex-pro' ); ?><br><em class="description" style="font-size:11px;"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></em></span>
						</p>
						<ul>
							<li><?php esc_html_e( 'Drag & drop the package on the Import page', 'infinity-migratex-pro' ); ?></li>
							<li><?php esc_html_e( 'Live verification: signature, manifest, checksums', 'infinity-migratex-pro' ); ?></li>
							<li><?php esc_html_e( 'URLs rewritten to this site, serialized-safe', 'infinity-migratex-pro' ); ?></li>
							<li><?php esc_html_e( 'Ephemeral WC tables (sessions, scheduler) excluded automatically', 'infinity-migratex-pro' ); ?></li>
							<li><?php esc_html_e( 'Optional maintenance mode during import', 'infinity-migratex-pro' ); ?></li>
						</ul>
						<a class="imp-btn imp-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-import' ) ); ?>">⬇ <?php esc_html_e( 'Open the Import page', 'infinity-migratex-pro' ); ?></a>
						<p class="description" style="margin:10px 0 0;"><?php esc_html_e( 'Imports run in the resumable engine — no timeout, pause / resume at any step.', 'infinity-migratex-pro' ); ?></p>
					</div>
				</div>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Compatibility check — verified live', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-integrations' ) ); ?>"><?php esc_html_e( 'Integrations overview', 'infinity-migratex-pro' ); ?></a>
				</div>
			</div>
			<div class="imp-panel-body">
				<table class="imp-table imp-table-list">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Check', 'infinity-migratex-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'infinity-migratex-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Details', 'infinity-migratex-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( IMP_Integrations_WooCommerce::compatibility_report() as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
								<td><?php echo IMP_Admin::badge( $row['ok'] ? 'pass' : 'warn', $row['ok'] ? __( 'PASS', 'infinity-migratex-pro' ) : __( 'REVIEW', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								<td><?php echo esc_html( $row['detail'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="imp-notice imp-notice-success" style="margin:14px 0 0;">
					<strong><?php esc_html_e( '100% compatible:', 'infinity-migratex-pro' ); ?></strong>
					<?php esc_html_e( 'products, orders (classic & HPOS), customers, attributes, settings, shipping and payment gateways all migrate through the same checksummed, resumable engine — serialized-safe URL replacement keeps every storefront link and webhook delivery URL intact.', 'infinity-migratex-pro' ); ?>
				</div>
			</div>
		</section>

		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'WooCommerce maintenance tools', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<div class="imp-tools-list">
						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Clear WooCommerce transients', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Deletes expired/weekly cached calculations (_transient_wc_*).', 'infinity-migratex-pro' ); ?></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="wc-tool" data-tool="transients" <?php disabled( ! $wc_active ); ?>><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>
						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Clear customer sessions', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Truncates wp_woocommerce_sessions (carts in progress are lost).', 'infinity-migratex-pro' ); ?> <span class="imp-edition imp-edition-pro">PRO</span></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="wc-tool" data-tool="sessions" data-imp-confirm="delete" <?php disabled( ! $wc_active || ! $is_pro ); ?>><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>
						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Regenerate product lookup tables', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Rebuilds wc_product_*_lookup after a migration (prices, stock, attributes).', 'infinity-migratex-pro' ); ?> <span class="imp-edition imp-edition-pro">PRO</span></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="wc-tool" data-tool="lookup" <?php disabled( ! $wc_active || ! $is_pro ); ?>><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>
						<div class="imp-tool">
							<div>
								<strong><?php esc_html_e( 'Recount product terms', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Fixes category/stock counts shown in the storefront filters.', 'infinity-migratex-pro' ); ?> <span class="imp-edition imp-edition-pro">PRO</span></em>
							</div>
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="wc-tool" data-tool="terms" <?php disabled( ! $wc_active || ! $is_pro ); ?>><?php esc_html_e( 'Run', 'infinity-migratex-pro' ); ?></button>
						</div>
					</div>
					<?php if ( ! $is_pro ) : ?>
						<div class="imp-notice imp-notice-info" style="margin:14px 0 0;">
							★ <?php esc_html_e( 'Sessions, lookup regeneration and term recount are Pro tools.', 'infinity-migratex-pro' ); ?>
							<a href="<?php echo esc_url( IMP_License::checkout_url() ); ?>" target="_blank" rel="noopener noreferrer">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Migration options for WooCommerce', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<p class="imp-lead"><?php esc_html_e( 'These options apply to new backups and dumps. They make WooCommerce backups lighter by excluding ephemeral data — orders, products and customers are always included.', 'infinity-migratex-pro' ); ?></p>
					<label class="imp-check">
						<input type="checkbox" data-imp-wc-option="wc_skip_sessions" <?php checked( ! empty( imp_setting( 'wc_skip_sessions', 1 ) ) ); ?>>
						<?php esc_html_e( 'Exclude customer sessions table from dumps (recommended)', 'infinity-migratex-pro' ); ?>
					</label>
					<label class="imp-check">
						<input type="checkbox" data-imp-wc-option="wc_skip_scheduler" <?php checked( ! empty( imp_setting( 'wc_skip_scheduler', 1 ) ) ); ?>>
						<?php esc_html_e( 'Exclude Action Scheduler logs from dumps (recommended — those tables can weigh hundreds of MB)', 'infinity-migratex-pro' ); ?>
					</label>
					<p class="imp-hint"><?php esc_html_e( 'Saved instantly. The exclusion applies at dump time: sessions and scheduler logs stay on this server.', 'infinity-migratex-pro' ); ?></p>
				</div>
			</section>
		</div>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * Inventaire de transfert (caché 10 min) : complète data_stats avec
	 * variantes, coupons, webhooks et le mode de stockage des commandes.
	 *
	 * @return array
	 */
	private static function inventory() {
		$cached = get_transient( 'imp_wc_inventory' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$stats = IMP_Integrations_WooCommerce::data_stats();

		$stats['variations'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation'"
		);
		$stats['coupons'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status != 'trash'"
		);

		// Webhooks : table dédiée (WC récent) sinon posts classiques.
		$webhooks_table = $wpdb->prefix . 'wc_webhooks';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $webhooks_table ) ) === $webhooks_table ) {
			$stats['webhooks'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$webhooks_table}" );
		} else {
			$stats['webhooks'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_webhook'"
			);
		}

		// Mode de stockage des commandes (HPOS ?).
		$orders_table = $wpdb->prefix . 'wc_orders';
		$stats['orders_hpos'] = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table );

		set_transient( 'imp_wc_inventory', $stats, 10 * MINUTE_IN_SECONDS );
		return $stats;
	}

	/**
	 * AJAX : outils et options WooCommerce.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'manage' );

		if ( ! IMP_Integrations_WooCommerce::is_active() ) {
			wp_send_json_error( array( 'code' => 'IMP-224', 'message' => __( 'WooCommerce is not active on this site.', 'infinity-migratex-pro' ) ), 422 );
		}

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		// Export depuis→vers : snapshot DB rapide ou package complet —
		// le job tourne dans le moteur reprise-sur-interruption standard.
		if ( 'export_snapshot' === $do || 'export_package' === $do ) {
			$full   = 'export_package' === $do;
			$result = IMP_Job::start(
				'backup',
				array(
					'name'       => $full
						? __( 'WooCommerce export — full package', 'infinity-migratex-pro' )
						: __( 'WooCommerce export — database snapshot', 'infinity-migratex-pro' ),
					'components' => $full ? 'full' : 'database',
					'package'    => $full ? 1 : 0,
				)
			);
			if ( ! $result['ok'] ) {
				wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 409 );
			}
			wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
		}

		switch ( $do ) {
			case 'tool':
				$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';

				if ( in_array( $tool, array( 'sessions', 'lookup', 'terms' ), true ) && ! IMP_License::is_pro() ) {
					wp_send_json_error( array( 'code' => 'IMP-241', 'message' => IMP_Job::error_text( 'IMP-241' ) ), 402 );
				}

				switch ( $tool ) {
					case 'transients':
						$count = IMP_Integrations_WooCommerce::clear_transients();
						wp_send_json_success( array( 'message' => sprintf( /* translators: %d: count */ __( '%d WooCommerce transients deleted.', 'infinity-migratex-pro' ), $count ) ) );
						break;

					case 'sessions':
						$count = IMP_Integrations_WooCommerce::clear_sessions();
						wp_send_json_success( array( 'message' => sprintf( /* translators: %d: rows */ __( '%d customer sessions cleared.', 'infinity-migratex-pro' ), $count ) ) );
						break;

					case 'lookup':
						$result = IMP_Integrations_WooCommerce::regenerate_lookup_tables();
						if ( ! $result['ok'] ) {
							wp_send_json_error( array( 'code' => 'IMP-240', 'message' => $result['message'] ), 422 );
						}
						wp_send_json_success( array( 'message' => $result['message'] ) );
						break;

					case 'terms':
						if ( ! IMP_Integrations_WooCommerce::recount_terms() ) {
							wp_send_json_error( array( 'code' => 'IMP-240', 'message' => __( 'Term recount is unavailable in this WooCommerce version.', 'infinity-migratex-pro' ) ), 422 );
						}
						wp_send_json_success( array( 'message' => __( 'Product terms recounted.', 'infinity-migratex-pro' ) ) );
						break;
				}
				break;

			case 'option':
				$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( (string) $_POST['key'] ) ) : '';
				if ( ! in_array( $key, array( 'wc_skip_sessions', 'wc_skip_scheduler' ), true ) ) {
					wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
				}
				$value          = empty( $_POST['value'] ) ? 0 : 1;
				$settings       = imp_settings();
				$settings[ $key ] = $value;
				update_option( 'imp_settings', $settings, false );
				IMP_Plugin::flush_settings();
				wp_send_json_success( array( 'message' => __( 'Setting saved.', 'infinity-migratex-pro' ), 'value' => $value ) );
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
