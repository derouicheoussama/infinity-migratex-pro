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
 * Page Settings : réglages réels enregistrés avec les APIs WordPress.
 * Chaque option modifie effectivement le comportement du plugin.
 */
final class IMP_Admin_Settings {

	const OPTION = 'imp_settings';

	/**
	 * Rendu — UN SEUL ÉCRAN : les 8 sections sont rendues dans le même
	 * formulaire, les onglets basculent instantanément côté navigateur
	 * (sans rechargement) et la sauvegarde part en AJAX depuis la barre
	 * fixe. Les liens ?tab= restent fonctionnels sans JavaScript.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'settings' );

		$settings = imp_settings();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'general', 'migration', 'backup', 'cloud', 'sheets', 'security', 'performance', 'logs', 'advanced' ), true ) ) {
			$tab = 'general';
		}

		$tabs = array(
			'general'     => __( 'General', 'infinity-migratex-pro' ),
			'migration'   => __( 'Migration', 'infinity-migratex-pro' ),
			'backup'      => __( 'Backup', 'infinity-migratex-pro' ),
			'cloud'       => __( 'Cloud (Pro)', 'infinity-migratex-pro' ),
			'sheets'      => __( 'Google Sheets (Pro)', 'infinity-migratex-pro' ),
			'security'    => __( 'Security', 'infinity-migratex-pro' ),
			'performance' => __( 'Performance', 'infinity-migratex-pro' ),
			'logs'        => __( 'Logs', 'infinity-migratex-pro' ),
			'advanced'    => __( 'Advanced', 'infinity-migratex-pro' ),
		);

		$panels = array(
			'general'     => 'tab_general',
			'migration'   => 'tab_migration',
			'backup'      => 'tab_backup',
			'cloud'       => 'tab_cloud',
			'sheets'      => 'tab_sheets',
			'security'    => 'tab_security',
			'performance' => 'tab_performance',
			'logs'        => 'tab_logs',
			'advanced'    => 'tab_advanced',
		);
		?>
		<section class="imp-panel imp-settings-shell">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Settings', 'infinity-migratex-pro' ); ?></h3>
				<p><?php esc_html_e( 'Everything in one screen — sections switch instantly and saving never leaves the page.', 'infinity-migratex-pro' ); ?></p>
			</div>
			<div class="imp-panel-body">
				<div class="imp-settings-search">
					<input type="search" data-imp-settings-search placeholder="<?php esc_attr_e( 'Search a setting… (ex: retention, theme, cloud)', 'infinity-migratex-pro' ); ?>" aria-label="<?php esc_attr_e( 'Search settings', 'infinity-migratex-pro' ); ?>">
					<span class="imp-settings-search-hint" data-imp-settings-search-hint hidden></span>
				</div>

				<nav class="imp-tabs" data-imp-tabs aria-label="<?php esc_attr_e( 'Settings sections', 'infinity-migratex-pro' ); ?>">
					<?php foreach ( $tabs as $key => $label ) : ?>
						<button type="button" class="imp-tab<?php echo $key === $tab ? ' is-active' : ''; ?>" data-imp-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</nav>

				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" data-imp-settings-form>
					<?php settings_fields( 'imp_settings_group' ); ?>

					<?php foreach ( $panels as $key => $method ) : ?>
						<div data-imp-tab-panel="<?php echo esc_attr( $key ); ?>"<?php echo $key === $tab ? '' : ' hidden'; ?>>
							<?php self::{$method}( $settings ); ?>
						</div>
					<?php endforeach; ?>

					<div class="imp-savebar" data-imp-savebar>
						<span class="imp-savebar-state" data-imp-savebar-state hidden>
							<span class="imp-dot" aria-hidden="true"></span><?php esc_html_e( 'Unsaved changes', 'infinity-migratex-pro' ); ?>
						</span>
						<span class="imp-savebar-saved" data-imp-savebar-saved>
							<?php
							$saved_at = (int) get_option( 'imp_settings_saved_at', 0 );
							if ( $saved_at > 0 ) {
								echo esc_html(
									sprintf(
										/* translators: 1: date 2: time */
										__( 'Last saved: %1$s at %2$s.', 'infinity-migratex-pro' ),
										mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $saved_at ) ),
										mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $saved_at ) )
									)
								);
							}
							?>
						</span>
						<button type="submit" class="imp-btn imp-btn-primary" data-imp-savebar-submit><?php esc_html_e( 'Save settings', 'infinity-migratex-pro' ); ?></button>
					</div>
				</form>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/* ------------------------------------------------------------------ *
	 * Onglets
	 * ------------------------------------------------------------------ */

	/**
	 * Onglet général.
	 */
	private static function tab_general( $settings ) {
		?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><label for="imp_setting_location"><?php esc_html_e( 'Backup location (absolute path, empty = default)', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="imp_setting_location" name="imp_settings[backup_location]" value="<?php echo esc_attr( $settings['backup_location'] ); ?>" placeholder="<?php echo esc_attr( trailingslashit( WP_CONTENT_DIR ) . 'infinity-migratex-pro' ); ?>" autocomplete="off">
					<p class="description"><?php esc_html_e( 'Preferably outside the web root. Current default:', 'infinity-migratex-pro' ); ?> <code><?php echo esc_html( IMP_Plugin::storage_dir() ); ?></code></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic cleanup', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[auto_cleanup]" value="1" <?php checked( ! empty( $settings['auto_cleanup'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Delete temporary migration files automatically during the daily maintenance.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Confirmation before destructive actions', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[confirm_destructive]" value="1" <?php checked( ! empty( $settings['confirm_destructive'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Ask for confirmation before restore, import, replace and delete operations.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'E-mail notifications (scheduled backups)', 'infinity-migratex-pro' ); ?></th>
				<td>
					<select name="imp_settings[notify_events]">
						<option value="failures" <?php selected( $settings['notify_events'], 'failures' ); ?>><?php esc_html_e( 'On failure only (recommended)', 'infinity-migratex-pro' ); ?></option>
						<option value="all" <?php selected( $settings['notify_events'], 'all' ); ?>><?php esc_html_e( 'On every completion', 'infinity-migratex-pro' ); ?></option>
						<option value="none" <?php selected( $settings['notify_events'], 'none' ); ?>><?php esc_html_e( 'Never', 'infinity-migratex-pro' ); ?></option>
					</select>
					<p>
						<input type="text" class="regular-text" name="imp_settings[notify_email]" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" placeholder="<?php esc_attr_e( 'Notification e-mail (empty = admin e-mail)', 'infinity-migratex-pro' ); ?>">
					</p>
					<p class="description"><?php esc_html_e( 'Applies to unattended operations: scheduled backups and their cloud uploads.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_theme"><?php esc_html_e( 'Appearance', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_theme" name="imp_settings[ui_theme]">
						<option value="light" <?php selected( $settings['ui_theme'], 'light' ); ?>><?php esc_html_e( 'Light (default)', 'infinity-migratex-pro' ); ?></option>
						<option value="dark" <?php selected( $settings['ui_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'infinity-migratex-pro' ); ?></option>
						<option value="auto" <?php selected( $settings['ui_theme'], 'auto' ); ?>><?php esc_html_e( 'Auto — follow my system', 'infinity-migratex-pro' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Dark mode covers the whole plugin interface, including notifications.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Smart reminders', 'infinity-migratex-pro' ); ?></th>
				<td>
					<p>
						<label style="display:inline-flex;align-items:center;gap:8px;margin-right:18px;">
							<?php esc_html_e( 'Backup older than', 'infinity-migratex-pro' ); ?>
							<select name="imp_settings[backup_reminder_days]">
								<option value="7" <?php selected( $settings['backup_reminder_days'], 7 ); ?>>7 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="14" <?php selected( $settings['backup_reminder_days'], 14 ); ?>>14 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="30" <?php selected( $settings['backup_reminder_days'], 30 ); ?>>30 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="0" <?php selected( $settings['backup_reminder_days'], 0 ); ?>><?php esc_html_e( 'Never remind', 'infinity-migratex-pro' ); ?></option>
							</select>
						</label>
						<label style="display:inline-flex;align-items:center;gap:8px;">
							<?php esc_html_e( 'Security scan older than', 'infinity-migratex-pro' ); ?>
							<select name="imp_settings[scan_reminder_days]">
								<option value="14" <?php selected( $settings['scan_reminder_days'], 14 ); ?>>14 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="30" <?php selected( $settings['scan_reminder_days'], 30 ); ?>>30 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="60" <?php selected( $settings['scan_reminder_days'], 60 ); ?>>60 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="90" <?php selected( $settings['scan_reminder_days'], 90 ); ?>>90 <?php esc_html_e( 'days', 'infinity-migratex-pro' ); ?></option>
								<option value="0" <?php selected( $settings['scan_reminder_days'], 0 ); ?>><?php esc_html_e( 'Never remind', 'infinity-migratex-pro' ); ?></option>
							</select>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'Drives the “Smart reminders” cards on the dashboard. Zero hides the reminder completely.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default exclusions', 'infinity-migratex-pro' ); ?></th>
				<td>
					<textarea class="imp-textarea" rows="8" name="imp_settings[default_exclusions]"><?php echo esc_textarea( $settings['default_exclusions'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Prefilled in the migration and backup forms. One pattern per line.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet migration.
	 */
	private static function tab_migration( $settings ) {
		?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><label for="imp_setting_chunk_files"><?php esc_html_e( 'Files per batch', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="10" max="2000" step="10" id="imp_setting_chunk_files" name="imp_settings[chunk_files]" value="<?php echo esc_attr( (int) $settings['chunk_files'] ); ?>">
					<p class="description"><?php esc_html_e( 'How many files are added per step to an archive. Lower it on slow shared hosting.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_chunk_rows"><?php esc_html_e( 'Rows per batch (replace)', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="10" max="5000" step="10" id="imp_setting_chunk_rows" name="imp_settings[chunk_rows]" value="<?php echo esc_attr( (int) $settings['chunk_rows'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_max_exec"><?php esc_html_e( 'Max seconds per step', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="3" max="25" id="imp_setting_max_exec" name="imp_settings[max_execution]" value="<?php echo esc_attr( (int) $settings['max_execution'] ); ?>">
					<p class="description"><?php esc_html_e( 'Time budget per AJAX step. The plugin stays under the PHP max_execution_time automatically.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_retries"><?php esc_html_e( 'Retry count', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="0" max="5" id="imp_setting_retries" name="imp_settings[retries]" value="<?php echo esc_attr( (int) $settings['retries'] ); ?>">
					<p class="description"><?php esc_html_e( 'Automatic retries of a failed step before marking the operation failed.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Serialized-safe JSON replacement', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[urlreplace_in_json]" value="1" <?php checked( ! empty( $settings['urlreplace_in_json'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Decode JSON values, replace inside, re-encode — keeps JSON valid during URL replacements.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet backup.
	 */
	private static function tab_backup( $settings ) {
		$sched_pro = IMP_License::is_pro();
		?>
		<?php if ( ! $sched_pro ) : ?>
			<div class="imp-notice imp-notice-info">
				<strong>★ <?php esc_html_e( 'Pro feature', 'infinity-migratex-pro' ); ?></strong> —
				<?php esc_html_e( 'Scheduled & automatic backups are part of the Pro edition (with cloud destinations, Turbo speed and cross-site URL rewrite).', 'infinity-migratex-pro' ); ?>
				<a class="imp-btn imp-btn-primary" style="margin-left:8px;" href="<?php echo esc_url( IMP_License::checkout_url() ); ?>" target="_blank" rel="noopener noreferrer">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></a>
			</div>
		<?php endif; ?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic backups', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[backup_schedule_enabled]" value="1" <?php checked( ! empty( $settings['backup_schedule_enabled'] ) ); ?> <?php disabled( ! $sched_pro ); ?>><span class="imp-switch-slider"></span></label>
					<?php echo $sched_pro ? '' : '<span class="imp-edition imp-edition-pro" style="margin-left:8px;">PRO</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Safety backup before updates', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[preupdate_backup]" value="1" <?php checked( ! empty( $settings['preupdate_backup'] ) ); ?> <?php disabled( ! $sched_pro ); ?>><span class="imp-switch-slider"></span></label>
					<?php echo $sched_pro ? '' : '<span class="imp-edition imp-edition-pro" style="margin-left:8px;">PRO</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p class="description"><?php esc_html_e( 'Before WordPress installs an update (plugin, theme or core — manual or automatic), a database safety snapshot is taken automatically so you can always roll back.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_schedule"><?php esc_html_e( 'Schedule', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_schedule" name="imp_settings[backup_schedule]" <?php disabled( ! $sched_pro ); ?>>
						<option value="daily" <?php selected( $settings['backup_schedule'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'infinity-migratex-pro' ); ?></option>
						<option value="weekly" <?php selected( $settings['backup_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'infinity-migratex-pro' ); ?></option>
						<option value="monthly" <?php selected( $settings['backup_schedule'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'infinity-migratex-pro' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_hour"><?php esc_html_e( 'Hour of the day', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_hour" name="imp_settings[backup_schedule_hour]" <?php disabled( ! $sched_pro ); ?>>
						<?php for ( $h = 0; $h < 24; $h++ ) : ?>
							<option value="<?php echo esc_attr( $h ); ?>" <?php selected( (int) $settings['backup_schedule_hour'], $h ); ?>><?php echo esc_html( sprintf( '%02d:00', $h ) ); ?></option>
						<?php endfor; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The backup runs at the first site visit from this hour (WP-Cron behavior).', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_components"><?php esc_html_e( 'Contents', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_components" name="imp_settings[backup_schedule_components]" <?php disabled( ! $sched_pro ); ?>>
						<option value="full" <?php selected( $settings['backup_schedule_components'], 'full' ); ?>><?php esc_html_e( 'Full site', 'infinity-migratex-pro' ); ?></option>
						<option value="database" <?php selected( $settings['backup_schedule_components'], 'database' ); ?>><?php esc_html_e( 'Database only', 'infinity-migratex-pro' ); ?></option>
						<option value="files" <?php selected( $settings['backup_schedule_components'], 'files' ); ?>><?php esc_html_e( 'Files only', 'infinity-migratex-pro' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_retention"><?php esc_html_e( 'Retention', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="1" max="100" id="imp_setting_retention" name="imp_settings[backup_retention]" value="<?php echo esc_attr( (int) $settings['backup_retention'] ); ?>" <?php disabled( ! $sched_pro ); ?>>
					<p class="description"><?php esc_html_e( 'Keep the last N backups — older ones are deleted automatically.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Compression (gzip database dumps)', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[compression]" value="1" <?php checked( ! empty( $settings['compression'] ) ); ?>><span class="imp-switch-slider"></span></label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet Cloud (Pro) : destinations de sauvegarde à distance.
	 */
	private static function tab_cloud( $settings ) {
		if ( ! class_exists( 'IMP_Remote' ) ) {
			?>
			<div class="imp-notice imp-notice-warning">
				<?php esc_html_e( 'The cloud module is disabled (INFINITY_MIGRATEX_PRO_SAFE_MODE).', 'infinity-migratex-pro' ); ?>
			</div>
			<?php
			return;
		}

		$is_pro = IMP_License::is_pro();
		$pro_url = '' !== INFINITY_MIGRATEX_PRO_CHECKOUT_URL ? INFINITY_MIGRATEX_PRO_CHECKOUT_URL : 'https://www.derouicheoussama.com';
		?>
		<?php if ( ! $is_pro ) : ?>
			<div class="imp-notice imp-notice-info">
				<strong>★ <?php esc_html_e( 'Pro feature', 'infinity-migratex-pro' ); ?></strong> —
				<?php esc_html_e( 'Send every backup to Google Drive, Dropbox or an FTP server. The engine below is fully implemented (resumable uploads, connection test) and unlocks with your Pro license. Everything is tested locally in development mode: activate any key in the Advanced tab to try it now.', 'infinity-migratex-pro' ); ?>
				<a class="imp-btn imp-btn-primary" style="margin-left:8px;" href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener noreferrer">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></a>
			</div>
		<?php endif; ?>

		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Send backups to the cloud', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[cloud_enabled]" value="1" <?php checked( ! empty( $settings['cloud_enabled'] ) ); ?> <?php disabled( ! $is_pro ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Automatically upload each new backup (manual and scheduled) to the destination below.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_provider"><?php esc_html_e( 'Destination', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_provider" name="imp_settings[cloud_provider]" data-imp-cloud-provider <?php disabled( ! $is_pro ); ?>>
						<option value=""><?php esc_html_e( '— None —', 'infinity-migratex-pro' ); ?></option>
						<option value="drive" <?php selected( $settings['cloud_provider'], 'drive' ); ?>>Google Drive</option>
						<option value="dropbox" <?php selected( $settings['cloud_provider'], 'dropbox' ); ?>>Dropbox</option>
						<option value="ftp" <?php selected( $settings['cloud_provider'], 'ftp' ); ?>><?php esc_html_e( 'FTP / FTPS', 'infinity-migratex-pro' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Amazon S3 and OneDrive are on the roadmap.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Keep local copy', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[cloud_keep_local]" value="1" <?php checked( ! empty( $settings['cloud_keep_local'] ) ); ?> <?php disabled( ! $is_pro ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Recommended ON. When off, remote parts are deleted from this server after a verified upload (the manifest always stays).', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
		</table>

		<?php /* PERFORMANCE : les blocs de configuration providers (70+
		       * lignes de DOM) ne sont rendus QUE si Pro est actif — hors
		       * Pro, l'onglet Cloud reste un teaser léger. */ ?>
		<?php if ( $is_pro ) : ?>
		<div data-imp-cloud-fields="drive" <?php echo 'drive' === $settings['cloud_provider'] ? '' : 'hidden'; ?>>
			<h4 style="margin:6px 0 10px;">Google Drive</h4>
			<table class="form-table imp-form" role="presentation">
				<tr>
					<th scope="row"><label><?php esc_html_e( 'OAuth client ID', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="imp_settings[cloud_drive_client_id]" value="<?php echo esc_attr( $settings['cloud_drive_client_id'] ); ?>" autocomplete="off" <?php disabled( ! $is_pro ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'OAuth client secret', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="password" class="regular-text" name="imp_settings[cloud_drive_client_secret]" value="<?php echo esc_attr( $settings['cloud_drive_client_secret'] ); ?>" autocomplete="new-password" <?php disabled( ! $is_pro ); ?>>
					<p class="description"><?php esc_html_e( 'Stored encrypted (AES-256-GCM when available).', 'infinity-migratex-pro' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Refresh token', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="password" class="regular-text" name="imp_settings[cloud_drive_refresh]" value="<?php echo esc_attr( $settings['cloud_drive_refresh'] ); ?>" autocomplete="new-password" <?php disabled( ! $is_pro ); ?>>
					<p class="description"><?php esc_html_e( 'Generate with the drive.file scope.', 'infinity-migratex-pro' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Folder ID (optional)', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="imp_settings[cloud_drive_folder]" value="<?php echo esc_attr( $settings['cloud_drive_folder'] ); ?>" autocomplete="off" <?php disabled( ! $is_pro ); ?>></td>
				</tr>
			</table>
		</div>

		<div data-imp-cloud-fields="dropbox" <?php echo 'dropbox' === $settings['cloud_provider'] ? '' : 'hidden'; ?>>
			<h4 style="margin:6px 0 10px;">Dropbox</h4>
			<table class="form-table imp-form" role="presentation">
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Access token', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="password" class="regular-text" name="imp_settings[cloud_dropbox_token]" value="<?php echo esc_attr( $settings['cloud_dropbox_token'] ); ?>" autocomplete="new-password" <?php disabled( ! $is_pro ); ?>>
					<p class="description"><?php esc_html_e( 'App Console → generate token with files.content.write scope. Stored encrypted.', 'infinity-migratex-pro' ); ?></p></td>
				</tr>
			</table>
		</div>

		<div data-imp-cloud-fields="ftp" <?php echo 'ftp' === $settings['cloud_provider'] ? '' : 'hidden'; ?>>
			<h4 style="margin:6px 0 10px;"><?php esc_html_e( 'FTP / FTPS', 'infinity-migratex-pro' ); ?></h4>
			<table class="form-table imp-form" role="presentation">
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Host', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="imp_settings[cloud_ftp_host]" value="<?php echo esc_attr( $settings['cloud_ftp_host'] ); ?>" autocomplete="off" <?php disabled( ! $is_pro ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Port', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="number" class="small-text" name="imp_settings[cloud_ftp_port]" value="<?php echo esc_attr( (int) $settings['cloud_ftp_port'] ); ?>" min="1" max="65535" <?php disabled( ! $is_pro ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Username', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="imp_settings[cloud_ftp_user]" value="<?php echo esc_attr( $settings['cloud_ftp_user'] ); ?>" autocomplete="off" <?php disabled( ! $is_pro ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Password', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="password" class="regular-text" name="imp_settings[cloud_ftp_pass]" value="<?php echo esc_attr( $settings['cloud_ftp_pass'] ); ?>" autocomplete="new-password" <?php disabled( ! $is_pro ); ?>>
					<p class="description"><?php esc_html_e( 'Stored encrypted (AES-256-GCM when available).', 'infinity-migratex-pro' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Remote path', 'infinity-migratex-pro' ); ?></label></th>
					<td><input type="text" class="regular-text" name="imp_settings[cloud_ftp_path]" value="<?php echo esc_attr( $settings['cloud_ftp_path'] ); ?>" autocomplete="off" <?php disabled( ! $is_pro ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'FTPS (explicit TLS)', 'infinity-migratex-pro' ); ?></th>
					<td><label class="imp-switch"><input type="checkbox" name="imp_settings[cloud_ftp_ssl]" value="1" <?php checked( ! empty( $settings['cloud_ftp_ssl'] ) ); ?> <?php disabled( ! $is_pro ); ?>><span class="imp-switch-slider"></span></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Passive mode', 'infinity-migratex-pro' ); ?></th>
					<td><label class="imp-switch"><input type="checkbox" name="imp_settings[cloud_ftp_passive]" value="1" <?php checked( ! empty( $settings['cloud_ftp_passive'] ) ); ?> <?php disabled( ! $is_pro ); ?>><span class="imp-switch-slider"></span></label></td>
				</tr>
			</table>
		</div>
		<?php endif; ?>

		<p class="description" data-imp-cloud-hints>
			<?php foreach ( IMP_Remote::providers() as $provider_id => $provider ) : ?>
				<em style="display:none" data-imp-cloud-hint="<?php echo esc_attr( $provider_id ); ?>"><?php echo esc_html( $provider['hint'] ); ?></em>
			<?php endforeach; ?>
			<span data-imp-cloud-hint-active></span>
		</p>
		<p>
			<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="cloud-test" <?php disabled( ! $is_pro ); ?>>⟳ <?php esc_html_e( 'Test connection', 'infinity-migratex-pro' ); ?></button>
			<span data-imp-cloud-test-result class="imp-muted"></span>
		</p>
		<?php
	}

	/**
	 * Onglet Google Sheets (Pro) — journal automatique des opérations.
	 */
	private static function tab_sheets( $settings ) {
		$is_pro = IMP_License::is_pro();
		?>
		<?php if ( ! $is_pro ) : ?>
			<div class="imp-notice imp-notice-info">
				<strong>★ <?php esc_html_e( 'Pro feature', 'infinity-migratex-pro' ); ?></strong> —
				<?php esc_html_e( 'Track every backup, restore, migration and import automatically in a Google Sheet. Set it up in two minutes with a free Google service account.', 'infinity-migratex-pro' ); ?>
				<button type="button" class="imp-btn imp-btn-primary" style="margin-left:8px;" data-imp-checkout="personal">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></button>
			</div>
		<?php endif; ?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Google Sheets tracking', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[sheets_enabled]" value="1" <?php checked( ! empty( $settings['sheets_enabled'] ) ); ?> <?php disabled( ! $is_pro ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Adds a row to your spreadsheet after every completed operation: date, type, name, site, files, size, duration, status.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Spreadsheet ID', 'infinity-migratex-pro' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="imp_settings[sheets_id]" value="<?php echo esc_attr( $settings['sheets_id'] ); ?>" autocomplete="off" <?php disabled( ! $is_pro ); ?>>
					<p class="description"><?php esc_html_e( 'The long ID in the sheet URL: docs.google.com/spreadsheets/d/THIS_ID/edit — share the sheet with the service account e-mail (Editor).', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sheet (tab) name', 'infinity-migratex-pro' ); ?></th>
				<td><input type="text" class="regular-text" name="imp_settings[sheets_name]" value="<?php echo esc_attr( $settings['sheets_name'] ); ?>" <?php disabled( ! $is_pro ); ?>></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Service account JSON', 'infinity-migratex-pro' ); ?></th>
				<td>
					<textarea class="imp-textarea" rows="6" name="imp_settings[sheets_json]" autocomplete="off" <?php disabled( ! $is_pro ); ?>><?php echo esc_textarea( $settings['sheets_json'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Google Cloud → Service account → Keys → JSON. Stored encrypted; never leaves this site. Share the target sheet with the service account e-mail.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'infinity-migratex-pro' ); ?></th>
				<td>
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="sheets-test" <?php disabled( ! $is_pro ); ?>>⟳ <?php esc_html_e( 'Send a test row', 'infinity-migratex-pro' ); ?></button>
					<span data-imp-sheets-test-result class="imp-muted"></span>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet sécurité.
	 */
	private static function tab_security( $settings ) {
		?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><label for="imp_setting_validation"><?php esc_html_e( 'Package validation', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_validation" name="imp_settings[package_validation]">
						<option value="strict" <?php selected( $settings['package_validation'], 'strict' ); ?>><?php esc_html_e( 'Strict — refuse any unsafe entry', 'infinity-migratex-pro' ); ?></option>
						<option value="standard" <?php selected( $settings['package_validation'], 'standard' ); ?>><?php esc_html_e( 'Standard — skip unsafe entries, import the rest', 'infinity-migratex-pro' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Secure temporary files', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[secure_tmp]" value="1" <?php checked( ! empty( $settings['secure_tmp'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Uploads and working files stay in the .htaccess-protected storage.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin notifications', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[admin_notifications]" value="1" <?php checked( ! empty( $settings['admin_notifications'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Show a notice in the admin when an operation fails.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet performance.
	 */
	private static function tab_performance( $settings ) {
		?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><label for="imp_setting_memory"><?php esc_html_e( 'Memory strategy', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<select id="imp_setting_memory" name="imp_settings[memory_strategy]">
						<option value="conservative" <?php selected( $settings['memory_strategy'], 'conservative' ); ?>><?php esc_html_e( 'Conservative (small batches)', 'infinity-migratex-pro' ); ?></option>
						<option value="balanced" <?php selected( $settings['memory_strategy'], 'balanced' ); ?>><?php esc_html_e( 'Balanced (default)', 'infinity-migratex-pro' ); ?></option>
						<option value="aggressive" <?php selected( $settings['memory_strategy'], 'aggressive' ); ?>><?php esc_html_e( 'Aggressive (large batches, dedicated servers)', 'infinity-migratex-pro' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Adapts the database batch size to the available memory.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_db_rows"><?php esc_html_e( 'Database rows per batch', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="20" max="2000" step="10" id="imp_setting_db_rows" name="imp_settings[chunk_db_rows]" value="<?php echo esc_attr( (int) $settings['chunk_db_rows'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="imp_setting_scan_large"><?php esc_html_e( 'Large file threshold (MB, scanner)', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="1" max="2048" id="imp_setting_scan_large" name="imp_settings[scan_large_file_mb]" value="<?php echo esc_attr( (int) $settings['scan_large_file_mb'] ); ?>">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet logs.
	 */
	private static function tab_logs( $settings ) {
		?>
		<table class="form-table imp-form" role="presentation">
			<tr>
				<th scope="row"><label for="imp_setting_logs_days"><?php esc_html_e( 'Log retention (days)', 'infinity-migratex-pro' ); ?></label></th>
				<td>
					<input type="number" min="7" max="365" id="imp_setting_logs_days" name="imp_settings[logs_retention_days]" value="<?php echo esc_attr( (int) $settings['logs_retention_days'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug mode', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[debug_mode]" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><?php esc_html_e( 'Adds technical detail to error messages (for diagnosis only).', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Export logs', 'infinity-migratex-pro' ); ?></th>
				<td>
					<?php
					$csv  = wp_nonce_url( add_query_arg( 'action', 'imp_download_log', admin_url( 'admin-post.php' ) ), 'imp-download' );
					$json = add_query_arg( 'format', 'json', $csv );
					?>
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( $csv ); ?>">CSV</a>
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( $json ); ?>">JSON</a>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Onglet avancé (données, licence, mises à jour).
	 */
	private static function tab_advanced( $settings ) {
		$license = IMP_License::get();
		?>
		<div class="imp-panel imp-config-tools">
			<h4 style="margin:0 0 10px;"><?php esc_html_e( 'Configuration tools', 'infinity-migratex-pro' ); ?></h4>
			<p style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0 0 8px;">
				<button type="button" class="imp-btn imp-btn-ghost" data-imp-config-export>⬇ <?php esc_html_e( 'Export configuration (JSON)', 'infinity-migratex-pro' ); ?></button>
				<input type="file" accept="application/json,.json" data-imp-config-file style="max-width:280px;">
				<button type="button" class="imp-btn imp-btn-ghost" data-imp-config-import>⬆ <?php esc_html_e( 'Import configuration', 'infinity-migratex-pro' ); ?></button>
				<button type="button" class="imp-btn imp-btn-ghost" data-imp-config-reset style="color:#c9281e;">↺ <?php esc_html_e( 'Restore default settings…', 'infinity-migratex-pro' ); ?></button>
			</p>
			<p class="description" style="margin:0;"><?php esc_html_e( 'Export/import replicates your setup on another site in seconds. Secrets (cloud tokens, passwords) are never exported — re-enter them on the target site. Restore defaults keeps your license and all backups.', 'infinity-migratex-pro' ); ?></p>
		</div>
		<table class="form-table imp-form" role="presentation">
			<?php if ( IMP_License::configured() ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'License', 'infinity-migratex-pro' ); ?></th>
					<td>
						<p>
							<?php
							echo IMP_License::is_pro()
								? IMP_Admin::badge( 'pass', sprintf( /* translators: %s: plan */ __( 'Pro active — %s', 'infinity-migratex-pro' ), $license['plan'] ) ) // phpcs:ignore WordPress.Security.EscapeOutput
								: IMP_Admin::badge( 'neutral', __( 'Free edition', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</p>
						<form data-imp-license-form>
							<input type="text" name="license_key" placeholder="<?php esc_attr_e( 'License key', 'infinity-migratex-pro' ); ?>" autocomplete="off">
							<button type="submit" class="imp-btn imp-btn-primary"><?php esc_html_e( 'Activate', 'infinity-migratex-pro' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Updates', 'infinity-migratex-pro' ); ?></th>
				<td>
					<p class="description">
						<?php
						$update = class_exists( 'IMP_Updater' ) ? IMP_Updater::update_available() : null;
						echo esc_html(
							$update
								/* translators: %s: version */
								? sprintf( __( 'Version %s is available — update from Plugins page.', 'infinity-migratex-pro' ), $update['tag'] )
								: __( 'You are up to date. Updates are served from official GitHub releases (no data is sent anywhere).', 'infinity-migratex-pro' )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete plugin data on uninstall', 'infinity-migratex-pro' ); ?></th>
				<td>
					<label class="imp-switch"><input type="checkbox" name="imp_settings[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>><span class="imp-switch-slider"></span></label>
					<p class="description"><strong><?php esc_html_e( 'Backups are NEVER deleted on uninstall, even with this option on.', 'infinity-migratex-pro' ); ?></strong> <?php esc_html_e( 'This removes settings, logs and scan reports only. Default: off.', 'infinity-migratex-pro' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Enregistrement (Settings API)
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre les réglages via l'API WordPress.
	 */
	public static function register() {
		register_setting(
			'imp_settings_group',
			'imp_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => imp_default_settings(),
			)
		);
	}

	/**
	 * Nettoyage des réglages.
	 *
	 * @param array $input Entrée.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = imp_default_settings();
		$input    = is_array( $input ) ? $input : array();
		$clean    = imp_settings(); // base : valeurs existantes.

		// Textes.
		$clean['backup_location']    = imp_normalize_path( isset( $input['backup_location'] ) ? (string) $input['backup_location'] : '' );
		$clean['default_exclusions'] = imp_parse_exclusions( isset( $input['default_exclusions'] ) ? (string) $input['default_exclusions'] : '' );
		$clean['default_exclusions'] = implode( "\n", $clean['default_exclusions'] );

		// Bascules.
		foreach ( array( 'auto_cleanup', 'confirm_destructive', 'compression', 'backup_schedule_enabled', 'urlreplace_in_json', 'secure_tmp', 'admin_notifications', 'debug_mode', 'delete_data_on_uninstall', 'preupdate_backup', 'sheets_enabled' ) as $flag ) {
			$clean[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		// Nombres bornés.
		$clean['chunk_files']    = max( 10, min( 2000, (int) ( isset( $input['chunk_files'] ) ? $input['chunk_files'] : $defaults['chunk_files'] ) ) );
		$clean['chunk_rows']     = max( 10, min( 5000, (int) ( isset( $input['chunk_rows'] ) ? $input['chunk_rows'] : $defaults['chunk_rows'] ) ) );
		$clean['chunk_db_rows']  = max( 20, min( 2000, (int) ( isset( $input['chunk_db_rows'] ) ? $input['chunk_db_rows'] : $defaults['chunk_db_rows'] ) ) );
		$clean['max_execution']  = max( 3, min( 25, (int) ( isset( $input['max_execution'] ) ? $input['max_execution'] : $defaults['max_execution'] ) ) );
		$clean['retries']        = max( 0, min( 5, (int) ( isset( $input['retries'] ) ? $input['retries'] : $defaults['retries'] ) ) );
		$clean['backup_schedule_hour']   = max( 0, min( 23, (int) ( isset( $input['backup_schedule_hour'] ) ? $input['backup_schedule_hour'] : $defaults['backup_schedule_hour'] ) ) );
		$clean['backup_retention']       = max( 1, min( 100, (int) ( isset( $input['backup_retention'] ) ? $input['backup_retention'] : $defaults['backup_retention'] ) ) );
		$clean['logs_retention_days']    = max( 7, min( 365, (int) ( isset( $input['logs_retention_days'] ) ? $input['logs_retention_days'] : $defaults['logs_retention_days'] ) ) );
		$clean['scan_large_file_mb']     = max( 1, min( 2048, (int) ( isset( $input['scan_large_file_mb'] ) ? $input['scan_large_file_mb'] : $defaults['scan_large_file_mb'] ) ) );

		// Énumérations.
		$clean['backup_schedule'] = in_array( isset( $input['backup_schedule'] ) ? $input['backup_schedule'] : '', array( 'daily', 'weekly', 'monthly' ), true )
			? $input['backup_schedule'] : $defaults['backup_schedule'];
		$clean['backup_schedule_components'] = in_array( isset( $input['backup_schedule_components'] ) ? $input['backup_schedule_components'] : '', array( 'full', 'files', 'database' ), true )
			? $input['backup_schedule_components'] : $defaults['backup_schedule_components'];
		$clean['package_validation'] = in_array( isset( $input['package_validation'] ) ? $input['package_validation'] : '', array( 'strict', 'standard' ), true )
			? $input['package_validation'] : $defaults['package_validation'];
		$clean['memory_strategy'] = in_array( isset( $input['memory_strategy'] ) ? $input['memory_strategy'] : '', array( 'conservative', 'balanced', 'aggressive' ), true )
			? $input['memory_strategy'] : $defaults['memory_strategy'];

		// Apparence.
		$clean['ui_theme'] = in_array( isset( $input['ui_theme'] ) ? $input['ui_theme'] : '', array( 'light', 'dark', 'auto' ), true )
			? $input['ui_theme'] : 'light';

		// Notifications.
		$clean['notify_events'] = in_array( isset( $input['notify_events'] ) ? $input['notify_events'] : '', array( 'failures', 'all', 'none' ), true )
			? $input['notify_events'] : $defaults['notify_events'];
		$clean['notify_email'] = isset( $input['notify_email'] ) ? sanitize_email( trim( (string) $input['notify_email'] ) ) : '';

		// Rappels intelligents (0 = rappel désactivé).
		$clean['backup_reminder_days'] = max( 0, min( 365, (int) ( isset( $input['backup_reminder_days'] ) ? $input['backup_reminder_days'] : $defaults['backup_reminder_days'] ) ) );
		$clean['scan_reminder_days']   = max( 0, min( 365, (int) ( isset( $input['scan_reminder_days'] ) ? $input['scan_reminder_days'] : $defaults['scan_reminder_days'] ) ) );

		// Cloud (Pro) : secrets chiffrés avant stockage, jamais en clair.
		$clean['cloud_enabled']   = empty( $input['cloud_enabled'] ) ? 0 : 1;
		$clean['cloud_keep_local'] = empty( $input['cloud_keep_local'] ) ? 0 : 1;
		$clean['cloud_provider']  = in_array( isset( $input['cloud_provider'] ) ? $input['cloud_provider'] : '', array( 'drive', 'dropbox', 'ftp' ), true )
			? $input['cloud_provider'] : '';

		$clean['cloud_drive_client_id'] = sanitize_text_field( isset( $input['cloud_drive_client_id'] ) ? (string) $input['cloud_drive_client_id'] : '' );
		$clean['cloud_drive_folder']    = sanitize_text_field( isset( $input['cloud_drive_folder'] ) ? (string) $input['cloud_drive_folder'] : '' );
		$clean['cloud_ftp_host']        = sanitize_text_field( isset( $input['cloud_ftp_host'] ) ? (string) $input['cloud_ftp_host'] : '' );
		$clean['cloud_ftp_port']        = max( 1, min( 65535, (int) ( isset( $input['cloud_ftp_port'] ) ? $input['cloud_ftp_port'] : 21 ) ) );
		$clean['cloud_ftp_user']        = sanitize_text_field( isset( $input['cloud_ftp_user'] ) ? (string) $input['cloud_ftp_user'] : '' );
		$clean['cloud_ftp_path']        = '/' . ltrim( isset( $input['cloud_ftp_path'] ) ? (string) $input['cloud_ftp_path'] : '/', '/' );
		$clean['cloud_ftp_ssl']         = empty( $input['cloud_ftp_ssl'] ) ? 0 : 1;
		$clean['cloud_ftp_passive']     = empty( $input['cloud_ftp_passive'] ) ? 0 : 1;

		// Google Sheets (Pro).
		$clean['sheets_id']   = sanitize_text_field( isset( $input['sheets_id'] ) ? (string) $input['sheets_id'] : '' );
		$clean['sheets_name'] = sanitize_text_field( isset( $input['sheets_name'] ) ? (string) $input['sheets_name'] : 'Backups' );
		if ( '' === $clean['sheets_name'] ) {
			$clean['sheets_name'] = 'Backups';
		}

		// Secrets : si le champ soumis est vide → conserver l'ancien ;
		// sinon chiffrer la nouvelle valeur.
		// Secrets chiffrés + JSON du compte de service Google Sheets.
		foreach ( array( 'cloud_drive_client_secret', 'cloud_drive_refresh', 'cloud_dropbox_token', 'cloud_ftp_pass', 'sheets_json' ) as $secret_key ) {
			$submitted = isset( $input[ $secret_key ] ) ? trim( (string) $input[ $secret_key ] ) : '';
			if ( '' === $submitted ) {
				$clean[ $secret_key ] = isset( $clean[ $secret_key ] ) ? $clean[ $secret_key ] : '';
				continue;
			}
			// Valeur déjà chiffrée (ressoumise telle quelle) ou nouvelle saisie.
			$clean[ $secret_key ] = ( 0 === strpos( $submitted, 'impenc1:' ) || 0 === strpos( $submitted, 'impb64:' ) )
				? $submitted
				: IMP_Security::encrypt( $submitted );
		}

		return $clean;
	}

	/**
	 * AJAX : activation de licence (si configurée).
	 */
	public static function ajax_save() {
		IMP_Security::ajax_guard( 'settings' );

		$key = isset( $_POST['license_key'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) ) : '';
		if ( '' === $key ) {
			wp_send_json_error( array( 'code' => 'IMP-210', 'message' => __( 'Enter a license key.', 'infinity-migratex-pro' ) ), 422 );
		}

		$result = IMP_License::activate( $key );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'code' => 'IMP-226', 'message' => $result['message'] ), 422 );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX : sauvegarde de TOUS les réglages depuis l'écran unique
	 * (sans rechargement). Même pipeline de sanitization que l'API
	 * WordPress : fusion sur les valeurs existantes, bornage, listes
	 * blanches — puis invalidation du cache statique.
	 */
	public static function ajax_save_all() {
		IMP_Security::ajax_guard( 'settings' );

		$raw   = isset( $_POST['imp_settings_json'] ) ? wp_unslash( $_POST['imp_settings_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- json_decode puis sanitize() complet.
		$input = json_decode( (string) $raw, true );
		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'code' => 'IMP-211', 'message' => __( 'Invalid settings payload.', 'infinity-migratex-pro' ) ), 400 );
		}

		$clean = self::sanitize( $input );
		update_option( self::OPTION, $clean, false );
		IMP_Plugin::flush_settings();
		update_option( 'imp_settings_saved_at', time(), false );

		$message = __( 'Settings saved.', 'infinity-migratex-pro' );
		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX : ajoute une ligne de test dans la Google Sheet (Pro).
	 */
	public static function ajax_sheets_test() {
		IMP_Security::ajax_guard( 'settings' );

		if ( ! IMP_License::is_pro() ) {
			wp_send_json_error( array( 'code' => 'IMP-241', 'message' => IMP_Job::error_text( 'IMP-241' ) ), 402 );
		}

		$result = IMP_Sheets::append_row( array(
			gmdate( 'Y-m-d H:i:s' ),
			__( 'Connection test', 'infinity-migratex-pro' ),
			'test',
			'—',
			(string) home_url(),
			'',
			'',
			'',
			(string) IMP_VERSION,
			'OK',
		) );

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 422 );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX : démarre l'essai PRO de 14 jours (une seule fois par site).
	 */
	public static function ajax_start_trial() {
		IMP_Security::ajax_guard( 'settings' );

		$result = IMP_License::start_trial();
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 409 );
		}
		wp_send_json_success( array( 'message' => $result['message'], 'trial' => true ) );
	}

	/**
	 * Champs secrets JAMAIS exportés (tokens cloud, mots de passe) —
	 * ils vivent chiffrés pour ce site et ne serviraient à rien ailleurs.
	 *
	 * @return array
	 */
	private static function secret_keys() {
		return array(
			'cloud_drive_client_secret',
			'cloud_drive_refresh',
			'cloud_dropbox_token',
			'cloud_ftp_pass',
			'sheets_json',
		);
	}

	/**
	 * AJAX : export de la configuration en JSON (secrets exclus).
	 *
	 * @return void
	 */
	public static function ajax_settings_export() {
		IMP_Security::ajax_guard( 'settings' );

		$settings = imp_settings();
		foreach ( self::secret_keys() as $secret_key ) {
			unset( $settings[ $secret_key ] );
		}

		wp_send_json_success(
			array(
				'payload' => wp_json_encode(
					array(
						'product'     => 'infinity-migratex-pro',
						'exported_at' => gmdate( 'c' ),
						'version'     => IMP_VERSION,
						'settings'    => $settings,
					)
				),
			)
		);
	}

	/**
	 * AJAX : import d'une configuration exportée (fusion sanitisée —
	 * seuls les champs présents dans le fichier sont modifiés, les
	 * secrets existants sont préservés).
	 *
	 * @return void
	 */
	public static function ajax_settings_import() {
		IMP_Security::ajax_guard( 'settings' );

		$raw   = isset( $_POST['imp_settings_json'] ) ? wp_unslash( $_POST['imp_settings_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- json_decode puis sanitize() complet.
		$doc   = json_decode( (string) $raw, true );
		if ( ! is_array( $doc ) || empty( $doc['settings'] ) || ! is_array( $doc['settings'] ) || ( isset( $doc['product'] ) && 'infinity-migratex-pro' !== $doc['product'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This file is not an Infinity MigrateX configuration export.', 'infinity-migratex-pro' ) ), 400 );
		}

		foreach ( self::secret_keys() as $secret_key ) {
			unset( $doc['settings'][ $secret_key ] );
		}

		$clean = self::sanitize( $doc['settings'] );
		update_option( self::OPTION, $clean, false );
		IMP_Plugin::flush_settings();
		update_option( 'imp_settings_saved_at', time(), false );

		wp_send_json_success( array( 'message' => __( 'Configuration imported — review the highlighted values and save.', 'infinity-migratex-pro' ) ) );
	}

	/**
	 * AJAX : restauration des réglages par défaut (licence et backups
	 * préservés — seul imp_settings revient à ses valeurs d'usine).
	 *
	 * @return void
	 */
	public static function ajax_settings_reset() {
		IMP_Security::ajax_guard( 'settings' );

		update_option( self::OPTION, imp_default_settings(), false );
		IMP_Plugin::flush_settings();
		update_option( 'imp_settings_saved_at', time(), false );

		wp_send_json_success( array( 'message' => __( 'Default settings restored — reloading…', 'infinity-migratex-pro' ) ) );
	}

	/**
	 * Résout un secret soumis : vide → valeur stockée ; blob chiffré →
	 * déchiffré ; sinon valeur brute.
	 *
	 * @param string $key Champ.
	 * @return string
	 */
	private static function resolve_secret( $key ) {
		$settings = imp_settings();
		$stored   = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$raw      = isset( $_POST[ $key ] ) ? trim( (string) wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par ajax_guard.
		if ( '' === $raw ) {
			return IMP_Security::decrypt( $stored );
		}
		if ( 0 === strpos( $raw, 'impenc1:' ) || 0 === strpos( $raw, 'impb64:' ) ) {
			return IMP_Security::decrypt( $raw );
		}
		return $raw;
	}

	/**
	 * AJAX : test réel de connexion à la destination cloud.
	 */
	public static function ajax_cloud_test() {
		IMP_Security::ajax_guard( 'settings' );

		if ( ! IMP_License::is_pro() ) {
			wp_send_json_error( array( 'code' => 'IMP-241', 'message' => IMP_Job::error_text( 'IMP-241' ) ), 402 );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( (string) $_POST['provider'] ) ) : '';
		if ( ! in_array( $provider, array( 'drive', 'dropbox', 'ftp' ), true ) ) {
			wp_send_json_error( array( 'code' => 'IMP-224', 'message' => __( 'Pick a destination first.', 'infinity-migratex-pro' ) ), 400 );
		}

		$settings = imp_settings();
		$cfg      = array();

		if ( 'drive' === $provider ) {
			$cfg = array(
				'client_id'     => isset( $_POST['cloud_drive_client_id'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['cloud_drive_client_id'] ) ) ) : (string) $settings['cloud_drive_client_id'],
				'client_secret' => self::resolve_secret( 'cloud_drive_client_secret' ),
				'refresh'       => self::resolve_secret( 'cloud_drive_refresh' ),
				'folder'        => isset( $_POST['cloud_drive_folder'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['cloud_drive_folder'] ) ) ) : (string) $settings['cloud_drive_folder'],
			);
		} elseif ( 'dropbox' === $provider ) {
			$cfg = array( 'token' => self::resolve_secret( 'cloud_dropbox_token' ) );
		} elseif ( 'ftp' === $provider ) {
			$cfg = array(
				'host'    => isset( $_POST['cloud_ftp_host'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['cloud_ftp_host'] ) ) ) : (string) $settings['cloud_ftp_host'],
				'port'    => isset( $_POST['cloud_ftp_port'] ) ? max( 1, (int) $_POST['cloud_ftp_port'] ) : (int) $settings['cloud_ftp_port'],
				'user'    => isset( $_POST['cloud_ftp_user'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['cloud_ftp_user'] ) ) ) : (string) $settings['cloud_ftp_user'],
				'pass'    => self::resolve_secret( 'cloud_ftp_pass' ),
				'path'    => isset( $_POST['cloud_ftp_path'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['cloud_ftp_path'] ) ) ) : (string) $settings['cloud_ftp_path'],
				'ssl'     => ! empty( $settings['cloud_ftp_ssl'] ),
				'passive' => ! isset( $settings['cloud_ftp_passive'] ) || ! empty( $settings['cloud_ftp_passive'] ),
			);
		}

		$result = IMP_Remote::test_connection( $provider, $cfg );

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'code' => 'IMP-242', 'message' => $result['message'] ), 422 );
		}
		wp_send_json_success( array( 'message' => $result['message'] ) );
	}
}

add_action( 'admin_init', array( 'IMP_Admin_Settings', 'register' ) );

/**
 * Après sauvegarde des réglages : replanifier le cron et vider le cache.
 */
add_action(
	'update_option_imp_settings',
	static function () {
		IMP_Plugin::flush_settings();
		IMP_Cron::sync();
	},
	10,
	0
);

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
