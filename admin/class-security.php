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
 * Page Security Center : état réel des protections, actions de
 * sécurisation, résumé du dernier scan, nettoyage des fichiers tmp.
 */
final class IMP_Admin_Security {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'security' );

		IMP_Security::bootstrap_storage();
		$report    = IMP_Security::protection_report();
		$hardening = class_exists( 'IMP_Hardening' ) ? IMP_Hardening::layers_report() : array();
		$watermark = class_exists( 'IMP_Watermark' ) ? IMP_Watermark::verify() : array( 'ok' => true, 'checked' => 0, 'stripped' => array() );
		$last_scan = IMP_Scanner::last_report();
		?>
		<?php if ( ! empty( $watermark['ok'] ) ) : ?>
			<div class="imp-notice imp-notice-success" style="margin-bottom:14px;">
				<strong>∞ Infinity Coder</strong> — <?php
				echo esc_html( sprintf(
					/* translators: %s: file count */
					__( 'Source signature verified on %s code files (author watermarks intact).', 'infinity-migratex-pro' ),
					number_format_i18n( (int) $watermark['checked'] )
				) );
				?>
			</div>
		<?php else : ?>
			<div class="imp-notice imp-notice-warning" style="margin-bottom:14px;">
				<strong>∞ Infinity Coder</strong> — <?php
				echo esc_html( sprintf(
					/* translators: %s: file count */
					__( 'Author watermarks missing in %s file(s): the code may have been stripped or tampered with.', 'infinity-migratex-pro' ),
					count( (array) $watermark['stripped'] )
				) );
				?>
			</div>
		<?php endif; ?>
		<?php
		/* Panneau « Data & token protection » : état réel de la protection
		 * des secrets (chiffrement au repos, non-affichage HTML, exports
		 * sans secrets, logs scrubés, tokens exportés jamais). */
		$protected_settings = imp_settings();
		$secret_map         = array(
			'Google Drive client secret' => 'cloud_drive_client_secret',
			'Google Drive refresh token' => 'cloud_drive_refresh',
			'Dropbox access token'       => 'cloud_dropbox_token',
			'FTP password'               => 'cloud_ftp_pass',
			'Google Sheets account JSON' => 'sheets_json',
		);
		$enc_count  = 0;
		$conf_count = 0;
		foreach ( $secret_map as $label => $key ) {
			if ( empty( $protected_settings[ $key ] ) ) {
				continue;
			}
			$conf_count++;
			if ( 0 === strpos( (string) $protected_settings[ $key ], 'impenc1:' ) ) {
				$enc_count++;
			}
		}
		?>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Data & token protection', 'infinity-migratex-pro' ); ?></h3>
			</div>
			<div class="imp-panel-body">
				<table class="imp-table imp-table-info">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Secrets stored encrypted', 'infinity-migratex-pro' ); ?></th>
							<td><?php
							if ( 0 === $conf_count ) {
								echo IMP_Admin::badge( 'neutral', __( 'No secrets configured', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							} elseif ( $enc_count === $conf_count ) {
								echo IMP_Admin::badge( 'pass', sprintf( /* translators: 1: encrypted 2: total */ __( '%1$d of %2$d encrypted (AES-256-GCM)', 'infinity-migratex-pro' ), $enc_count, $conf_count ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							} else {
								echo IMP_Admin::badge( 'warn', sprintf( /* translators: 1: encrypted 2: total */ __( '%1$d of %2$d encrypted — re-save the others to encrypt', 'infinity-migratex-pro' ), $enc_count, $conf_count ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Secrets in page source', 'infinity-migratex-pro' ); ?></th>
							<td><?php echo esc_html( __( 'Never — secret fields are never printed, even encrypted.', 'infinity-migratex-pro' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Configuration export', 'infinity-migratex-pro' ); ?></th>
							<td><?php echo esc_html( __( 'Secrets excluded from JSON exports.', 'infinity-migratex-pro' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Logs', 'infinity-migratex-pro' ); ?></th>
							<td><?php echo esc_html( __( 'Secret-like keys are redacted automatically.', 'infinity-migratex-pro' ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Hardening layers', 'infinity-migratex-pro' ); ?></h3>
				<?php if ( class_exists( 'IMP_Hardening' ) ) : ?>
				<div class="imp-panel-actions">
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="security-integrity"><?php esc_html_e( 'Verify source code now', 'infinity-migratex-pro' ); ?></button>
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="security-reseal"><?php esc_html_e( 'Re-seal baseline', 'infinity-migratex-pro' ); ?></button>
				</div>
				<?php else : ?>
				<div class="imp-panel-actions"><span class="imp-muted"><?php esc_html_e( 'Hardening module disabled (safe mode).', 'infinity-migratex-pro' ); ?></span></div>
				<?php endif; ?>
			</div>
			<div class="imp-panel-body">
				<?php if ( empty( $hardening ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'The hardening module is not loaded.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
				<table class="imp-table imp-table-list" data-imp-hardening-table>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Layer', 'infinity-migratex-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'infinity-migratex-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Details', 'infinity-migratex-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $hardening as $item ) : ?>
							<tr data-imp-hardening="<?php echo esc_attr( $item['id'] ); ?>">
								<td><strong><?php echo esc_html( $item['label'] ); ?></strong></td>
								<td><?php echo IMP_Admin::badge( $item['ok'] ? 'pass' : 'fail', $item['ok'] ? __( 'ACTIVE', 'infinity-migratex-pro' ) : __( 'ALERT', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								<td><?php echo esc_html( $item['detail'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="imp-hint"><?php esc_html_e( 'Integrity verdicts are re-checked daily and after every official update. Any unexpected modification raises a visible alert and a security journal entry.', 'infinity-migratex-pro' ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Security center', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<button type="button" class="imp-btn imp-btn-primary" data-imp-action="security-resecure"><?php esc_html_e( 'Re-secure storage', 'infinity-migratex-pro' ); ?></button>
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="security-cleanup"><?php esc_html_e( 'Delete temporary files now', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
			<div class="imp-panel-body">
				<table class="imp-table imp-table-list" data-imp-security-table>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Protection', 'infinity-migratex-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'infinity-migratex-pro' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Details', 'infinity-migratex-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $report as $item ) : ?>
							<tr data-imp-protection="<?php echo esc_attr( $item['id'] ); ?>">
								<td><strong><?php echo esc_html( $item['label'] ); ?></strong></td>
								<td><?php echo IMP_Admin::badge( $item['ok'] ? 'pass' : 'warn', $item['ok'] ? __( 'ACTIVE', 'infinity-migratex-pro' ) : __( 'REVIEW', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								<td><?php echo esc_html( $item['detail'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="imp-notice imp-notice-info">
					<strong><?php esc_html_e( 'Delete temporary migration files automatically', 'infinity-migratex-pro' ); ?></strong> —
					<?php
					echo esc_html(
						imp_setting( 'auto_cleanup', 1 )
							? __( 'enabled (daily maintenance purges working files).', 'infinity-migratex-pro' )
							: __( 'disabled — enable it in Settings → General.', 'infinity-migratex-pro' )
					);
					?>
				</div>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Last security scan', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-scanner' ) ); ?>"><?php esc_html_e( 'Scanner page', 'infinity-migratex-pro' ); ?></a>
				</div>
			</div>
			<div class="imp-panel-body">
				<?php if ( null === $last_scan ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No scan performed yet.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<div class="imp-cards imp-cards-3">
						<div class="imp-card imp-card-critical">
							<span class="imp-card-label"><?php esc_html_e( 'Critical', 'infinity-migratex-pro' ); ?></span>
							<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $last_scan['summary']['critical'] ) ); ?></strong>
						</div>
						<div class="imp-card imp-card-warning">
							<span class="imp-card-label"><?php esc_html_e( 'Warnings', 'infinity-migratex-pro' ); ?></span>
							<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $last_scan['summary']['warning'] ) ); ?></strong>
						</div>
						<div class="imp-card imp-card-info">
							<span class="imp-card-label"><?php esc_html_e( 'Informational', 'infinity-migratex-pro' ); ?></span>
							<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $last_scan['summary']['info'] ) ); ?></strong>
						</div>
					</div>
					<p class="imp-muted"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Scanned on %s.', 'infinity-migratex-pro' ), mysql2date( get_option( 'date_format' ) . ' H:i', $last_scan['date'] ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'Capabilities & access control', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<table class="imp-table imp-table-info">
					<tbody>
						<?php
						$roles = array();
						foreach ( wp_roles()->roles as $role_key => $role_info ) {
							$role = get_role( $role_key );
							if ( $role && $role->has_cap( 'infinity_migrate_manage' ) ) {
								$roles[] = $role_info['name'];
							}
						}
						$rows = array(
							array( __( 'Dedicated capabilities', 'infinity-migratex-pro' ), implode( ', ', IMP_Capabilities::all() ) ),
							array( __( 'Roles with access', 'infinity-migratex-pro' ), empty( $roles ) ? __( 'None (administrators by default after activation)', 'infinity-migratex-pro' ) : implode( ', ', $roles ) ),
							array( __( 'Server-side checks', 'infinity-migratex-pro' ), __( 'Every AJAX, download and form action re-verifies nonce + capability — a visible button is never the only barrier.', 'infinity-migratex-pro' ) ),
						);
						foreach ( $rows as $row ) :
							?>
							<tr><th scope="row"><?php echo esc_html( $row[0] ); ?></th><td><?php echo esc_html( $row[1] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : vérification d'intégrité / rescellement du code.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'manage' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		switch ( $do ) {
			case 'integrity':
				$result = IMP_Hardening::verify_baseline();
				wp_send_json_success(
					array(
						'ok'      => (bool) $result['ok'],
						'changed' => count( (array) $result['changed'] ),
						'missing' => count( (array) $result['missing'] ),
						'added'   => count( (array) $result['added'] ),
						'count'   => isset( $result['count'] ) ? (int) $result['count'] : 0,
					)
				);
				break;

			case 'reseal':
				$count = IMP_Hardening::rebuild_baseline();
				wp_send_json_success(
					array(
						/* translators: %d: file count */
						'message' => sprintf( __( 'Baseline re-sealed: %d files hashed (SHA-256).', 'infinity-migratex-pro' ), $count ),
					)
				);
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
