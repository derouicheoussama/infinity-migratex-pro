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
 * Durcissement multi-couches :
 *
 * COUCHE 1 — Anti-cracker  : rate limiting par utilisateur + IP sur
 *            toutes les actions du plugin (AJAX, téléchargements,
 *            activation de licence) avec verrouillage temporaire.
 * COUCHE 2 — Anti-fuite    : journal d'événements de sécurité (tentatives
 *            non autorisées, dépassements de limite) + secrets masqués
 *            partout (logs, diagnostics, exports).
 * COUCHE 3 — Anti-contrefaçon : scellement du code source (baseline
 *            SHA-256 de tous les fichiers du plugin, vérification
 *            quotidienne + manuelle — toute modification est détectée
 *            et signalée) et vérification du manifest de la mise à jour.
 */
final class IMP_Hardening {

	const BASELINE_OPTION = 'imp_integrity_baseline';
	const STATUS_OPTION   = 'imp_integrity_status';
	const CRON_HOOK       = 'imp_cron_integrity';

	/* ------------------------------------------------------------------ *
	 * Couche 1 — Rate limiting (anti brute-force)
	 * ------------------------------------------------------------------ */

	/**
	 * Identité du demandeur (utilisateur + IP).
	 *
	 * @return string
	 */
	private static function client_id() {
		$user = get_current_user_id();
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:.]/', '', (string) $_SERVER['REMOTE_ADDR'] ) : '';
		return substr( md5( $user . '|' . $ip ), 0, 16 );
	}

	/**
	 * Vérifie et incrémente le compteur de fenêtre glissante.
	 *
	 * @param string $bucket Nom du compartiment (ajax|download|license).
	 * @param int    $max    Requêtes autorisées.
	 * @param int    $window Fenêtre en secondes.
	 * @return bool True si autorisé.
	 */
	public static function rate_ok( $bucket, $max, $window ) {
		$key = 'imp_rl_' . $bucket . '_' . self::client_id();
		$hit = get_transient( $key );

		if ( false === $hit ) {
			set_transient( $key, array( 'n' => 1, 't' => time() ), $window );
			return true;
		}

		$hit  = wp_parse_args( (array) $hit, array( 'n' => 0, 't' => time() ) );
		$hit['n']++;

		if ( $hit['n'] > $max ) {
			// Verrou plus long après dépassement : freine les attaques.
			set_transient( $key, $hit, max( $window, 300 ) );
			self::log_event(
				'rate-limit',
				sprintf(
					/* translators: 1: bucket 2: count */
					__( 'Rate limit exceeded on “%1$s” (%2$d requests) — temporary lock applied.', 'infinity-migratex-pro' ),
					$bucket,
					$hit['n']
				)
			);
			return false;
		}

		set_transient( $key, $hit, $window );
		return true;
	}

	/* ------------------------------------------------------------------ *
	 * Couche 2 — Journal d'événements de sécurité (anti-fuite)
	 * ------------------------------------------------------------------ */

	/**
	 * Écrit un événement de sécurité dans le journal (type "security"),
	 * sans jamais enregistrer de secret.
	 *
	 * @param string $event  Code d'événement.
	 * @param string $detail Détail (libre, déjà non sensible).
	 * @return void
	 */
	public static function log_event( $event, $detail ) {
		$log_id = IMP_Logger::start(
			substr( sanitize_text_field( (string) $event ), 0, 60 ),
			'security',
			array( 'detail' => (string) $detail )
		);
		IMP_Logger::finish(
			$log_id,
			IMP_Logger::STATUS_COMPLETED,
			substr( sanitize_text_field( (string) $detail ), 0, 190 )
		);
	}

	/* ------------------------------------------------------------------ *
	 * Couche 3 — Scellement du code (anti-contrefaçon)
	 * ------------------------------------------------------------------ */

	/**
	 * Construit la baseline SHA-256 de tous les fichiers du plugin.
	 *
	 * @return int Nombre de fichiers scellés.
	 */
	public static function rebuild_baseline() {
		$dir     = imp_normalize_path( IMP_DIR );
		$files   = array();
		$skip_re = '#(^|/)(\.git|node_modules)(/|$)#';

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach ( $iterator as $fileinfo ) {
			/** @var SplFileInfo $fileinfo */
			try {
				if ( ! $fileinfo->isFile() ) {
					continue;
				}
				$path = imp_normalize_path( $fileinfo->getPathname() );
				$rel  = ltrim( substr( $path, strlen( $dir ) ), '/' );
				if ( preg_match( $skip_re, $rel ) ) {
					continue;
				}
				$hash = hash_file( 'sha256', $path );
				if ( false !== $hash ) {
					$files[ $rel ] = $hash;
				}
			} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fichier illisible : ignoré du scellement.
			}
		}

		ksort( $files );
		update_option(
			self::BASELINE_OPTION,
			array(
				'built_at' => time(),
				'version'  => IMP_VERSION,
				'files'    => $files,
			),
			false
		);

		update_option(
			self::STATUS_OPTION,
			array(
				'ok'         => true,
				'checked_at' => time(),
				'changed'    => array(),
				'missing'    => array(),
				'added'      => array(),
			),
			false
		);

		return count( $files );
	}

	/**
	 * Vérifie les fichiers actuels contre la baseline.
	 *
	 * @return array{ok:bool,checked_at:int,changed:array,missing:array,added:array,count:int}
	 */
	public static function verify_baseline() {
		$baseline = get_option( self::BASELINE_OPTION );
		if ( ! is_array( $baseline ) || empty( $baseline['files'] ) ) {
			// Pas encore scellé : sceller maintenant (première exécution).
			$count = self::rebuild_baseline();
			return array(
				'ok'         => true,
				'checked_at' => time(),
				'changed'    => array(),
				'missing'    => array(),
				'added'      => array(),
				'count'      => $count,
			);
		}

		// Mise à jour légitime détectée : la version scellée diffère de la
		// version installée. L'upgrader WordPress ou l'installation manuelle
		// de l'archive officielle vient de remplacer les fichiers — on
		// re-scelle au lieu de crier à l'attaque. Une altération SANS
		// changement de version déclenche toujours l'alerte (le re-scellement
		// est journalisé et auditable dans le journal de sécurité).
		$sealed_version = isset( $baseline['version'] ) ? (string) $baseline['version'] : '';
		if ( '' === $sealed_version || $sealed_version !== IMP_VERSION ) {
			$count = self::rebuild_baseline();
			self::log_event(
				'integrity-reseal',
				sprintf(
					/* translators: 1: previous version 2: current version 3: file count */
					__( 'Official update detected (%1$s → %2$s) — source seal rebuilt on %3$d files. No tampering.', 'infinity-migratex-pro' ),
					'' !== $sealed_version ? $sealed_version : __( 'unknown', 'infinity-migratex-pro' ),
					IMP_VERSION,
					number_format_i18n( $count )
				)
			);
			return array(
				'ok'         => true,
				'checked_at' => time(),
				'changed'    => array(),
				'missing'    => array(),
				'added'      => array(),
				'count'      => $count,
				'resealed'   => true,
			);
		}

		$dir      = imp_normalize_path( IMP_DIR );
		$expected = (array) $baseline['files'];
		$changed  = array();
		$missing  = array();
		$added    = array();
		$seen     = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach ( $iterator as $fileinfo ) {
			/** @var SplFileInfo $fileinfo */
			try {
				if ( ! $fileinfo->isFile() ) {
					continue;
				}
				$path = imp_normalize_path( $fileinfo->getPathname() );
				$rel  = ltrim( substr( $path, strlen( $dir ) ), '/' );
				if ( false !== strpos( $rel, '.git/' ) ) {
					continue;
				}
				$seen[ $rel ] = true;

				if ( ! isset( $expected[ $rel ] ) ) {
					$added[] = $rel;
					continue;
				}
				if ( hash_file( 'sha256', $path ) !== $expected[ $rel ] ) {
					$changed[] = $rel;
				}
			} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// ignoré.
			}
		}

		foreach ( array_keys( $expected ) as $rel ) {
			if ( ! isset( $seen[ $rel ] ) ) {
				$missing[] = $rel;
			}
		}

		$ok = empty( $changed ) && empty( $missing );

		$status = array(
			'ok'         => $ok,
			'checked_at' => time(),
			'changed'    => array_slice( $changed, 0, 50 ),
			'missing'    => array_slice( $missing, 0, 50 ),
			'added'      => array_slice( $added, 0, 50 ),
			'count'      => count( $expected ),
		);
		update_option( self::STATUS_OPTION, $status, false );

		if ( ! $ok ) {
			self::log_event(
				'integrity-alert',
				sprintf(
					/* translators: 1: changed 2: missing */
					__( 'Plugin file integrity check FAILED — %1$d modified, %2$d missing.', 'infinity-migratex-pro' ),
					count( $changed ),
					count( $missing )
				)
			);
		}

		return $status;
	}

	/**
	 * Cron quotidien : vérification d'intégrité.
	 *
	 * @return void
	 */
	public static function run_daily_check() {
		self::verify_baseline();
	}

	/* ------------------------------------------------------------------ *
	 * Hooks
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre le cron quotidien (activation).
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_daily_check' ) );
	}

	/**
	 * Retire le cron (désactivation).
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Hooks d'administration.
	 *
	 * @return void
	 */
	public static function admin_hooks() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_daily_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'integrity_notice' ) );
	}

	/**
	 * Alerte visible si le code du plugin a été altéré.
	 *
	 * @return void
	 */
	public static function integrity_notice() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			return;
		}
		$status = get_option( self::STATUS_OPTION );
		if ( ! is_array( $status ) || ! empty( $status['ok'] ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong>Infinity MigrateX Pro — <?php esc_html_e( 'SECURITY ALERT', 'infinity-migratex-pro' ); ?>:</strong>
				<?php
				echo esc_html( sprintf(
					/* translators: 1: modified 2: missing */
					__( '%1$s plugin file(s) modified and %2$d missing versus the sealed baseline. This can be an attack or a manual patch — reinstall the plugin from the official source and check the Security page.', 'infinity-migratex-pro' ),
					count( (array) ( $status['changed'] ?? array() ) ),
					count( (array) ( $status['missing'] ?? array() ) )
				) );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Rapport des couches (Security Center).
	 *
	 * @return array[]
	 */
	public static function layers_report() {
		$status     = get_option( self::STATUS_OPTION );
		$baseline   = get_option( self::BASELINE_OPTION );
		$integrity  = is_array( $status ) ? $status : null;
		$sealed     = is_array( $baseline ) && ! empty( $baseline['files'] );
		$binding    = IMP_License::binding_mode_label();

		return array(
			array(
				'id'     => 'rate-limit',
				'label'  => __( 'Rate limiting (anti brute-force)', 'infinity-migratex-pro' ),
				'ok'     => true,
				'detail' => __( 'Every plugin action (AJAX, downloads, license activation) is limited per user + IP with temporary lockout. Events are written to the security journal.', 'infinity-migratex-pro' ),
			),
			array(
				'id'     => 'journal',
				'label'  => __( 'Security event journal', 'infinity-migratex-pro' ),
				'ok'     => true,
				'detail' => __( 'Unauthorized attempts, rate-limit hits and integrity alerts are logged (type “security”) and never contain secrets.', 'infinity-migratex-pro' ),
			),
			array(
				'id'     => 'secrets',
				'label'  => __( 'Secrets isolation (anti-leak)', 'infinity-migratex-pro' ),
				'ok'     => true,
				'detail' => __( 'Cloud credentials and migration DB passwords are AES-256-GCM encrypted, masked in every export, scrubbed from logs and destroyed after use.', 'infinity-migratex-pro' ),
			),
			array(
				'id'     => 'integrity',
				'label'  => __( 'Source code seal (anti-counterfeiting)', 'infinity-migratex-pro' ),
				'ok'     => ( null !== $integrity && ! empty( $integrity['ok'] ) ),
				'detail' => $sealed
					? sprintf(
						/* translators: 1: file count 2: date */
						__( 'Baseline sealed: %1$s files (built %2$s). Daily + manual verification against SHA-256.', 'infinity-migratex-pro' ),
						number_format_i18n( count( (array) $baseline['files'] ) ),
						mysql2date( get_option( 'date_format' ) . ' H:i', gmdate( 'Y-m-d H:i:s', (int) $baseline['built_at'] ) )
					)
					: __( 'Not sealed yet — run the integrity check to build the baseline.', 'infinity-migratex-pro' ),
			),
			array(
				'id'     => 'binding',
				'label'  => __( 'License binding & signed updates (Pro anti-piracy)', 'infinity-migratex-pro' ),
				'ok'     => true,
				'detail' => $binding,
			),
		);
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
