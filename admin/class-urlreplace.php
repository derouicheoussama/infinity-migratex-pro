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
 * Page URL Replace : remplacement intelligent sérialisé-safe avec
 * aperçu (dry run), domaine guidé et statistiques réelles.
 */
final class IMP_Admin_URLReplace {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'urlreplace' );
		?>
		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Smart URL replacer', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<p class="imp-lead"><?php esc_html_e( 'Replaces URLs (or any text) across the whole database without breaking serialized PHP data and JSON — Elementor pages, WooCommerce settings and WordPress options stay valid.', 'infinity-migratex-pro' ); ?></p>

					<form id="imp-replace-form" data-imp-replace-form>
						<div class="imp-field">
							<label for="imp_replace_from"><?php esc_html_e( 'Search for', 'infinity-migratex-pro' ); ?> *</label>
							<input type="text" id="imp_replace_from" name="replace_from" placeholder="https://oldsite.com" required>
						</div>
						<div class="imp-field">
							<label for="imp_replace_to"><?php esc_html_e( 'Replace with', 'infinity-migratex-pro' ); ?> *</label>
							<input type="text" id="imp_replace_to" name="replace_to" placeholder="https://newsite.com" required>
						</div>

						<div class="imp-field imp-field-check">
							<label class="imp-check"><input type="checkbox" id="imp_replace_json" checked> <?php esc_html_e( 'Also handle JSON values (decode → replace → re-encode)', 'infinity-migratex-pro' ); ?></label>
						</div>

						<div class="imp-wizard-nav">
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-action="replace-preview"><?php esc_html_e( 'Dry run (preview)', 'infinity-migratex-pro' ); ?></button>
							<button type="submit" class="imp-btn imp-btn-danger" data-imp-confirm="replace"><?php esc_html_e( 'Run replacement', 'infinity-migratex-pro' ); ?></button>
						</div>
					</form>
					<div data-imp-jobbox="replace" class="imp-jobbox"></div>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Preview & statistics', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<p class="imp-muted" data-imp-replace-preview-empty><?php esc_html_e( 'Run a dry run to see how many values would change — nothing is written.', 'infinity-migratex-pro' ); ?></p>
					<div data-imp-replace-preview hidden>
						<table class="imp-table imp-table-info">
							<tbody>
								<tr><th scope="row"><?php esc_html_e( 'Tables scanned', 'infinity-migratex-pro' ); ?></th><td data-imp-stat="tables">—</td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Rows scanned', 'infinity-migratex-pro' ); ?></th><td data-imp-stat="rows">—</td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Values that would change', 'infinity-migratex-pro' ); ?></th><td data-imp-stat="matches">—</td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Errors', 'infinity-migratex-pro' ); ?></th><td data-imp-stat="errors">—</td></tr>
							</tbody>
						</table>
						<p class="imp-hint"><?php esc_html_e( 'The dry run inspects the options and postmeta tables. The real replacement walks every table with text columns.', 'infinity-migratex-pro' ); ?></p>
					</div>
				</div>
			</section>
		</div>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : aperçu rapide (dry run sur tables clés).
	 */
	public static function ajax_preview() {
		IMP_Security::ajax_guard( 'manage' );

		$from = isset( $_POST['from'] ) ? trim( (string) wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? trim( (string) wp_unslash( $_POST['to'] ) ) : '';

		if ( strlen( $from ) < 3 || '' === $to || $from === $to ) {
			wp_send_json_error( array( 'code' => 'IMP-210', 'message' => IMP_Job::error_text( 'IMP-210' ) ), 422 );
		}

		$result = IMP_URL_Replacer::quick_preview( array( $from ), array( $to ) );

		wp_send_json_success(
			array(
				'tables'  => $result['tables'],
				'rows'    => $result['rows'],
				'matches' => $result['matches'],
				'errors'  => 0,
			)
		);
	}

	/**
	 * AJAX : démarrage du remplacement réel (ou dry run complet).
	 */
	public static function ajax_start() {
		IMP_Security::ajax_guard( 'manage' );

		$from = isset( $_POST['from'] ) ? trim( (string) wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? trim( (string) wp_unslash( $_POST['to'] ) ) : '';
		$json = empty( $_POST['json'] ) ? 0 : 1;
		$dry  = empty( $_POST['dry_run'] ) ? 0 : 1;

		if ( strlen( $from ) < 3 || '' === $to || $from === $to ) {
			wp_send_json_error( array( 'code' => 'IMP-210', 'message' => IMP_Job::error_text( 'IMP-210' ) ), 422 );
		}

		$result = IMP_Job::start(
			'replace',
			array(
				'from'    => $from,
				'to'      => $to,
				'json'    => $json,
				'dry_run' => $dry,
				'name'    => sprintf( /* translators: %s: search string */ __( 'Search & replace “%s”', 'infinity-migratex-pro' ), mb_substr( $from, 0, 40 ) ),
			)
		);

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 409 );
		}

		wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
	}
}
