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
 * Page Database : tables réelles (taille, lignes, moteur, collation),
 * export (backup DB), import SQL vérifié, optimize/repair avec
 * confirmation — aucune opération destructive sans accord explicite.
 */
final class IMP_Admin_Database {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'database' );

		$tables = IMP_Database::tables_info();
		$prefix = $GLOBALS['wpdb']->prefix;
		?>
		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Database manager', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<div class="imp-db-actions">
						<button type="button" class="imp-btn imp-btn-primary" data-imp-action="db-export"><?php esc_html_e( 'Export database (backup)', 'infinity-migratex-pro' ); ?></button>
						<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="db-refresh"><?php esc_html_e( 'Refresh table list', 'infinity-migratex-pro' ); ?></button>
					</div>
					<div data-imp-jobbox="backup" class="imp-jobbox"></div>

					<hr class="imp-sep">

					<form id="imp-db-import-form" data-imp-db-import enctype="multipart/form-data">
						<div class="imp-field">
							<label for="imp_db_sql_file"><?php esc_html_e( 'Import a SQL file (.sql / .sql.gz)', 'infinity-migratex-pro' ); ?></label>
							<input type="file" id="imp_db_sql_file" name="sql_file" accept=".sql,.gz">
							<p class="imp-hint"><?php esc_html_e( 'The file is uploaded to the protected storage, executed statement by statement, then deleted. Confirm before it runs.', 'infinity-migratex-pro' ); ?></p>
						</div>
						<button type="submit" class="imp-btn imp-btn-danger" data-imp-confirm="import"><?php esc_html_e( 'Upload & import', 'infinity-migratex-pro' ); ?></button>
					</form>
					<div data-imp-jobbox="db_import" class="imp-jobbox"></div>

					<hr class="imp-sep">

					<div class="imp-db-maint">
						<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="db-maint" data-mode="optimize" data-imp-confirm="replace"><?php esc_html_e( 'Optimize selected tables', 'infinity-migratex-pro' ); ?></button>
						<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="db-maint" data-mode="repair" data-imp-confirm="replace"><?php esc_html_e( 'Repair selected tables', 'infinity-migratex-pro' ); ?></button>
						<span class="imp-muted"><?php esc_html_e( 'Select tables below first. Repair only fixes MyISAM/ARCHIVE tables when the server allows it.', 'infinity-migratex-pro' ); ?></span>
					</div>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Summary', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body" data-imp-db-summary>
					<?php self::render_summary(); ?>
				</div>
			</section>
		</div>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Tables', 'infinity-migratex-pro' ); ?></h3>
				<label class="imp-check imp-check-inline"><input type="checkbox" data-imp-db-selectall> <?php esc_html_e( 'Select all', 'infinity-migratex-pro' ); ?></label>
			</div>
			<div class="imp-panel-body" data-imp-db-tables>
				<?php self::render_tables( $tables, $prefix ); ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * Résumé DB.
	 */
	private static function render_summary() {
		$stats = IMP_Site_Stats::db_stats();
		$rows  = array(
			array( __( 'Server', 'infinity-migratex-pro' ), IMP_Compatibility::db_version() ),
			array( __( 'Tables (site prefix)', 'infinity-migratex-pro' ), number_format_i18n( count( IMP_Database::site_tables() ) ) ),
			array( __( 'Tables (total in database)', 'infinity-migratex-pro' ), number_format_i18n( $stats['tables'] ) ),
			array( __( 'Rows', 'infinity-migratex-pro' ), number_format_i18n( $stats['rows'] ) ),
			array( __( 'Total size', 'infinity-migratex-pro' ), imp_format_bytes( $stats['bytes'] ) ),
			array( __( 'Table prefix', 'infinity-migratex-pro' ), '<code>' . esc_html( $GLOBALS['wpdb']->prefix ) . '</code>' ),
		);
		echo '<table class="imp-table imp-table-info"><tbody>';
		foreach ( $rows as $row ) {
			// $row[1] peut contenir du <code> volontaire — valeurs pré-échappées à la construction.
			echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . wp_kses_post( $row[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Liste des tables (avec cases à cocher pour maintenance).
	 *
	 * @param array  $tables Tables.
	 * @param string $prefix Préfixe.
	 */
	private static function render_tables( $tables, $prefix ) {
		if ( empty( $tables ) ) {
			echo '<p class="imp-muted">' . esc_html__( 'No tables readable.', 'infinity-migratex-pro' ) . '</p>';
			return;
		}
		?>
		<table class="imp-table imp-table-list">
			<thead>
				<tr>
					<th scope="col" class="imp-col-check"></th>
					<th scope="col"><?php esc_html_e( 'Table', 'infinity-migratex-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rows', 'infinity-migratex-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Size', 'infinity-migratex-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Index', 'infinity-migratex-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Engine', 'infinity-migratex-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Collation', 'infinity-migratex-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Belongs to', 'infinity-migratex-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tables as $table ) : ?>
					<tr>
						<td class="imp-col-check"><input type="checkbox" data-imp-db-table value="<?php echo esc_attr( $table['name'] ); ?>"></td>
						<td><code><?php echo esc_html( $table['name'] ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $table['rows'] ) ); ?></td>
						<td><?php echo esc_html( imp_format_bytes( $table['data_bytes'] ) ); ?></td>
						<td><?php echo esc_html( imp_format_bytes( $table['index_bytes'] ) ); ?></td>
						<td><?php echo esc_html( $table['engine'] ); ?></td>
						<td><?php echo esc_html( $table['collation'] ); ?></td>
						<td>
							<?php
							if ( 0 === strpos( $table['name'], $prefix ) ) {
								echo esc_html__( 'This site', 'infinity-migratex-pro' );
							} elseif ( false !== strpos( $table['name'], 'woocommerce' ) || false !== strpos( $table['name'], 'wc_' ) ) {
								echo esc_html__( 'WooCommerce', 'infinity-migratex-pro' );
							} else {
								echo esc_html__( 'Other installation', 'infinity-migratex-pro' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX : liste des tables (rafraîchissement).
	 */
	public static function ajax_tables() {
		IMP_Security::ajax_guard( 'manage' );

		ob_start();
		self::render_tables( IMP_Database::tables_info(), $GLOBALS['wpdb']->prefix );
		$html = (string) ob_get_clean();

		ob_start();
		self::render_summary();
		$summary = (string) ob_get_clean();

		wp_send_json_success(
			array(
				'html'    => $html,
				'summary' => $summary,
			)
		);
	}

	/**
	 * AJAX : export (job backup database), optimize, repair, import SQL.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'manage' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		switch ( $do ) {
			case 'export':
				$result = IMP_Job::start(
					'backup',
					array(
						'components' => 'database',
						'name'       => __( 'Database export', 'infinity-migratex-pro' ),
						'origin'     => 'manual',
					)
				);
				if ( ! $result['ok'] ) {
					wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 409 );
				}
				wp_send_json_success( array( 'status' => IMP_Job::public_status(), 'jobbox' => 'backup' ) );
				break;

			case 'maint':
				global $wpdb;
				$mode   = ( isset( $_POST['mode'] ) && 'repair' === $_POST['mode'] ) ? 'repair' : 'optimize'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- liste blanche ci-dessus.
				$tables = isset( $_POST['tables'] ) && is_array( $_POST['tables'] ) ? wp_unslash( $_POST['tables'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validés ci-dessous.
				$tables = array_values( array_filter( array_map( static function ( $t ) {
					$t = sanitize_text_field( (string) $t );
					return preg_match( '/^[A-Za-z0-9_\-]+$/', $t ) ? $t : '';
				}, $tables ) ) );

				if ( empty( $tables ) ) {
					wp_send_json_error( array( 'code' => 'IMP-224', 'message' => __( 'Select at least one table.', 'infinity-migratex-pro' ) ), 400 );
				}

				$results = array();
				$ok      = 0;
				$fail    = 0;
				foreach ( $tables as $table ) {
					if ( 'repair' === $mode ) {
						$report = $wpdb->get_row( "REPAIR TABLE `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table strictement validé [A-Za-z0-9_-]+.
					} else {
						$report = $wpdb->get_row( "OPTIMIZE TABLE `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table strictement validé.
					}
					$status = is_array( $report ) && isset( $report['Msg_type'] ) && 'status' === $report['Msg_type'] ? 'ok' : 'note';
					if ( is_array( $report ) && isset( $report['Msg_type'] ) && 'error' === $report['Msg_type'] ) {
						$status = 'error';
						$fail++;
					} elseif ( 'ok' === $status ) {
						$ok++;
					}
					$results[] = array(
						'table'  => $table,
						'status' => $status,
						'msg'    => is_array( $report ) ? (string) ( $report['Msg_text'] ?? '' ) : 'no result',
					);
				}

				$log_id = IMP_Logger::start( sprintf( /* translators: %s: mode */ __( 'Database %s', 'infinity-migratex-pro' ), $mode ), IMP_Logger::TYPE_DATABASE );
				IMP_Logger::finish( $log_id, $fail > 0 ? IMP_Logger::STATUS_FAILED : IMP_Logger::STATUS_COMPLETED, sprintf( '%d ok / %d failed', $ok, $fail ) );

				wp_send_json_success( array( 'results' => $results ) );
				break;

			case 'import_upload':
				$uploaded = IMP_Security::handle_upload( 'sql_file', array( 'sql', 'gz' ) );
				if ( is_string( $uploaded ) ) {
					wp_send_json_error( array( 'code' => $uploaded, 'message' => IMP_Job::error_text( $uploaded ) ), 422 );
				}
				wp_send_json_success( array( 'file' => basename( (string) $uploaded['file'] ) ) );
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}
