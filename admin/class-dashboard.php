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
 * Page Dashboard : informations réelles du site, statistiques d'opérations,
 * santé système, actions rapides, activité récente.
 */
final class IMP_Admin_Dashboard {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'dashboard' );

		$stats  = IMP_Site_Stats::get();
		$log    = IMP_Logger::dashboard_stats();
		$health = IMP_Compatibility::health_checks();

		$backups_all  = IMP_Backup_Engine::all();
		$backup_count = count( $backups_all );
		$package_count = count( IMP_Package::all() );
		$job           = IMP_Job::public_status();
		$last_backup   = $backups_all[0] ?? null;

		// Rappels intelligents (données réelles, cadences réglables).
		$settings   = imp_settings();
		$reminders  = array();
		$last_scan  = IMP_Scanner::last_report();
		$backup_reminder_days = max( 0, (int) imp_setting( 'backup_reminder_days', 14 ) );
		$scan_reminder_days   = max( 0, (int) imp_setting( 'scan_reminder_days', 30 ) );

		if ( empty( $settings['backup_schedule_enabled'] ) || ! IMP_License::is_pro() ) {
			if ( $backup_count === 0 ) {
				$reminders[] = array(
					'msg'  => __( 'No backup yet — create your first full backup before any migration.', 'infinity-migratex-pro' ),
					'url'  => admin_url( 'admin.php?page=infinity-migratex-pro-backups' ),
					'cta'  => __( 'Create backup', 'infinity-migratex-pro' ),
				);
			} elseif ( $backup_reminder_days > 0 && null !== $last_backup
				&& ( time() - (int) $last_backup['created_ts'] ) > $backup_reminder_days * DAY_IN_SECONDS ) {
				$age_days = (int) ceil( ( time() - (int) $last_backup['created_ts'] ) / DAY_IN_SECONDS );
				$reminders[] = array(
					'msg'  => sprintf(
						/* translators: 1: days 2: date */
						__( 'The last backup is %1$s days old (%2$s) — schedule automatic backups or run one now.', 'infinity-migratex-pro' ),
						$age_days,
						mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $last_backup['created_ts'] ) )
					),
					'url'  => admin_url( 'admin.php?page=infinity-migratex-pro-backups' ),
					'cta'  => __( 'Back up now', 'infinity-migratex-pro' ),
				);
			}
		}
		if ( $scan_reminder_days > 0
			&& ( empty( $last_scan ) || ( time() - strtotime( (string) $last_scan['date'] ) ) > $scan_reminder_days * DAY_IN_SECONDS ) ) {
			$reminders[] = array(
				'msg'  => sprintf(
					/* translators: %s: days */
					__( 'No security scan in the last %s days.', 'infinity-migratex-pro' ),
					(int) $scan_reminder_days
				),
				'url'  => admin_url( 'admin.php?page=infinity-migratex-pro-scanner' ),
				'cta'  => __( 'Scan now', 'infinity-migratex-pro' ),
			);
		}
		?>
		<?php if ( 'running' !== $job['status'] ) : ?>
		<section class="imp-hero-status imp-hero-<?php echo esc_attr( $health ); ?>">
			<div class="imp-hero-status-main">
				<span class="dashicons <?php echo esc_attr( 'error' === $health ? 'dashicons-warning' : 'dashicons-yes-alt' ); ?>" aria-hidden="true"></span>
				<div>
					<strong><?php echo esc_html( 'error' === $health ? __( 'Action needed', 'infinity-migratex-pro' ) : ( 'warning' === $health ? __( 'Almost ready', 'infinity-migratex-pro' ) : __( 'Your site is ready to migrate', 'infinity-migratex-pro' ) ) ); ?></strong>
					<p><?php
					echo esc_html( sprintf(
						/* translators: 1: backups count 2: site size */
						__( '%1$s backups on file · site size %2$s · %3$s active plugins.', 'infinity-migratex-pro' ),
						number_format_i18n( $backup_count ),
						imp_format_bytes( $stats['bytes'] ),
						number_format_i18n( count( (array) get_option( 'active_plugins', array() ) ) )
					) );
					?></p>
				</div>
			</div>
			<div class="imp-hero-status-actions">
				<a class="imp-btn imp-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-backups' ) ); ?>"><?php esc_html_e( 'Create backup', 'infinity-migratex-pro' ); ?></a>
				<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-migration' ) ); ?>"><?php esc_html_e( 'Start migration', 'infinity-migratex-pro' ); ?></a>
			</div>
		</section>
		<?php endif; ?>

		<?php /* ⚠️ NOTE POUR DEROUICHE : bannière d'édition du dashboard —
		       * PRO = vitrine dorée avec la licence ; FREE = upsell majeur
		       * avec les gains concrets et ouverture du tunnel d'achat. */
		if ( IMP_License::is_pro() ) :
			$dash_license = IMP_License::get();
			$dash_trial   = IMP_License::days_left();
			$dash_until   = (int) $dash_license['expires'] > 0
				? mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $dash_license['expires'] ) )
				: '';
			?>
		<section class="imp-edition-banner imp-edition-banner-pro">
			<span class="imp-edition-banner-star" aria-hidden="true">★</span>
			<div class="imp-edition-banner-main">
				<strong><?php
				echo null !== $dash_trial
					? esc_html( sprintf( /* translators: %s: days */ __( 'PRO trial active — %s days left', 'infinity-migratex-pro' ), number_format_i18n( $dash_trial ) ) )
					: esc_html__( 'PRO edition active', 'infinity-migratex-pro' );
				?></strong>
				<p><?php
				echo esc_html( sprintf(
					/* translators: 1: plan */
					__( 'Plan: %1$s · Cloud destinations, scheduled backups, AES-256 encryption, turbo speed and priority support unlocked.', 'infinity-migratex-pro' ),
					(string) $dash_license['plan']
				) );
				?></p>
			</div>
			<div class="imp-edition-banner-side">
				<?php if ( null !== $dash_trial ) : ?>
					<button type="button" class="imp-btn imp-btn-small imp-btn-primary" data-imp-checkout="personal"><?php esc_html_e( 'Keep PRO — upgrade now', 'infinity-migratex-pro' ); ?></button>
				<?php else : ?>
					<?php if ( '' !== $dash_until ) : ?>
						<span class="imp-edition-valid"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Valid until %s', 'infinity-migratex-pro' ), $dash_until ) ); ?></span>
					<?php else : ?>
						<span class="imp-edition-valid"><?php esc_html_e( 'Lifetime license', 'infinity-migratex-pro' ); ?></span>
					<?php endif; ?>
					<a class="imp-btn imp-btn-small imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-settings' ) ); ?>#advanced"><?php esc_html_e( 'Manage license', 'infinity-migratex-pro' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
		<?php else : ?>
		<section class="imp-edition-banner imp-edition-banner-free">
			<span class="imp-edition-banner-star" aria-hidden="true">★</span>
			<div class="imp-edition-banner-main">
				<strong><?php esc_html_e( 'You are on the FREE edition — unlock the full suite', 'infinity-migratex-pro' ); ?></strong>
				<p><?php esc_html_e( 'Cloud backups (Google Drive, Dropbox, FTP) · scheduled backups with e-mail alerts · AES-256 archive encryption · turbo speed · priority support.', 'infinity-migratex-pro' ); ?></p>
			</div>
			<div class="imp-edition-banner-side">
				<button type="button" class="imp-btn imp-btn-primary" data-imp-checkout="personal"><?php esc_html_e( 'Upgrade to PRO', 'infinity-migratex-pro' ); ?></button>
				<span class="imp-edition-note"><?php esc_html_e( 'from 39€/year — 14-day money-back', 'infinity-migratex-pro' ); ?></span>
			</div>
		</section>
		<?php endif; ?>

		<div class="imp-cards imp-cards-4">
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Site size', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value" data-imp-count="<?php echo esc_attr( $stats['files'] ); ?>">0</strong>
				<span class="imp-card-sub"><?php echo esc_html( sprintf( /* translators: %s: files */ _n( '%s file', '%s files', $stats['files'], 'infinity-migratex-pro' ), number_format_i18n( $stats['files'] ) ) ); ?></span>
			</div>
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Database', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value" data-imp-count="<?php echo esc_attr( $stats['db_rows'] ); ?>">0</strong>
				<span class="imp-card-sub"><?php echo esc_html( sprintf( /* translators: 1: tables 2: rows */ __( '%1$s tables · %2$s rows', 'infinity-migratex-pro' ), number_format_i18n( $stats['db_tables'] ), number_format_i18n( $stats['db_rows'] ) ) ); ?></span>
			</div>
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Backups', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $backup_count ) ); ?></strong>
				<span class="imp-card-sub"><?php echo esc_html( sprintf( /* translators: %s: packages */ _n( '%s package', '%s packages', $package_count, 'infinity-migratex-pro' ), number_format_i18n( $package_count ) ) ); ?></span>
			</div>
			<div class="imp-card">
				<span class="imp-card-label"><?php esc_html_e( 'Migrations', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $log['migrations'] ) ); ?></strong>
				<span class="imp-card-sub"><?php echo esc_html( sprintf( /* translators: 1: backups 2: restores */ __( '%1$s backups · %2$s restores', 'infinity-migratex-pro' ), number_format_i18n( $log['backups'] ), number_format_i18n( $log['restores'] ) ) ); ?></span>
			</div>
		</div>

		<?php if ( 'running' === $job['status'] ) : ?>
			<div class="imp-panel imp-panel-accent" data-imp-jobbox="resumed">
				<div class="imp-panel-body">
					<p><strong><?php esc_html_e( 'An operation is in progress.', 'infinity-migratex-pro' ); ?></strong>
					<?php esc_html_e( 'It is paused — click Resume to continue where it stopped.', 'infinity-migratex-pro' ); ?></p>
					<p id="imp-resumed-job-message" class="imp-muted"></p>
				</div>
				<div class="imp-panel-actions">
					<button type="button" class="imp-btn imp-btn-primary" data-imp-action="job-resume"><?php esc_html_e( 'Resume operation', 'infinity-migratex-pro' ); ?></button>
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="job-cancel"><?php esc_html_e( 'Cancel', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
		<?php endif; ?>

		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Quick actions', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body imp-quickactions">
					<a class="imp-quickaction" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-migration' ) ); ?>">
						<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Start Migration', 'infinity-migratex-pro' ); ?></strong>
						<em><?php esc_html_e( 'Domain, folder, export package', 'infinity-migratex-pro' ); ?></em>
					</a>
					<button type="button" class="imp-quickaction" data-imp-action="quick-backup">
						<span class="dashicons dashicons-database-export" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Create Backup', 'infinity-migratex-pro' ); ?></strong>
						<em><?php esc_html_e( 'Full site, files or database', 'infinity-migratex-pro' ); ?></em>
					</button>
					<a class="imp-quickaction" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-restore' ) ); ?>">
						<span class="dashicons dashicons-database-import" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Restore', 'infinity-migratex-pro' ); ?></strong>
						<em><?php esc_html_e( 'Guided restore from a backup', 'infinity-migratex-pro' ); ?></em>
					</a>
					<button type="button" class="imp-quickaction" data-imp-action="quick-scan">
						<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Scan Site', 'infinity-migratex-pro' ); ?></strong>
						<em><?php esc_html_e( 'Core checksums and file analysis', 'infinity-migratex-pro' ); ?></em>
					</button>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Site information', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<table class="imp-table imp-table-info">
						<tbody>
							<?php
							global $wp_version;
							$rows = array(
								array( __( 'WordPress version', 'infinity-migratex-pro' ), $wp_version ),
								array( __( 'PHP version', 'infinity-migratex-pro' ), PHP_VERSION ),
								array( __( 'Database server', 'infinity-migratex-pro' ), IMP_Compatibility::db_version() ),
								array( __( 'Web server', 'infinity-migratex-pro' ), IMP_Compatibility::server_software() ),
								array( __( 'PHP memory limit', 'infinity-migratex-pro' ), (string) ini_get( 'memory_limit' ) ),
								array( __( 'Site size', 'infinity-migratex-pro' ), imp_format_bytes( $stats['bytes'] ) . ' (' . number_format_i18n( $stats['files'] ) . ' ' . __( 'files', 'infinity-migratex-pro' ) . ')' ),
								array( __( 'Database size', 'infinity-migratex-pro' ), imp_format_bytes( $stats['db_bytes'] ) . ' (' . number_format_i18n( $stats['db_tables'] ) . ' ' . __( 'tables', 'infinity-migratex-pro' ) . ')' ),
								array( __( 'Plugins', 'infinity-migratex-pro' ), number_format_i18n( count( (array) get_option( 'active_plugins', array() ) ) ) . ' ' . __( 'active', 'infinity-migratex-pro' ) ),
								array( __( 'Themes', 'infinity-migratex-pro' ), wp_get_themes() ? count( wp_get_themes() ) : '—' ),
								array( __( 'Free disk space', 'infinity-migratex-pro' ), imp_format_bytes( IMP_Site_Stats::disk_free() ) ),
							);
							foreach ( $rows as $row ) :
								?>
								<tr>
									<th scope="row"><?php echo esc_html( $row[0] ); ?></th>
									<td><?php echo esc_html( $row[1] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<section class="imp-panel" id="imp-health-panel" data-imp-health>
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'System health', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="health-refresh"><?php esc_html_e( 'Re-check now', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
			<div class="imp-panel-body">
				<table class="imp-table imp-table-health">
					<tbody>
						<?php foreach ( $health as $check ) : ?>
							<tr data-imp-check="<?php echo esc_attr( $check['id'] ); ?>">
								<th scope="row"><?php echo esc_html( $check['label'] ); ?></th>
								<td>
									<?php
									$labels = array(
										'pass' => __( 'PASS', 'infinity-migratex-pro' ),
										'warn' => __( 'WARNING', 'infinity-migratex-pro' ),
										'error' => __( 'ERROR', 'infinity-migratex-pro' ),
									);
									echo IMP_Admin::badge(
										'error' === $check['status'] ? 'fail' : ( 'warn' === $check['status'] ? 'warn' : 'pass' ),
										esc_html( $labels[ $check['status'] ] )
									);
									?>
									<span class="imp-health-value"><?php echo esc_html( $check['value'] ); ?></span>
									<span class="imp-health-hint"><?php echo esc_html( $check['hint'] ); ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Smart reminders', 'infinity-migratex-pro' ); ?></h3>
			</div>
			<div class="imp-panel-body">
				<?php if ( empty( $reminders ) ) : ?>
					<div class="imp-notice imp-notice-success" style="margin:0;">
						<strong><?php esc_html_e( 'Everything is covered.', 'infinity-migratex-pro' ); ?></strong>
						<?php esc_html_e( 'Recent scan, backups on file, protection layers active.', 'infinity-migratex-pro' ); ?>
					</div>
				<?php else : ?>
					<ul class="imp-reminders">
						<?php foreach ( $reminders as $reminder ) : ?>
							<li>
								<span class="dashicons dashicons-bell" aria-hidden="true"></span>
								<span class="imp-reminder-msg"><?php echo esc_html( $reminder['msg'] ); ?></span>
								<a class="imp-btn imp-btn-small" href="<?php echo esc_url( $reminder['url'] ); ?>"><?php echo esc_html( $reminder['cta'] ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( $last_backup ) : ?>
		<section class="imp-panel imp-last-backup">
			<div class="imp-panel-body imp-last-backup-body">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<div class="imp-last-backup-info">
					<strong><?php echo esc_html( sprintf( /* translators: %s: name */ __( 'Latest backup: %s', 'infinity-migratex-pro' ), $last_backup['name'] ) ); ?></strong>
					<p><?php
					echo esc_html( sprintf(
						/* translators: 1: human time 2: size */
						__( '%1$s ago · %2$s', 'infinity-migratex-pro' ),
						human_time_diff( (int) $last_backup['created_ts'], time() ),
						imp_format_bytes( (int) ( $last_backup['sizes']['total'] ?? 0 ) )
					) );
					?></p>
				</div>
				<div class="imp-last-backup-actions">
					<a class="imp-btn imp-btn-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'imp_download_backup', 'id' => rawurlencode( $last_backup['id'] ) ), admin_url( 'admin-post.php' ) ), 'imp-download' ) ); ?>"><?php esc_html_e( 'Download', 'infinity-migratex-pro' ); ?></a>
					<a class="imp-btn imp-btn-small imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-restore&backup=' . rawurlencode( $last_backup['id'] ) ) ); ?>"><?php esc_html_e( 'Restore', 'infinity-migratex-pro' ); ?></a>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Recent activity', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-logs' ) ); ?>"><?php esc_html_e( 'View all logs', 'infinity-migratex-pro' ); ?></a>
				</div>
			</div>
			<div class="imp-panel-body">
				<?php $recent = IMP_Logger::recent( 8 ); ?>
				<?php if ( empty( $recent ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No operations recorded yet. Your migrations, backups and scans will be listed here.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<table class="imp-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Operation', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Type', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Duration', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Date', 'infinity-migratex-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['action'] ); ?></td>
									<td><?php echo esc_html( $entry['type'] ); ?></td>
									<td>
										<?php
										$status_badges = array(
											'completed' => array( 'pass', __( 'Completed', 'infinity-migratex-pro' ) ),
											'failed'    => array( 'fail', __( 'Failed', 'infinity-migratex-pro' ) ),
											'running'   => array( 'info', __( 'Running', 'infinity-migratex-pro' ) ),
											'canceled'  => array( 'neutral', __( 'Canceled', 'infinity-migratex-pro' ) ),
										);
										$sb = isset( $status_badges[ $entry['status'] ] ) ? $status_badges[ $entry['status'] ] : array( 'neutral', $entry['status'] );
										echo IMP_Admin::badge( $sb[0], $sb[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
										?>
									</td>
									<td><?php echo esc_html( imp_human_duration( (float) $entry['duration'] ) ); ?></td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $entry['created'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : recalcul santé + stats.
	 */
	public static function ajax_health_refresh() {
		IMP_Security::ajax_guard( 'manage' );

		// Bouton explicite : l'utilisateur DEMANDE des données fraîches —
		// on force le recalcul complet (l'affichage, lui, n'attend jamais).
		delete_transient( 'imp_site_stats' );
		delete_transient( 'imp_health_checks' );
		IMP_Site_Stats::get( true );

		$checks = IMP_Compatibility::health_checks( true );

		wp_send_json_success(
			array(
				'checks' => $checks,
				'overall' => IMP_Compatibility::overall_status( $checks ),
			)
		);
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
