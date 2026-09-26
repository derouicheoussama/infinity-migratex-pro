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
 * Page Scanner : scan complet (checksums core + fichiers) par chunks,
 * rapport réel avec statuts SAFE / WARNING / CRITICAL.
 */
final class IMP_Admin_Scanner {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'scanner' );

		$last = IMP_Scanner::last_report();
		?>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Security scanner', 'infinity-migratex-pro' ); ?></h3>
				<div class="imp-panel-actions">
					<button type="button" class="imp-btn imp-btn-primary" data-imp-action="scan-start"><?php esc_html_e( 'Scan site now', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
			<div class="imp-panel-body">
				<p class="imp-lead"><?php esc_html_e( 'Real checks: WordPress core checksums from the official WordPress.org API, PHP files inside uploads, potentially suspicious code patterns, large files and risky permissions.', 'infinity-migratex-pro' ); ?></p>
				<div class="imp-notice imp-notice-info">
					<?php esc_html_e( 'This scanner never declares a file to be malware on its own: findings are marked “potentially suspicious — needs review”. A modified core file can have legitimate causes (e.g. language packs).', 'infinity-migratex-pro' ); ?>
				</div>

				<div data-imp-jobbox="scan" class="imp-jobbox"></div>
			</div>
		</section>

		<section class="imp-panel" data-imp-scan-report <?php echo $last ? '' : 'hidden'; ?>>
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Last scan report', 'infinity-migratex-pro' ); ?></h3>
				<span class="imp-muted" data-imp-scan-meta>
					<?php if ( $last ) : ?>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: date 2: files 3: duration */
								__( '%1$s — %2$s files in %3$s', 'infinity-migratex-pro' ),
								mysql2date( get_option( 'date_format' ) . ' H:i', $last['date'] ),
								number_format_i18n( $last['files_scanned'] ),
								imp_human_duration( $last['duration'] )
							)
						);
						?>
					<?php endif; ?>
				</span>
			</div>
			<div class="imp-panel-body" data-imp-scan-body>
				<?php if ( $last ) : ?>
					<?php self::render_report( $last ); ?>
				<?php endif; ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * Affiche un rapport (utilisé au rendu initial et au retour JS).
	 *
	 * @param array $report Rapport.
	 * @return void
	 */
	public static function render_report( $report ) {
		$summary = $report['summary'];
		?>
		<div class="imp-cards imp-cards-3">
			<div class="imp-card imp-card-critical">
				<span class="imp-card-label"><?php esc_html_e( 'Critical', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $summary['critical'] ) ); ?></strong>
			</div>
			<div class="imp-card imp-card-warning">
				<span class="imp-card-label"><?php esc_html_e( 'Warnings', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $summary['warning'] ) ); ?></strong>
			</div>
			<div class="imp-card imp-card-info">
				<span class="imp-card-label"><?php esc_html_e( 'Informational', 'infinity-migratex-pro' ); ?></span>
				<strong class="imp-card-value"><?php echo esc_html( number_format_i18n( $summary['info'] ) ); ?></strong>
			</div>
		</div>

		<?php if ( empty( $report['core_api_ok'] ) ) : ?>
			<div class="imp-notice imp-notice-warning">
				<?php esc_html_e( 'The WordPress.org checksum API was unreachable during this scan: core verification was skipped. Other checks ran normally.', 'infinity-migratex-pro' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( empty( $report['findings'] ) ) : ?>
			<div class="imp-notice imp-notice-success">
				<strong><?php esc_html_e( 'SAFE', 'infinity-migratex-pro' ); ?></strong> —
				<?php esc_html_e( 'no findings in this scan.', 'infinity-migratex-pro' ); ?>
			</div>
		<?php else : ?>
			<?php if ( ! empty( $report['truncated'] ) ) : ?>
				<div class="imp-notice imp-notice-info"><?php esc_html_e( 'Showing the first 500 findings.', 'infinity-migratex-pro' ); ?></div>
			<?php endif; ?>
			<table class="imp-table imp-table-list">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Level', 'infinity-migratex-pro' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'infinity-migratex-pro' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'infinity-migratex-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) $report['findings'] as $finding ) : ?>
						<tr>
							<td>
								<?php
								$map = array(
									'critical' => array( 'fail', 'CRITICAL' ),
									'warning'  => array( 'warn', 'WARNING' ),
									'info'     => array( 'info', 'INFO' ),
								);
								$b = isset( $map[ $finding['level'] ] ) ? $map[ $finding['level'] ] : array( 'neutral', '?' );
								echo IMP_Admin::badge( $b[0], $b[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
								?>
							</td>
							<td><code><?php echo esc_html( (string) $finding['type'] ); ?></code></td>
							<td><?php echo esc_html( (string) $finding['text'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}
}
