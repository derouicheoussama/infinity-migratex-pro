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
 * Page Logs : journal réel des opérations — filtres, recherche,
 * export CSV/JSON, suppression. Jamais de secrets enregistrés.
 */
final class IMP_Admin_Logs {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'logs' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'logs' );

		$current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type         = isset( $_GET['log_type'] ) ? sanitize_key( wp_unslash( $_GET['log_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status       = isset( $_GET['log_status'] ) ? sanitize_key( wp_unslash( $_GET['log_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page     = 25;

		$args = array(
			'limit'  => $per_page,
			'offset' => ( $current_page - 1 ) * $per_page,
		);
		if ( '' !== $type ) {
			$args['type'] = $type;
		}
		if ( '' !== $status ) {
			$args['status'] = $status;
		}
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$rows  = IMP_Logger::query( $args );
		$total = IMP_Logger::count( array_filter( array( 'type' => $type, 'status' => $status, 'search' => $search ), static function ( $v ) { return '' !== $v; } ) );
		$pages = (int) ceil( $total / $per_page );

		$export_csv  = wp_nonce_url( add_query_arg( 'action', 'imp_download_log', admin_url( 'admin-post.php' ) ), 'imp-download' );
		$export_json = add_query_arg( 'format', 'json', $export_csv );

		$types = array(
			''          => __( 'All types', 'infinity-migratex-pro' ),
			'backup'    => __( 'Backup', 'infinity-migratex-pro' ),
			'restore'   => __( 'Restore', 'infinity-migratex-pro' ),
			'migration' => __( 'Migration', 'infinity-migratex-pro' ),
			'scan'      => __( 'Scan', 'infinity-migratex-pro' ),
			'replace'   => __( 'Replace', 'infinity-migratex-pro' ),
			'package'   => __( 'Package', 'infinity-migratex-pro' ),
			'database'  => __( 'Database', 'infinity-migratex-pro' ),
			'system'    => __( 'System', 'infinity-migratex-pro' ),
		);
		$statuses = array(
			''          => __( 'All statuses', 'infinity-migratex-pro' ),
			'completed' => __( 'Completed', 'infinity-migratex-pro' ),
			'failed'    => __( 'Failed', 'infinity-migratex-pro' ),
			'running'   => __( 'Running', 'infinity-migratex-pro' ),
			'canceled'  => __( 'Canceled', 'infinity-migratex-pro' ),
		);
		?>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Activity logs', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( $export_csv ); ?>"><?php esc_html_e( 'Export CSV', 'infinity-migratex-pro' ); ?></a>
					<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( $export_json ); ?>"><?php esc_html_e( 'Export JSON', 'infinity-migratex-pro' ); ?></a>
					<button type="button" class="imp-btn imp-btn-danger" data-imp-action="logs-clear" data-imp-confirm="delete"><?php esc_html_e( 'Clear all logs', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
			<div class="imp-panel-body">
				<form method="get" class="imp-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="infinity-migratex-pro-logs">
					<select name="log_type">
						<?php foreach ( $types as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="log_status">
						<?php foreach ( $statuses as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search logs…', 'infinity-migratex-pro' ); ?>">
					<button type="submit" class="imp-btn imp-btn-ghost"><?php esc_html_e( 'Filter', 'infinity-migratex-pro' ); ?></button>
				</form>

				<?php if ( empty( $rows ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No log entries match these filters.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<table class="imp-table imp-table-list" data-imp-logs-table>
						<thead>
							<tr>
								<th scope="col">#</th>
								<th scope="col"><?php esc_html_e( 'Date', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Operation', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Type', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Duration', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Files', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'DB rows', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Size', 'infinity-migratex-pro' ); ?></th>
								<th scope="col" class="imp-col-actions"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr data-imp-log-id="<?php echo esc_attr( $row['id'] ); ?>">
									<td><?php echo (int) $row['id']; ?></td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i:s', $row['created'] ) ); ?></td>
									<td>
										<?php echo esc_html( $row['action'] ); ?>
										<?php if ( ! empty( $row['message'] ) ) : ?>
											<br><em class="imp-muted"><?php echo esc_html( mb_substr( (string) $row['message'], 0, 120 ) ); ?></em>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $row['type'] ); ?></td>
									<td>
										<?php
										$map = array(
											'completed' => array( 'pass', __( 'Completed', 'infinity-migratex-pro' ) ),
											'failed'    => array( 'fail', __( 'Failed', 'infinity-migratex-pro' ) ),
											'running'   => array( 'info', __( 'Running', 'infinity-migratex-pro' ) ),
											'canceled'  => array( 'neutral', __( 'Canceled', 'infinity-migratex-pro' ) ),
										);
										$b = isset( $map[ $row['status'] ] ) ? $map[ $row['status'] ] : array( 'neutral', $row['status'] );
										echo IMP_Admin::badge( $b[0], $b[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
										?>
									</td>
									<td><?php echo esc_html( imp_human_duration( (float) $row['duration'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $row['files_processed'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $row['db_rows'] ) ); ?></td>
									<td><?php echo esc_html( (int) $row['size_bytes'] > 0 ? imp_format_bytes( (int) $row['size_bytes'] ) : '—' ); ?></td>
									<td class="imp-col-actions">
										<button type="button" class="imp-btn imp-btn-small imp-btn-ghost" data-imp-action="log-details" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Details', 'infinity-migratex-pro' ); ?></button>
										<button type="button" class="imp-btn imp-btn-small imp-btn-danger" data-imp-action="log-delete" data-id="<?php echo esc_attr( $row['id'] ); ?>" data-imp-confirm="delete"><?php esc_html_e( 'Delete', 'infinity-migratex-pro' ); ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $pages > 1 ) : ?>
						<nav class="imp-pagination" aria-label="<?php esc_attr_e( 'Logs pagination', 'infinity-migratex-pro' ); ?>">
							<?php
							$base = add_query_arg(
								array_filter(
									array(
										'page'       => 'infinity-migratex-pro-logs',
										'log_type'   => $type,
										'log_status' => $status,
										's'          => $search,
									),
									static function ( $v ) { return '' !== $v; }
								),
								admin_url( 'admin.php' )
							);
							echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', $base ), 'format' => '', 'current' => $current_page, 'total' => $pages ) ) );
							?>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : actions sur les logs.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'logs' );

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		switch ( $do ) {
			case 'delete':
				$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				if ( $id < 1 ) {
					wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
				}
				IMP_Logger::delete( $id );
				wp_send_json_success( array( 'deleted' => $id ) );
				break;

			case 'clear':
				IMP_Logger::clear();
				wp_send_json_success( array( 'cleared' => true ) );
				break;

			case 'details':
				$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				if ( $id < 1 ) {
					wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
				}
				$rows = IMP_Logger::query( array( 'limit' => 200 ) );
				$found = null;
				foreach ( $rows as $row ) {
					if ( (int) $row['id'] === $id ) {
						$found = $row;
						break;
					}
				}
				if ( null === $found ) {
					wp_send_json_error( array( 'code' => 'IMP-218', 'message' => __( 'Log entry not found in the recent window.', 'infinity-migratex-pro' ) ), 404 );
				}
				wp_send_json_success(
					array(
						'entry'    => $found,
						'details'  => json_decode( (string) $found['details'], true ),
						'user'     => get_userdata( (int) $found['user_id'] ) ? get_userdata( (int) $found['user_id'] )->user_login : __( 'system', 'infinity-migratex-pro' ),
					)
				);
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
