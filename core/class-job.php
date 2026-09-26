<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity MigrateX Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — signature et mentions à conserver.
 */

defined( 'ABSPATH' ) || exit;

/* phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.RestrictedFunctions, Squiz.PHP.DiscouragedFunctions -- Un plugin de sauvegarde doit lire/ecrire de tres gros fichiers binaires en flux (fopen/fwrite/fread) et dialoguer directement avec la base pour les dumps: WP_Filesystem et prepare() ne supportent pas ce mode. Nonces et capabilities restent verifies sur chaque action. */

/**
 * Ordonnanceur d'opérations longues : chaque job avance par steps bornés
 * en temps (budget), sauvegarde son état entre deux requêtes et peut être
 * mis en pause, repris ou annulé. La progression affichée correspond au
 * travail réellement effectué (fichiers/bytes/lignes traités).
 */
final class IMP_Job {

	const OPTION   = 'imp_active_job';
	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_CANCELED  = 'canceled';

	/** @var array|null Job en cours (cache local). */
	private static $job = null;

	/* ------------------------------------------------------------------ *
	 * Cycle de vie
	 * ------------------------------------------------------------------ */

	/**
	 * Démarre un job. Refuse si un autre job est actif.
	 *
	 * @param string $type Type (backup|restore|migration|scan|replace|package_import|db_import).
	 * @param array  $data Configuration du job.
	 * @return array{ok:bool,error?:string,job?:array}
	 */
	public static function start( $type, array $data = array() ) {
		$current = get_option( self::OPTION );
		if ( is_array( $current ) && ! empty( $current['status'] ) && self::STATUS_RUNNING === $current['status'] ) {
			return array( 'ok' => false, 'error' => 'IMP-230' );
		}

		$type = sanitize_key( (string) $type );
		if ( ! self::type_exists( $type ) ) {
			return array( 'ok' => false, 'error' => 'IMP-224' );
		}

		// Garde-fou anti fausse sécurité : chiffrement demandé sans mot de
		// passe = refus (une archive « chiffrée » à clé vide serait lisible
		// par quiconque connaît l'algorithme). Le JS valide déjà ; le
		// serveur ne descend JAMAIS en clair silencieusement.
		if ( 'backup' === $type && ! empty( $data['encrypt'] ) && empty( $data['enc_pass'] ) ) {
			return array( 'ok' => false, 'error' => 'IMP-258' );
		}

		$labels = array(
			'backup'         => __( 'Backup', 'infinity-migratex-pro' ),
			'restore'        => __( 'Restore', 'infinity-migratex-pro' ),
			'migration'      => __( 'Migration', 'infinity-migratex-pro' ),
			'scan'           => __( 'Security scan', 'infinity-migratex-pro' ),
			'replace'        => __( 'Search & replace', 'infinity-migratex-pro' ),
			'package_import' => __( 'Package import', 'infinity-migratex-pro' ),
			'db_import'      => __( 'Database import', 'infinity-migratex-pro' ),
			'remote'         => __( 'Cloud upload', 'infinity-migratex-pro' ),
		);

		$log_type = IMP_Logger::TYPE_SYSTEM;
		if ( isset( $labels[ $type ] ) ) {
			$map = array(
				'backup'         => IMP_Logger::TYPE_BACKUP,
				'restore'        => IMP_Logger::TYPE_RESTORE,
				'migration'      => IMP_Logger::TYPE_MIGRATION,
				'scan'           => IMP_Logger::TYPE_SCAN,
				'replace'        => IMP_Logger::TYPE_REPLACE,
				'package_import' => IMP_Logger::TYPE_RESTORE,
				'db_import'      => IMP_Logger::TYPE_DATABASE,
				'remote'         => IMP_Logger::TYPE_BACKUP,
			);
			$log_type = $map[ $type ];
		}

		$job = array(
			'id'         => 'j' . gmdate( 'His' ) . wp_generate_password( 6, false, false ),
			'type'       => $type,
			'title'      => isset( $labels[ $type ] ) ? $labels[ $type ] : $type,
			'phases'     => self::phases_for( $type, $data ),
			'phase'      => 0,
			'data'       => self::sanitize_data( $type, $data ),
			'state'      => array(),
			'status'     => self::STATUS_RUNNING,
			'started_at' => microtime( true ),
			'started'    => time(),
			'steps'      => 0,
			'messages'   => array(),
			'token'      => wp_generate_password( 32, false, false ),
			'origin'     => isset( $data['origin'] ) ? sanitize_key( (string) $data['origin'] ) : 'manual',
		);

		$log_name = isset( $data['name'] ) && '' !== $data['name'] ? sanitize_text_field( (string) $data['name'] ) : $job['title'];
		$job['log_id'] = IMP_Logger::start( $log_name, $log_type, array( 'type' => $type ) );

		// Migration clone : identifiants déjà chiffrés par le wizard sous une
		// référence temporaire → re-rattachés à ce job, référence effacée.
		if ( 'migration' === $type && ! empty( $data['creds_ref'] ) ) {
			$ref  = sanitize_key( (string) $data['creds_ref'] );
			$blob = get_transient( 'imp_migration_creds_' . $ref );
			if ( false !== $blob ) {
				set_transient( 'imp_migration_creds_' . $job['id'], $blob, 6 * HOUR_IN_SECONDS );
				delete_transient( 'imp_migration_creds_' . $ref );
			}
		}

		update_option( self::OPTION, $job, false );
		self::$job = $job;

		return array( 'ok' => true, 'job' => self::public_view( $job ) );
	}

	/**
	 * Job actif (ou dernier terminé).
	 *
	 * @return array|null
	 */
	public static function get() {
		if ( null !== self::$job ) {
			return self::$job;
		}
		$job = get_option( self::OPTION );
		self::$job = is_array( $job ) ? $job : null;
		return self::$job;
	}

	/**
	 * Job actif uniquement (running).
	 *
	 * @return array|null
	 */
	public static function get_active() {
		$job = self::get();
		return ( is_array( $job ) && self::STATUS_RUNNING === $job['status'] ) ? $job : null;
	}

	/**
	 * Exécute un step (jusqu'au budget). Verrou anti-concurrence.
	 *
	 * @param float|null $budget Secondes (null = réglages).
	 * @return array Statut public.
	 */
	public static function step( $budget = null ) {
		$job = self::get_active();
		if ( null === $job ) {
			return self::public_status();
		}

		// Verrou : un step à la fois (stale après 3 min).
		$lock = (int) get_transient( 'imp_job_lock' );
		if ( $lock > 0 && ( time() - $lock ) < 180 ) {
			return self::public_status();
		}
		set_transient( 'imp_job_lock', time(), 180 );

		$budget = null === $budget ? self::budget() : (float) $budget;
		$step_start = microtime( true );
		$messages   = array();

		try {
			while ( ( microtime( true ) - $step_start ) <= $budget ) {
				if ( self::STATUS_CANCELED === $job['status'] ) {
					break;
				}
				if ( $job['phase'] >= count( $job['phases'] ) ) {
					$job['status'] = self::STATUS_COMPLETED;
					break;
				}

				$phase    = $job['phases'][ $job['phase'] ];
				$phase_key = $phase['key'];
				$result    = self::run_phase( $job, $phase_key );

				if ( isset( $result['message'] ) && '' !== $result['message'] ) {
					$messages[] = $result['message'];
				}

				if ( isset( $result['error'] ) ) {
					$job['status'] = self::STATUS_FAILED;
					$job['error']  = $result['error'];
					$messages[]    = self::error_text( $result['error'] );
					break;
				}

				$job['steps']++;

				if ( ! empty( $result['done'] ) ) {
					$job['phase']++;
					$job['state'][ '_pct_' . $phase_key ] = 100;
					// Petites phases : on enchaîne dans le même step.
					continue;
				}

				$job['state'][ '_pct_' . $phase_key ] = isset( $result['percent'] ) ? (float) $result['percent'] : 0.0;
				$job['state'][ '_msg_' . $phase_key ] = isset( $result['message'] ) ? $result['message'] : '';
				break; // budget atteint au milieu d'une phase.
			}
		} catch ( Exception $e ) {
			$job['status'] = self::STATUS_FAILED;
			$job['error']  = 'IMP-299';
			$messages[]    = __( 'An unexpected server error interrupted the operation. You can resume it from where it stopped.', 'infinity-migratex-pro' );
			if ( imp_setting( 'debug_mode', 0 ) ) {
				$messages[] = 'Debug: ' . $e->getMessage();
			}
		}

		$job['messages'] = array_slice( array_merge( (array) $job['messages'], $messages ), -12 );

		// Statut final ?
		if ( self::STATUS_COMPLETED === $job['status'] ) {
			self::complete( $job );
		} elseif ( self::STATUS_FAILED === $job['status'] ) {
			self::fail( $job );
		}

		update_option( self::OPTION, $job, false );
		self::$job = $job;
		delete_transient( 'imp_job_lock' );

		// Job en arrière-plan (cron) : relancer la chaîne asynchrone.
		if ( 'cron' === $job['origin'] && self::STATUS_RUNNING === $job['status'] ) {
			self::kick_background( $job );
		}

		// Backup terminé avec envoi cloud demandé : enchaîner l'upload.
		if ( self::STATUS_COMPLETED === $job['status'] && 'backup' === $job['type'] && ! empty( $job['data']['remote_after'] ) ) {
			self::chain_remote( $job );
		}

		return self::public_status();
	}

	/**
	 * Enchaîne automatiquement l'upload cloud après un backup réussi
	 * (fonctionnalité Pro — vérifiée ici, côté serveur).
	 *
	 * @param array $job Job backup terminé.
	 * @return void
	 */
	private static function chain_remote( array $job ) {
		if ( ! class_exists( 'IMP_Remote' ) || ! IMP_License::is_pro() || ! IMP_Remote::is_ready() ) {
			return;
		}

		$backup_id = isset( $job['result']['backup_id'] )
			? IMP_Backup_Engine::sanitize_id( (string) $job['result']['backup_id'] )
			: ( isset( $job['data']['backup_id'] ) ? IMP_Backup_Engine::sanitize_id( (string) $job['data']['backup_id'] ) : '' );
		if ( '' === $backup_id ) {
			return;
		}

		$result = self::start(
			'remote',
			array(
				'backup'     => $backup_id,
				'keep_local' => imp_setting( 'cloud_keep_local', 1 ),
				'origin'     => isset( $job['origin'] ) ? (string) $job['origin'] : 'manual',
				'name'       => __( 'Cloud upload', 'infinity-migratex-pro' ),
			)
		);

		// En arrière-plan (cron) : relancer la chaîne asynchrone du nouveau job.
		if ( $result['ok'] && 'cron' === (string) $job['origin'] ) {
			$new = self::get_active();
			if ( null !== $new ) {
				self::kick_background( $new );
			}
		}
	}

	/**
	 * Exécute la phase courante.
	 *
	 * @param array  $job       Job (par référence).
	 * @param string $phase_key Clé de phase.
	 * @return array{done?:bool,percent?:float,message?:string,error?:string}
	 */
	private static function run_phase( array &$job, $phase_key ) {
		$result = self::run_phase_step( $job, $phase_key );

		// Auto-reprise IMP-203 (liste de fichiers illisible — typiquement
		// purgée du répertoire temporaire par le serveur entre deux steps) :
		// si AUCUN fichier n'a encore été traité, on reconstruit l'index et
		// on retente la phase une seule fois.
		$files_done = isset( $result['files_done'] ) ? (int) $result['files_done'] : -1;
		if ( isset( $result['error'] ) && 'IMP-203' === $result['error'] && 'index' !== $phase_key
			&& in_array( $job['type'], array( 'backup', 'migration' ), true )
			&& 0 >= $files_done && empty( $job['state']['_imp203_retried'] ) ) {
			$job['state']['_imp203_retried'] = 1;
			if ( isset( $job['state'][ $phase_key ] ) && is_array( $job['state'][ $phase_key ] ) ) {
				$job['state'][ $phase_key ] = array();
			}
			$rebuild = self::run_phase_step( $job, 'index' );
			if ( ! isset( $rebuild['error'] ) ) {
				$result = self::run_phase_step( $job, $phase_key );
				if ( ! isset( $result['error'] ) ) {
					$result['message'] = __( 'The file list had been removed from the temporary directory — it was rebuilt automatically and the operation resumed.', 'infinity-migratex-pro' );
				}
			}
		}

		return $result;
	}

	/**
	 * Exécute un step de phase (switch type/phase).
	 *
	 * @param array  $job       Job.
	 * @param string $phase_key Clé de phase.
	 * @return array{done?:bool,percent?:float,message?:string,error?:string}
	 */
	private static function run_phase_step( array &$job, $phase_key ) {
		$budget = self::budget();

		switch ( $job['type'] . '/' . $phase_key ) {
			/* ---------- Backup ---------- */
			case 'backup/prepare':
				return IMP_Backup_Engine::step_prepare( $job );
			case 'backup/index':
				return IMP_Backup_Engine::step_index( $job, $budget );
			case 'backup/files':
				return IMP_Backup_Engine::step_files( $job, $budget );
			case 'backup/database':
				return IMP_Backup_Engine::step_database( $job, $budget );
			case 'backup/encrypt':
				return IMP_Crypto::step_encrypt_backup( $job );
			case 'backup/finalize':
				return IMP_Backup_Engine::step_finalize( $job );

			/* ---------- Restore ---------- */
			case 'restore/prepare':
				return IMP_Backup_Engine::step_restore_prepare( $job );
			case 'restore/files':
				return IMP_Backup_Engine::step_restore_files( $job, $budget );
			case 'restore/database':
				return IMP_Backup_Engine::step_restore_database( $job, $budget );
			case 'restore/reurl':
				return IMP_Backup_Engine::step_restore_reurl( $job, $budget );
			case 'restore/integrity':
				return self::step_restore_integrity( $job );
			case 'restore/finalize':
				return self::step_restore_finalize( $job );

			/* ---------- Migration ---------- */
			case 'migration/prepare':
				return IMP_Migrator::step_prepare( $job );
			case 'migration/index':
				return IMP_Migrator::step_index( $job, $budget );
			case 'migration/files':
				return IMP_Migrator::step_files( $job, $budget );
			case 'migration/db_dump':
				return IMP_Migrator::step_db_dump( $job, $budget );
			case 'migration/db_import':
				return IMP_Migrator::step_db_import( $job, $budget );
			case 'migration/replace':
				return IMP_Migrator::step_replace( $job, $budget );
			case 'migration/wpconfig':
				return IMP_Migrator::step_wpconfig( $job );
			case 'migration/integrity':
				return IMP_Migrator::step_integrity( $job );
			case 'migration/finalize':
				return IMP_Migrator::step_finalize( $job );

			/* ---------- Scan ---------- */
			case 'scan/core':
				return IMP_Scanner::step_core( $job, $budget );
			case 'scan/files':
				return IMP_Scanner::step_files( $job, $budget );
			case 'scan/finalize':
				return IMP_Scanner::step_finalize( $job );

			/* ---------- Search & replace ---------- */
			case 'replace/prepare':
				return self::step_replace_prepare( $job );
			case 'replace/run':
				return self::step_replace_run( $job, $budget );
			case 'replace/finalize':
				return self::step_replace_finalize( $job );

			/* ---------- Import de package ---------- */
			case 'package_import/prepare':
				return self::step_package_prepare( $job );
			case 'package_import/files':
				return IMP_Backup_Engine::step_restore_files( $job, $budget );
			case 'package_import/database':
				return IMP_Backup_Engine::step_restore_database( $job, $budget );
			case 'package_import/integrity':
				return self::step_restore_integrity( $job );
			case 'package_import/finalize':
				return self::step_restore_finalize( $job );

			/* ---------- Import SQL direct ---------- */
			case 'db_import/run':
				return self::step_db_import_run( $job, $budget );
			case 'db_import/finalize':
				return self::step_db_import_finalize( $job );

			/* ---------- Upload cloud (Pro) ---------- */
			case 'remote/prepare':
				return IMP_Remote::step_prepare( $job );
			case 'remote/upload':
				return IMP_Remote::step_upload( $job, $budget );
			case 'remote/finalize':
				return IMP_Remote::step_finalize( $job );
			}

		return array(
			'done'    => false,
			'error'   => 'IMP-224',
			'message' => '',
		);
	}

	/**
	 * Annulation propre du job actif.
	 *
	 * @return array Statut.
	 */
	public static function cancel() {
		$job = self::get_active();
		if ( null === $job ) {
			return self::public_status();
		}

		$job['status'] = self::STATUS_CANCELED;

		// Nettoyage propre au type.
		if ( 'backup' === $job['type'] && ! empty( $job['data']['backup_id'] ) ) {
			IMP_Backup_Engine::delete( $job['data']['backup_id'] );
			@wp_delete_file( IMP_Security::tmp_dir() . $job['data']['backup_id'] . '-filelist.jsonl' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( 'package_import' === $job['type'] && ! empty( $job['data']['staging_dir'] ) && is_dir( $job['data']['staging_dir'] ) ) {
			IMP_Security::rrmdir( $job['data']['staging_dir'] );
		}
		if ( 'migration' === $job['type'] ) {
			delete_transient( 'imp_migration_creds_' . $job['id'] );
		}
		if ( 'remote' === $job['type'] ) {
			delete_transient( 'imp_cloud_cfg_' . $job['id'] );
		}
		// Ne jamais laisser un site en maintenance après une annulation.
		self::remove_maintenance( $job );
		if ( in_array( (string) $job['type'], array( 'restore', 'package_import' ), true ) && ! empty( $job['data']['backup'] ) ) {
			$dec = IMP_Security::tmp_dir() . 'restore-' . IMP_Backup_Engine::sanitize_id( (string) $job['data']['backup'] );
			if ( is_dir( $dec ) ) {
				IMP_Security::rrmdir( $dec );
			}
		}

		IMP_Logger::finish(
			(int) $job['log_id'],
			IMP_Logger::STATUS_CANCELED,
			__( 'Operation canceled by the user.', 'infinity-migratex-pro' ),
			array(
				'duration' => microtime( true ) - (float) $job['started_at'],
				'details'  => array( 'steps' => (int) $job['steps'] ),
			)
		);

		update_option( self::OPTION, $job, false );
		self::$job = $job;
		delete_transient( 'imp_job_lock' );

		return self::public_status();
	}

	/**
	 * Annule sans conditions (désactivation).
	 *
	 * @return void
	 */
	public static function cancel_all() {
		$job = self::get();
		if ( $job && self::STATUS_RUNNING === $job['status'] ) {
			self::cancel();
		}
	}

	/**
	 * Efface le job terminé (nouveau départ propre).
	 *
	 * @return void
	 */
	public static function clear() {
		$job = self::get();
		if ( $job && self::STATUS_RUNNING !== $job['status'] ) {
			delete_option( self::OPTION );
			self::$job = null;
		}
	}

	/* ------------------------------------------------------------------ *
	 * Phases par type
	 * ------------------------------------------------------------------ */

	/**
	 * Le type de job existe-t-il ?
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	private static function type_exists( $type ) {
		return in_array( $type, array( 'backup', 'restore', 'migration', 'scan', 'replace', 'package_import', 'db_import', 'remote' ), true );
	}

	/**
	 * Phases + poids pour un type de job.
	 *
	 * @param string $type Type.
	 * @param array  $data Config.
	 * @return array[] {key,label,weight}
	 */
	private static function phases_for( $type, array $data ) {
		switch ( $type ) {
			case 'backup':
				$files = IMP_Backup_Engine::wants_files( $data );
				$db    = IMP_Backup_Engine::wants_database( $data );
				$enc   = ! empty( $data['encrypt'] ) ? 6 : 0;
				if ( $files && $db ) {
					return array(
						array( 'key' => 'prepare', 'label' => __( 'Preparation', 'infinity-migratex-pro' ), 'weight' => 2 ),
						array( 'key' => 'index', 'label' => __( 'File indexing', 'infinity-migratex-pro' ), 'weight' => 13 ),
						array( 'key' => 'files', 'label' => __( 'Files archive', 'infinity-migratex-pro' ), 'weight' => 45 ),
						array( 'key' => 'database', 'label' => __( 'Database', 'infinity-migratex-pro' ), 'weight' => 33 - $enc ),
						array( 'key' => 'encrypt', 'label' => __( 'AES-256 encryption', 'infinity-migratex-pro' ), 'weight' => max( 1, $enc ) ),
						array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 7 ),
					);
				}
				if ( $files ) {
					return array(
						array( 'key' => 'prepare', 'label' => __( 'Preparation', 'infinity-migratex-pro' ), 'weight' => 4 ),
						array( 'key' => 'index', 'label' => __( 'File indexing', 'infinity-migratex-pro' ), 'weight' => 26 ),
						array( 'key' => 'files', 'label' => __( 'Files archive', 'infinity-migratex-pro' ), 'weight' => 62 - $enc ),
						array( 'key' => 'encrypt', 'label' => __( 'AES-256 encryption', 'infinity-migratex-pro' ), 'weight' => max( 1, $enc ) ),
						array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 8 ),
					);
				}
				return array(
					array( 'key' => 'prepare', 'label' => __( 'Preparation', 'infinity-migratex-pro' ), 'weight' => 8 ),
					array( 'key' => 'database', 'label' => __( 'Database', 'infinity-migratex-pro' ), 'weight' => 84 - $enc ),
					array( 'key' => 'encrypt', 'label' => __( 'AES-256 encryption', 'infinity-migratex-pro' ), 'weight' => max( 1, $enc ) ),
					array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 8 ),
				);

			case 'restore':
				return array(
					array( 'key' => 'prepare', 'label' => __( 'Pre-checks', 'infinity-migratex-pro' ), 'weight' => 6 ),
					array( 'key' => 'files', 'label' => __( 'Files', 'infinity-migratex-pro' ), 'weight' => 34 ),
					array( 'key' => 'database', 'label' => __( 'Database', 'infinity-migratex-pro' ), 'weight' => 36 ),
					array( 'key' => 'reurl', 'label' => __( 'URL rewrite (cross-site)', 'infinity-migratex-pro' ), 'weight' => 12 ),
					array( 'key' => 'integrity', 'label' => __( 'Integrity check', 'infinity-migratex-pro' ), 'weight' => 6 ),
					array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 6 ),
				);

			case 'migration':
				if ( isset( $data['type'] ) && 'clone' === $data['type'] ) {
					return array(
						array( 'key' => 'prepare', 'label' => __( 'Preparation', 'infinity-migratex-pro' ), 'weight' => 2 ),
						array( 'key' => 'index', 'label' => __( 'Indexing', 'infinity-migratex-pro' ), 'weight' => 13 ),
						array( 'key' => 'files', 'label' => __( 'Copying files', 'infinity-migratex-pro' ), 'weight' => 30 ),
						array( 'key' => 'db_dump', 'label' => __( 'Exporting database', 'infinity-migratex-pro' ), 'weight' => 15 ),
						array( 'key' => 'db_import', 'label' => __( 'Importing database', 'infinity-migratex-pro' ), 'weight' => 15 ),
						array( 'key' => 'replace', 'label' => __( 'URL replacement', 'infinity-migratex-pro' ), 'weight' => 12 ),
						array( 'key' => 'wpconfig', 'label' => __( 'wp-config.php', 'infinity-migratex-pro' ), 'weight' => 3 ),
						array( 'key' => 'integrity', 'label' => __( 'Integrity', 'infinity-migratex-pro' ), 'weight' => 7 ),
						array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 3 ),
					);
				}
				return array(
					array( 'key' => 'prepare', 'label' => __( 'Preparation', 'infinity-migratex-pro' ), 'weight' => 5 ),
					array( 'key' => 'replace', 'label' => __( 'URL replacement', 'infinity-migratex-pro' ), 'weight' => 87 ),
					array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 8 ),
				);

			case 'scan':
				return array(
					array( 'key' => 'core', 'label' => __( 'WordPress core', 'infinity-migratex-pro' ), 'weight' => 25 ),
					array( 'key' => 'files', 'label' => __( 'Files scanning', 'infinity-migratex-pro' ), 'weight' => 70 ),
					array( 'key' => 'finalize', 'label' => __( 'Report', 'infinity-migratex-pro' ), 'weight' => 5 ),
				);

			case 'replace':
				return array(
					array( 'key' => 'prepare', 'label' => __( 'Preparation', 'infinity-migratex-pro' ), 'weight' => 5 ),
					array( 'key' => 'run', 'label' => __( 'Replacement', 'infinity-migratex-pro' ), 'weight' => 88 ),
					array( 'key' => 'finalize', 'label' => __( 'Report', 'infinity-migratex-pro' ), 'weight' => 7 ),
				);

			case 'package_import':
				return array(
					array( 'key' => 'prepare', 'label' => __( 'Verification', 'infinity-migratex-pro' ), 'weight' => 6 ),
					array( 'key' => 'files', 'label' => __( 'Files', 'infinity-migratex-pro' ), 'weight' => 35 ),
					array( 'key' => 'database', 'label' => __( 'Database', 'infinity-migratex-pro' ), 'weight' => 35 ),
					array( 'key' => 'integrity', 'label' => __( 'Integrity check', 'infinity-migratex-pro' ), 'weight' => 9 ),
					array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 15 ),
				);

			case 'db_import':
				return array(
					array( 'key' => 'run', 'label' => __( 'Import', 'infinity-migratex-pro' ), 'weight' => 90 ),
					array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 10 ),
				);

			case 'remote':
				return array(
					array( 'key' => 'prepare', 'label' => __( 'Verification', 'infinity-migratex-pro' ), 'weight' => 10 ),
					array( 'key' => 'upload', 'label' => __( 'Cloud upload', 'infinity-migratex-pro' ), 'weight' => 82 ),
					array( 'key' => 'finalize', 'label' => __( 'Finalization', 'infinity-migratex-pro' ), 'weight' => 8 ),
				);
		}

		return array();
	}

	/**
	 * Nettoie/valide la config entrante par type.
	 *
	 * @param string $type Type.
	 * @param array  $data Config brute.
	 * @return array
	 */
	private static function sanitize_data( $type, array $data ) {
		$clean = array();

		if ( ! empty( $data['name'] ) ) {
			$clean['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( ! empty( $data['origin'] ) ) {
			$clean['origin'] = sanitize_key( (string) $data['origin'] );
		}

		// Preset de vitesse : Safe / Balanced / Turbo (Turbo = Pro).
		$speed = isset( $data['speed'] ) ? sanitize_key( (string) $data['speed'] ) : 'balanced';
		if ( ! in_array( $speed, array( 'safe', 'balanced', 'turbo' ), true ) ) {
			$speed = 'balanced';
		}
		if ( 'turbo' === $speed && ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) ) {
			$speed = 'balanced'; // Rétrogradé côté serveur, jamais contournable.
		}
		$clean['speed'] = $speed;

		switch ( $type ) {
			case 'backup':
				$components = isset( $data['components'] ) ? (string) $data['components'] : 'full';
				if ( ! in_array( $components, array( 'full', 'files', 'database' ), true ) ) {
					$components = 'full';
				}
				$clean['components'] = $components;
				if ( ! empty( $data['selection'] ) && is_array( $data['selection'] ) ) {
					$clean['selection'] = array_values( array_filter( array_map( 'sanitize_key', $data['selection'] ) ) );
				}
				$clean['exclusions'] = imp_parse_exclusions( isset( $data['exclusions'] ) ? (string) $data['exclusions'] : imp_default_exclusions_text() );
				$clean['package']    = ! empty( $data['package'] );
				$clean['remote_after'] = ! empty( $data['remote_after'] );
				// Chiffrement AES-256 (Pro) — mot de passe chiffré immédiatement.
				$clean['encrypt'] = ! empty( $data['encrypt'] ) && class_exists( 'IMP_License' ) && IMP_License::is_pro() ? 1 : 0;
				$clean['enc_pass_enc'] = ( 1 === $clean['encrypt'] && ! empty( $data['enc_pass'] ) )
					? IMP_Security::encrypt( (string) $data['enc_pass'] )
					: '';
				break;

			case 'restore':
				$clean['backup'] = IMP_Backup_Engine::sanitize_id( isset( $data['backup'] ) ? (string) $data['backup'] : '' );
				$clean['maintenance'] = ! isset( $data['maintenance'] ) || ! empty( $data['maintenance'] ) ? 1 : 0;
				$clean['reurl'] = ! empty( $data['reurl'] ) ? 1 : 0;
				// Mot de passe de décryptage (backup chiffré) : chiffré immédiatement.
				$clean['enc_pass_enc'] = ! empty( $data['enc_pass'] ) ? IMP_Security::encrypt( (string) $data['enc_pass'] ) : '';
				if ( ! empty( $data['restore_components'] ) && is_array( $data['restore_components'] ) ) {
					$clean['restore_components'] = array_values( array_filter( array_map( 'sanitize_key', $data['restore_components'] ) ) );
				}
				break;

			case 'migration':
				$mtype = isset( $data['type'] ) ? (string) $data['type'] : 'domain';
				$clean['type'] = in_array( $mtype, array( 'domain', 'clone' ), true ) ? $mtype : 'domain';
				$clean['old_url'] = esc_url_raw( isset( $data['old_url'] ) ? (string) $data['old_url'] : home_url() );
				$clean['new_url'] = esc_url_raw( isset( $data['new_url'] ) ? (string) $data['new_url'] : '' );
				if ( 'clone' === $clean['type'] ) {
					$clean['dest_path'] = imp_normalize_path( isset( $data['dest_path'] ) ? (string) $data['dest_path'] : '' );
					$clean['db_prefix'] = preg_replace( '/[^A-Za-z0-9_]/', '', isset( $data['db_prefix'] ) ? (string) $data['db_prefix'] : 'wp_' );
					$clean['db_overwrite'] = empty( $data['db_overwrite'] ) ? 0 : 1;
					if ( empty( $clean['db_prefix'] ) ) {
						$clean['db_prefix'] = 'wp_';
					}
					// Identifiants transmis séparément, jamais persistés en clair :
					// ils sont chiffrés à la phase prepare, on ne les garde pas ici.
				}
				if ( ! empty( $data['selection'] ) && is_array( $data['selection'] ) ) {
					$clean['selection'] = array_values( array_filter( array_map( 'sanitize_key', $data['selection'] ) ) );
				}
				break;

			case 'scan':
				break;

			case 'replace':
				$clean['from'] = (string) ( isset( $data['from'] ) ? wp_unslash( $data['from'] ) : '' );
				$clean['to']   = (string) ( isset( $data['to'] ) ? wp_unslash( $data['to'] ) : '' );
				$clean['dry_run'] = empty( $data['dry_run'] ) ? 0 : 1;
				$clean['json']    = isset( $data['json'] ) ? (int) (bool) $data['json'] : 1;
				break;

			case 'package_import':
				$clean['package'] = basename( sanitize_file_name( isset( $data['package'] ) ? (string) $data['package'] : '' ) );
				// Import glisser-déposer : chemin serveur validé (fichier existant
				// dans le stockage protégé), jamais accepté depuis le client.
				if ( ! empty( $data['package_path'] ) ) {
					$cand = (string) $data['package_path'];
					if ( 0 === strpos( imp_normalize_path( $cand ), imp_normalize_path( IMP_Plugin::storage_dir() ) ) && is_file( $cand ) ) {
						$clean['package_path'] = $cand;
					}
				}
				$clean['maintenance'] = empty( $data['maintenance'] ) ? 0 : 1;
				if ( ! empty( $data['restore_components'] ) && is_array( $data['restore_components'] ) ) {
					$clean['restore_components'] = array_values( array_filter( array_map( 'sanitize_key', $data['restore_components'] ) ) );
				}
				break;

			case 'db_import':
				$clean['file'] = basename( sanitize_file_name( isset( $data['file'] ) ? (string) $data['file'] : '' ) );
				break;

			case 'remote':
				$clean['backup']     = IMP_Backup_Engine::sanitize_id( isset( $data['backup'] ) ? (string) $data['backup'] : '' );
				$clean['keep_local'] = ! isset( $data['keep_local'] ) || ! empty( $data['keep_local'] ) ? 1 : 0;
				break;
		}

		/**
		 * Filtre la configuration de job nettoyée.
		 *
		 * @param array  $clean Config nettoyée.
		 * @param string $type  Type.
		 * @param array  $data  Config brute.
		 */
		return apply_filters( 'imp_job_data', $clean, $type, $data );
	}

	/* ------------------------------------------------------------------ *
	 * Phases locales (replace / imports / integrity / finalize)
	 * ------------------------------------------------------------------ */

	/**
	 * Préparation du remplacement autonome.
	 */
	private static function step_replace_prepare( array &$job ) {
		$from = trim( (string) $job['data']['from'] );
		$to   = trim( (string) $job['data']['to'] );
		if ( '' === $from || strlen( $from ) < 3 ) {
			return array( 'done' => false, 'error' => 'IMP-210', 'message' => '' );
		}
		if ( $from === $to ) {
			return array( 'done' => false, 'error' => 'IMP-210', 'message' => __( 'Search and replacement strings are identical.', 'infinity-migratex-pro' ) );
		}
		$job['state']['replace'] = array();
		return array( 'done' => true, 'message' => __( 'Replacement starts — serialized and JSON data handled safely.', 'infinity-migratex-pro' ) );
	}

	/**
	 * Exécution du remplacement autonome.
	 */
	private static function step_replace_run( array &$job, $budget ) {
		$state =& $job['state']['replace'];
		$result = IMP_URL_Replacer::run(
			array(
				'from'       => array( (string) $job['data']['from'] ),
				'to'         => array( (string) $job['data']['to'] ),
				'dry_run'    => ! empty( $job['data']['dry_run'] ),
				'json'       => ! empty( $job['data']['json'] ),
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['meta']['replace'] = $result;

		$total = 0;
		if ( ! empty( $state['tables'] ) ) {
			foreach ( $state['tables'] as $table ) {
				$total += (int) $table['rows'];
			}
		}

		return array(
			'done'    => $result['done'],
			'percent' => $total > 0 ? min( 100, 100 * $result['rows_scanned'] / $total ) : 0.0,
			'message' => sprintf(
				/* translators: 1: rows 2: values 3: table */
				__( 'Scanned %1$d rows, %2$d values updated — %3$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['rows_scanned'] ),
				number_format_i18n( $result['values_changed'] ),
				$result['current']
			),
		);
	}

	/**
	 * Rapport final du remplacement autonome.
	 */
	private static function step_replace_finalize( array &$job ) {
		$r = isset( $job['state']['meta']['replace'] ) ? $job['state']['meta']['replace'] : array();

		$job['result'] = array(
			'tables_scanned' => isset( $r['tables_scanned'] ) ? $r['tables_scanned'] : 0,
			'rows_scanned'   => isset( $r['rows_scanned'] ) ? $r['rows_scanned'] : 0,
			'rows_changed'   => isset( $r['rows_changed'] ) ? $r['rows_changed'] : 0,
			'values_changed' => isset( $r['values_changed'] ) ? $r['values_changed'] : 0,
			'errors'         => isset( $r['errors'] ) ? $r['errors'] : 0,
			'no_pk'          => isset( $r['no_pk'] ) ? $r['no_pk'] : array(),
			'dry_run'        => ! empty( $job['data']['dry_run'] ),
		);

		IMP_Integrations_WordPress::clear_known_caches();
		IMP_Integrations_WordPress::flush_rewrites();

		return array(
			'done'    => true,
			'message' => ! empty( $job['data']['dry_run'] )
				? sprintf(
					/* translators: 1: values 2: rows */
					__( 'Dry run complete: %1$d values would change in %2$d rows. Nothing was written.', 'infinity-migratex-pro' ),
					number_format_i18n( $job['result']['values_changed'] ),
					number_format_i18n( $job['result']['rows_changed'] )
				)
				: sprintf(
					/* translators: 1: values 2: rows */
					__( 'Replacement complete: %1$d values updated in %2$d rows.', 'infinity-migratex-pro' ),
					number_format_i18n( $job['result']['values_changed'] ),
					number_format_i18n( $job['result']['rows_changed'] )
				),
		);
	}

	/**
	 * Préparation de l'import de package : re-vérification + extraction
	 * des parties vers un dossier de staging protégé.
	 */
	private static function step_package_prepare( array &$job ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'done' => false, 'error' => 'IMP-205', 'message' => '' );
		}

		// Chemin serveur du package (import glisser-déposer ou stockage).
		$candidates = array();
		if ( ! empty( $job['data']['package_path'] ) && is_file( (string) $job['data']['package_path'] ) ) {
			$candidates[] = (string) $job['data']['package_path'];
		}
		if ( ! empty( $job['data']['package'] ) ) {
			$candidates[] = IMP_Package::dir() . basename( (string) $job['data']['package'] );
			$candidates[] = IMP_Security::tmp_dir() . basename( (string) $job['data']['package'] );
		}

		$source = '';
		foreach ( $candidates as $candidate ) {
			if ( is_file( $candidate ) ) {
				$source = $candidate;
				break;
			}
		}
		if ( '' === $source ) {
			return array( 'done' => false, 'error' => 'IMP-218', 'message' => __( 'Package file not found.', 'infinity-migratex-pro' ) );
		}

		$scan = IMP_Package::scan( $source );
		if ( ! $scan['ok'] ) {
			$error = isset( $scan['error'] ) ? $scan['error'] : 'IMP-217';
			return array( 'done' => false, 'error' => $error, 'message' => __( 'Package verification failed — import refused.', 'infinity-migratex-pro' ) );
		}

		// Staging protégé pour les parties internes.
		$staging = IMP_Security::tmp_dir() . 'import-' . $job['id'];
		wp_mkdir_p( $staging );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $source ) ) {
			return array( 'done' => false, 'error' => 'IMP-213', 'message' => '' );
		}

		foreach ( array( 'manifest.json', 'files.zip', 'database.sql', 'database.sql.gz' ) as $part ) {
			$stream = $zip->getStream( $part );
			if ( ! is_resource( $stream ) ) {
				continue;
			}
			$out = @fopen( $staging . '/' . $part, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( is_resource( $out ) ) {
				while ( ! feof( $stream ) ) {
					$data = fread( $stream, 1024 * 1024 );
					if ( false === $data ) {
						break;
					}
					fwrite( $out, $data );
				}
				fclose( $out );
			}
			fclose( $stream );
		}
		$zip->close();

		if ( ! is_file( $staging . '/manifest.json' ) ) {
			return array( 'done' => false, 'error' => 'IMP-212', 'message' => __( 'Package manifest missing.', 'infinity-migratex-pro' ) );
		}

		$job['data']['staging_dir'] = $staging;
		$job['data']['source_dir']  = $staging; // lu par les phases restore/files & restore/database.
		$job['state']               = array(
			'files'    => array( 'index' => 0, 'done' => 0 ),
			'database' => array(),
		);

		return array(
			'done'    => true,
			'message' => __( 'Package verified — checksums valid, extraction begins.', 'infinity-migratex-pro' ),
		);
	}

	/**
	 * Vérification post-restore/import.
	 */
	private static function step_restore_integrity( array &$job ) {
		$manifest = isset( $job['data']['manifest'] ) ? $job['data']['manifest'] : null;
		$restored = isset( $job['state']['meta']['restored_files'] ) ? (int) $job['state']['meta']['restored_files'] : 0;
		$statements = isset( $job['state']['meta']['restored_statements'] ) ? (int) $job['state']['meta']['restored_statements'] : 0;

		$db_tables = IMP_Site_Stats::db_stats();

		$job['state']['meta']['integrity'] = array(
			'restored_files'    => $restored,
			'restored_statements' => $statements,
			'db_tables_after'   => $db_tables['tables'],
			'manifest_files'    => $manifest ? (int) $manifest['counts']['files'] : 0,
			'manifest_tables'   => $manifest ? (int) $manifest['counts']['db_tables'] : 0,
		);

		return array(
			'done'    => true,
			'message' => sprintf(
				/* translators: 1: files 2: statements 3: tables */
				__( 'Integrity: %1$d files restored, %2$d SQL statements, %3$d tables online.', 'infinity-migratex-pro' ),
				number_format_i18n( $restored ),
				number_format_i18n( $statements ),
				number_format_i18n( $db_tables['tables'] )
			),
		);
	}

	/**
	 * Finalisation restore/import : caches, rewrite, rapport.
	 */
	private static function step_restore_finalize( array &$job ) {
		// Le site n'est plus en cours de restauration : sortir du mode
		// maintenance en premier.
		self::remove_maintenance( $job );

		IMP_Integrations_WordPress::clear_known_caches();
		IMP_Integrations_WordPress::flush_rewrites();

		$integrity = isset( $job['state']['meta']['integrity'] ) ? $job['state']['meta']['integrity'] : array();

		$job['result'] = array(
			'integrity'   => $integrity,
			'elementor'   => IMP_Integrations_Elementor::is_active(),
			'woocommerce' => IMP_Integrations_WooCommerce::is_active(),
		);

		// Nettoyage : staging + dossier de décryptage temporaire.
		if ( ! empty( $job['data']['staging_dir'] ) && is_dir( $job['data']['staging_dir'] ) ) {
			IMP_Security::rrmdir( $job['data']['staging_dir'] );
		}
		if ( ! empty( $job['data']['backup'] ) ) {
			$dec = IMP_Security::tmp_dir() . 'restore-' . IMP_Backup_Engine::sanitize_id( (string) $job['data']['backup'] );
			if ( is_dir( $dec ) ) {
				IMP_Security::rrmdir( $dec );
			}
		}

		$message = __( 'Restore complete — caches cleared and rewrite rules flushed.', 'infinity-migratex-pro' );

		// Cross-site : résumé du remplacement d'URLs effectué.
		if ( ! empty( $job['state']['meta']['reurl'] ) ) {
			$reurl = $job['state']['meta']['reurl'];
			$message .= ' ' . sprintf(
				/* translators: 1: values 2: url */
				__( 'Old-site URLs rewritten: %1$d values now point to %2$s.', 'infinity-migratex-pro' ),
				number_format_i18n( (int) $reurl['values_changed'] ),
				(string) $reurl['to']
			);
		}

		// Caches persistants obsolètes : ils peuvent servir l'ancien
		// contenu/DB après un restore — avertissement actionnable.
		$stale = array();
		foreach ( array( 'object-cache.php', 'advanced-cache.php' ) as $dropin ) {
			if ( file_exists( WP_CONTENT_DIR . '/' . $dropin ) ) {
				$stale[] = $dropin;
			}
		}
		if ( ! empty( $stale ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: file names */
				__( 'Warning: persistent cache drop-ins found (%s) — if the restored site misbehaves, rename them in wp-content and clear the cache.', 'infinity-migratex-pro' ),
				implode( ', ', $stale )
			);
		}

		return array(
			'done'    => true,
			'message' => $message,
		);
	}

	/**
	 * Import SQL direct — exécution.
	 */
	private static function step_db_import_run( array &$job, $budget ) {
		$file = IMP_Security::tmp_dir() . (string) $job['data']['file'];
		if ( ! is_file( $file ) ) {
			return array( 'done' => false, 'error' => 'IMP-207', 'message' => __( 'SQL file not found in protected storage.', 'infinity-migratex-pro' ) );
		}

		$state  =& $job['state']['import'];
		$state  = is_array( $state ) ? $state : array();
		$result = IMP_Database::import_chunk( array( 'file' => $file ), $state, $budget );

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['meta']['import'] = $result;

		return array(
			'done'    => $result['done'],
			'percent' => $result['total_bytes'] > 0 ? min( 100, 100 * $result['bytes'] / $result['total_bytes'] ) : 0.0,
			'message' => sprintf(
				/* translators: 1: statements 2: table */
				__( 'Importing: %1$d statements — %2$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['statements'] ),
				$result['current']
			),
		);
	}

	/**
	 * Import SQL direct — fin.
	 */
	private static function step_db_import_finalize( array &$job ) {
		$result = isset( $job['state']['meta']['import'] ) ? $job['state']['meta']['import'] : array();

		@wp_delete_file( IMP_Security::tmp_dir() . (string) $job['data']['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$job['result'] = array(
			'statements'  => isset( $result['statements'] ) ? $result['statements'] : 0,
			'tables_seen' => isset( $result['tables_seen'] ) ? $result['tables_seen'] : 0,
		);

		return array(
			'done'    => true,
			'message' => sprintf(
				/* translators: 1: statements 2: tables */
				__( 'Import complete: %1$d statements executed, %2$d tables created.', 'infinity-migratex-pro' ),
				number_format_i18n( $job['result']['statements'] ),
				number_format_i18n( $job['result']['tables_seen'] )
			),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Fin de job / statut public
	 * ------------------------------------------------------------------ */

	/**
	 * Job terminé avec succès.
	 */
	private static function complete( array &$job ) {
		$stats = array(
			'duration' => microtime( true ) - (float) $job['started_at'],
		);
		$meta = isset( $job['state']['meta'] ) ? $job['state']['meta'] : array();

		if ( isset( $meta['files_total'] ) ) {
			$stats['files_processed'] = (int) $meta['files_total'];
		} elseif ( isset( $meta['files']['total_files'] ) ) {
			$stats['files_processed'] = (int) $meta['files']['total_files'];
		}
		if ( isset( $meta['db_rows'] ) ) {
			$stats['db_rows'] = (int) $meta['db_rows'];
		} elseif ( isset( $meta['dump']['rows_done'] ) ) {
			$stats['db_rows'] = (int) $meta['dump']['rows_done'];
		}
		if ( isset( $job['result']['sizes']['total'] ) ) {
			$stats['size_bytes'] = (int) $job['result']['sizes']['total'];
		}

		IMP_Logger::finish(
			(int) $job['log_id'],
			IMP_Logger::STATUS_COMPLETED,
			end( (array) $job['messages'] ) ?: '',
			$stats
		);

		$job['finished_at'] = microtime( true );

		self::maybe_notify(
			$job,
			true,
			(string) ( end( (array) $job['messages'] ) ?: __( 'Operation completed.', 'infinity-migratex-pro' ) )
		);
	}

	/**
	 * Job échoué.
	 */
	private static function fail( array &$job ) {
		// Ne jamais laisser un site en maintenance après un échec.
		self::remove_maintenance( $job );

		IMP_Logger::finish(
			(int) $job['log_id'],
			IMP_Logger::STATUS_FAILED,
			isset( $job['error'] ) ? $job['error'] : '',
			array(
				'duration' => microtime( true ) - (float) $job['started_at'],
				'details'  => array(
					'steps' => (int) $job['steps'],
					'phase' => isset( $job['phases'][ $job['phase'] ]['key'] ) ? $job['phases'][ $job['phase'] ]['key'] : '',
				),
			)
		);
		$job['finished_at'] = microtime( true );

		self::maybe_notify(
			$job,
			false,
			isset( $job['error'] ) ? self::error_text( (string) $job['error'] ) : __( 'Operation failed.', 'infinity-migratex-pro' )
		);
	}

	/**
	 * Retire le fichier .maintenance posé par une restauration.
	 *
	 * @param array $job Job.
	 * @return void
	 */
	private static function remove_maintenance( array $job ) {
		if ( in_array( (string) $job['type'], array( 'restore', 'package_import' ), true )
			&& file_exists( ABSPATH . '.maintenance' ) ) {
			wp_delete_file( ABSPATH . '.maintenance' );
		}
	}

	/**
	 * Notification e-mail des jobs d'arrière-plan (backups programmés et
	 * opérations chaînées) — selon le réglage notify_events.
	 *
	 * @param array  $job     Job.
	 * @param bool   $ok      Succès ?
	 * @param string $summary Résumé lisible.
	 * @return void
	 */
	private static function maybe_notify( array $job, $ok, $summary ) {
		if ( 'cron' !== (string) ( isset( $job['origin'] ) ? $job['origin'] : '' ) ) {
			return; // Les opérations manuelles sont suivies à l'écran.
		}
		if ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) {
			return; // Notifications e-mail = Pro.
		}

		$mode = (string) imp_setting( 'notify_events', 'failures' );
		if ( 'none' === $mode || ( 'failures' === $mode && $ok ) ) {
			return;
		}

		$to = trim( (string) imp_setting( 'notify_email', '' ) );
		if ( '' === $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		if ( ! is_string( $to ) || '' === $to ) {
			return;
		}

		$site    = wp_parse_url( home_url(), PHP_URL_HOST );
		$subject = sprintf(
			'[%s] Infinity MigrateX Pro — %s : %s',
			(string) $site,
			(string) ( isset( $job['title'] ) ? $job['title'] : $job['type'] ),
			$ok ? 'OK' : 'FAILED'
		);
		$body = sprintf(
			"%s\n\n%s\n\n%s",
			(string) $summary,
			__( 'Details and logs: Infinity MigrateX Pro → Logs.', 'infinity-migratex-pro' ),
			sprintf( /* translators: %s: site url */ __( 'Site: %s', 'infinity-migratex-pro' ), home_url() )
		);

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Vue publique du job (sans token ni credentials).
	 *
	 * @param array $job Job.
	 * @return array
	 */
	private static function public_view( array $job ) {
		unset( $job['token'], $job['log_id'] );
		return $job;
	}

	/**
	 * Statut complet pour le JS : progression réelle + phases.
	 *
	 * @return array{status:string,type:string,phase:string,phases:array[],percent:float,message:string,messages:array[],error?:string,result?:array,elapsed:float}
	 */
	public static function public_status() {
		$job = self::get();

		if ( null === $job ) {
			return array(
				'status'   => 'none',
				'type'     => '',
				'phase'    => '',
				'phases'   => array(),
				'percent'  => 0.0,
				'message'  => '',
				'messages' => array(),
				'elapsed'  => 0.0,
			);
		}

		$phases = array();
		$done_weight = 0;
		$total_weight = 0;
		foreach ( (array) $job['phases'] as $i => $phase ) {
			$total_weight += $phase['weight'];
			$pct = isset( $job['state'][ '_pct_' . $phase['key'] ] ) ? (float) $job['state'][ '_pct_' . $phase['key'] ] : 0.0;
			if ( $i < (int) $job['phase'] || self::STATUS_COMPLETED === $job['status'] ) {
				$pct = 100.0;
			}
			if ( $i < (int) $job['phase'] ) {
				$done_weight += $phase['weight'];
			} elseif ( abs( $pct - 100.0 ) < 0.01 && self::STATUS_RUNNING === $job['status'] && $i === (int) $job['phase'] ) {
				$done_weight += $phase['weight'];
			}
			$phases[] = array(
				'key'     => $phase['key'],
				'label'   => $phase['label'],
				'percent' => round( $pct, 1 ),
				'current' => ( $i === (int) $job['phase'] && self::STATUS_RUNNING === $job['status'] ),
			);
		}

		$percent = $total_weight > 0 ? 100 * $done_weight / $total_weight : 0.0;
		$percent = min( 100.0, $percent );
		if ( self::STATUS_COMPLETED === $job['status'] ) {
			$percent = 100.0;
		}

		$current_phase_key = isset( $job['phases'][ $job['phase'] ]['key'] ) ? $job['phases'][ $job['phase'] ]['key'] : '';

		$message = '';
		if ( ! empty( $job['messages'] ) ) {
			$message = (string) end( $job['messages'] );
		}

		$status = array(
			'status'   => (string) $job['status'],
			'type'     => (string) $job['type'],
			'job_id'   => (string) $job['id'],
			'phase'    => $current_phase_key,
			'phases'   => $phases,
			'percent'  => round( $percent, 1 ),
			'message'  => $message,
			'messages' => array_values( (array) $job['messages'] ),
			'elapsed'  => round( microtime( true ) - (float) $job['started_at'], 1 ),
			'origin'   => isset( $job['origin'] ) ? (string) $job['origin'] : 'manual',
		);

		if ( self::STATUS_RUNNING !== $job['status'] && isset( $job['result'] ) ) {
			$status['result'] = $job['result'];
		}
		if ( self::STATUS_FAILED === $job['status'] ) {
			$status['error'] = isset( $job['error'] ) ? (string) $job['error'] : 'IMP-299';
			$status['error_text'] = self::error_text( $status['error'] );
		}

		return $status;
	}

	/* ------------------------------------------------------------------ *
	 * Arrière-plan (backups programmés)
	 * ------------------------------------------------------------------ */

	/**
	 * Budget de step selon réglages et limites PHP.
	 *
	 * @return float
	 */
	public static function budget() {
		$setting = (int) imp_setting( 'max_execution', 20 );
		$ini     = (int) ini_get( 'max_execution_time' );
		$limit   = ( $ini > 0 ) ? max( 5, $ini - 5 ) : $setting;
		return (float) max( 3, min( $setting, $limit, 25 ) );
	}

	/**
	 * Endpoint d'exécution en arrière-plan (token secret du job).
	 *
	 * @return void
	 */
	public static function background_step() {
		$job = self::get_active();
		if ( null === $job ) {
			wp_die( 'no job', '', array( 'response' => 204 ) );
		}

		$job_id = isset( $_GET['job'] ) ? sanitize_text_field( (string) $_GET['job'] ) : '';
		$token  = isset( $_GET['token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $_GET['token'] ) : '';

		if ( $job_id !== $job['id'] || ! hash_equals( (string) $job['token'], $token ) ) {
			wp_die( 'forbidden', '', array( 'response' => 403 ) );
		}
		if ( 'cron' !== $job['origin'] ) {
			wp_die( 'not allowed', '', array( 'response' => 403 ) );
		}

		self::step( self::budget() );
		wp_die( 'ok', '', array( 'response' => 204 ) );
	}

	/**
	 * Relance la chaîne asynchrone (POST non bloquant vers soi-même).
	 *
	 * @param array $job Job.
	 * @return void
	 */
	public static function kick_background( array $job ) {
		$url = add_query_arg(
			array(
				'action' => 'imp_bg_step',
				'job'    => rawurlencode( $job['id'] ),
				'token'  => rawurlencode( $job['token'] ),
			),
			admin_url( 'admin-post.php' )
		);
		wp_remote_post(
			$url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	/**
	 * Reprise « au fil de l'eau » si le loopback est bloqué : chaque visite
	 * admin fait avancer un job d'arrière-plan.
	 *
	 * @return void
	 */
	public static function piggyback() {
		$job = self::get_active();
		if ( null === $job ) {
			return;
		}
		// Toute origine : un onglet fermé ne doit pas laisser une
		// migration/backup bloquée indéfiniment — les visites admin
		// font avancer l'opération (le verrou évite le chevauchement
		// avec la boucle AJAX du navigateur).
		$last = (int) get_transient( 'imp_bg_last_step' );
		if ( time() - $last < 45 ) {
			return; // la boucle du navigateur ou la chaîne cron fonctionne.
		}
		set_transient( 'imp_bg_last_step', time(), 120 );
		self::step( 10 );
	}

	/* ------------------------------------------------------------------ *
	 * Catalogue d'erreurs (codes + causes + solutions)
	 * ------------------------------------------------------------------ */

	/**
	 * Message humain pour un code d'erreur IMP-XXX.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function error_text( $code ) {
		$catalog = array(
			'IMP-201' => array(
				'message' => __( 'Unable to open the file index for writing.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Possible causes: storage not writable, disk full. Fix the storage directory permissions in Settings → General.', 'infinity-migratex-pro' ),
			),
			'IMP-202' => array(
				'message' => __( 'The site directory could not be scanned.', 'infinity-migratex-pro' ),
				'hint'    => __( 'A folder may have unreadable permissions. The operation skips it and continues — retry if files are missing.', 'infinity-migratex-pro' ),
			),
			'IMP-203' => array(
				'message' => __( 'The file list is unreadable.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Restart the operation: the list is rebuilt automatically.', 'infinity-migratex-pro' ),
			),
			'IMP-204' => array(
				'message' => __( 'Unable to write a destination file.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Possible causes: insufficient permissions, disk space, filesystem restriction. Check the destination and retry.', 'infinity-migratex-pro' ),
			),
			'IMP-205' => array(
				'message' => __( 'The PHP zip extension (ZipArchive) is missing.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Ask your host to enable the “zip” PHP extension — it is required for archives.', 'infinity-migratex-pro' ),
			),
			'IMP-206' => array(
				'message' => __( 'Unable to create the archive.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Possible causes: storage not writable, disk full, file locks. Retry in a moment.', 'infinity-migratex-pro' ),
			),
			'IMP-207' => array(
				'message' => __( 'The SQL file could not be read.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Check that the file exists in the protected storage and is readable.', 'infinity-migratex-pro' ),
			),
			'IMP-208' => array(
				'message' => __( 'Target database credentials expired.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Credentials are encrypted and time-limited. Restart the migration wizard.', 'infinity-migratex-pro' ),
			),
			'IMP-209' => array(
				'message' => __( 'Connection to the target database failed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Check host, user, password and that the user has CREATE/INSERT privileges.', 'infinity-migratex-pro' ),
			),
			'IMP-210' => array(
				'message' => __( 'Invalid search/replacement pair.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Provide a search string (3 characters min) different from the replacement.', 'infinity-migratex-pro' ),
			),
			'IMP-211' => array(
				'message' => __( 'Backup manifest missing or unreadable.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The backup folder may be incomplete — create a new backup.', 'infinity-migratex-pro' ),
			),
			'IMP-212' => array(
				'message' => __( 'Invalid or missing manifest in the package.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Rebuild the package from its source site.', 'infinity-migratex-pro' ),
			),
			'IMP-213' => array(
				'message' => __( 'Archive unreadable or corrupted.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The zip could not be opened — re-download or rebuild the archive.', 'infinity-migratex-pro' ),
			),
			'IMP-214' => array(
				'message' => __( 'Package rejected: unsafe entries (path traversal).', 'infinity-migratex-pro' ),
				'hint'    => __( 'This package contains paths trying to escape the destination. Import refused in strict mode.', 'infinity-migratex-pro' ),
			),
			'IMP-215' => array(
				'message' => __( 'Package too large when decompressed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The uncompressed content exceeds the 4 GB safety limit.', 'infinity-migratex-pro' ),
			),
			'IMP-216' => array(
				'message' => __( 'Empty package: no files and no database.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Nothing to import — rebuild the package.', 'infinity-migratex-pro' ),
			),
			'IMP-217' => array(
				'message' => __( 'Integrity check failed: checksums do not match.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The archive is corrupted or was altered. Refused on purpose — rebuild or re-download it.', 'infinity-migratex-pro' ),
			),
			'IMP-218' => array(
				'message' => __( 'Backup or package not found.', 'infinity-migratex-pro' ),
				'hint'    => __( 'It may have been deleted. Refresh the list.', 'infinity-migratex-pro' ),
			),
			'IMP-219' => array(
				'message' => __( 'Backup directory could not be created.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Check storage permissions (Settings → General → backup location).', 'infinity-migratex-pro' ),
			),
			'IMP-220' => array(
				'message' => __( 'Unable to write a metadata file.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Storage may be full or read-only.', 'infinity-migratex-pro' ),
			),
			'IMP-221' => array(
				'message' => __( 'Not enough disk space to restore safely.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Free some space before restoring.', 'infinity-migratex-pro' ),
			),
			'IMP-222' => array(
				'message' => __( 'Invalid source or target URL.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Use complete absolute URLs (https://example.com).', 'infinity-migratex-pro' ),
			),
			'IMP-223' => array(
				'message' => __( 'Invalid destination path.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The path must be outside the current site root (or a clean subfolder) and writable.', 'infinity-migratex-pro' ),
			),
			'IMP-224' => array(
				'message' => __( 'Unknown operation type.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Reload the page — the request was malformed.', 'infinity-migratex-pro' ),
			),
			'IMP-230' => array(
				'message' => __( 'Another operation is already running.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Resume or cancel the current operation before starting a new one.', 'infinity-migratex-pro' ),
			),
			'IMP-225' => array(
				'message' => __( 'Preflight checks report critical issues.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Fix the reported problems (destination path, database connection…) and run the preflight again.', 'infinity-migratex-pro' ),
			),
			'IMP-301' => array(
				'message' => __( 'No file was uploaded.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Choose a file and try again.', 'infinity-migratex-pro' ),
			),
			'IMP-302' => array(
				'message' => __( 'File type not allowed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Only .infinitymigrate, .zip and .sql/.sql.gz files are accepted.', 'infinity-migratex-pro' ),
			),
			'IMP-303' => array(
				'message' => __( 'File too large.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The file exceeds the size limit — import it from the server storage instead.', 'infinity-migratex-pro' ),
			),
			'IMP-304' => array(
				'message' => __( 'Upload failed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The server rejected the upload (permissions or size limits).', 'infinity-migratex-pro' ),
			),
			'IMP-305' => array(
				'message' => __( 'The file is not a valid zip archive.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Signature check failed — re-download the package and retry.', 'infinity-migratex-pro' ),
			),
			'IMP-403' => array(
				'message' => __( 'Unauthorized request.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Your session or permissions expired — reload the page.', 'infinity-migratex-pro' ),
			),
			'IMP-241' => array(
				'message' => __( 'Cloud destinations are a Pro feature.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Activate your Pro license in Settings → Advanced to unlock Google Drive, Dropbox and FTP backups.', 'infinity-migratex-pro' ),
			),
			'IMP-242' => array(
				'message' => __( 'Could not reach the cloud destination.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Check the connection details and use the “Test connection” button in Settings → Cloud.', 'infinity-migratex-pro' ),
			),
			'IMP-243' => array(
				'message' => __( 'Cloud authentication failed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The token or password was refused — regenerate it (Drive refresh token, Dropbox token, FTP password) and test again.', 'infinity-migratex-pro' ),
			),
			'IMP-244' => array(
				'message' => __( 'The cloud upload failed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The operation is resumable: press Resume to continue from where it stopped. Check the destination storage quota if it repeats.', 'infinity-migratex-pro' ),
			),
			'IMP-245' => array(
				'message' => __( 'Cloud quota exceeded or file refused.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Free some space on the destination or raise its upload limits, then resume.', 'infinity-migratex-pro' ),
			),
			'IMP-246' => array(
				'message' => __( 'The PHP FTP extension is missing.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Ask your host to enable the “ftp” PHP extension, or pick Google Drive / Dropbox instead.', 'infinity-migratex-pro' ),
			),
			'IMP-227' => array(
				'message' => __( 'SQL import errors were detected.', 'infinity-migratex-pro' ),
				'hint'    => __( 'One or more statements could not be executed — the database may be in a mixed state. Restore the safety backup, fix the source dump, then retry.', 'infinity-migratex-pro' ),
			),
			'IMP-228' => array(
				'message' => __( 'This SQL file contains statements that are not allowed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Only standard dump statements are importable (INSERT, CREATE, UPDATE…). Dangerous constructs like LOAD DATA or INTO OUTFILE are blocked.', 'infinity-migratex-pro' ),
			),
			'IMP-250' => array(
				'message' => __( 'Too many requests — temporary lock.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Anti brute-force protection. Wait a minute and retry.', 'infinity-migratex-pro' ),
			),
			'IMP-256' => array(
				'message' => __( 'The PHP OpenSSL extension is missing.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Backup encryption needs OpenSSL — ask your host to enable it, or restore this backup with the original password on a server that has it.', 'infinity-migratex-pro' ),
			),
			'IMP-257' => array(
				'message' => __( 'Encryption failed.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The encrypted copy was removed to avoid corruption — retry the backup. Check disk space if it repeats.', 'infinity-migratex-pro' ),
			),
			'IMP-258' => array(
				'message' => __( 'Encryption password missing.', 'infinity-migratex-pro' ),
				'hint'    => __( 'An encrypted backup requires a password of at least 6 characters — an archive locked with an empty key would give false security. Enter a password or uncheck encryption.', 'infinity-migratex-pro' ),
			),
			'IMP-259' => array(
				'message' => __( 'Wrong decryption password or altered file.', 'infinity-migratex-pro' ),
				'hint'    => __( 'Enter the exact password used when the backup was created. Nothing was restored.', 'infinity-migratex-pro' ),
			),
			'IMP-299' => array(
				'message' => __( 'Unexpected server error.', 'infinity-migratex-pro' ),
				'hint'    => __( 'The operation can be resumed from where it stopped. If it repeats, enable debug mode in Settings for details.', 'infinity-migratex-pro' ),
			),
		);

		$code = (string) $code;
		if ( isset( $catalog[ $code ] ) ) {
			return $catalog[ $code ]['message'] . ' ' . $catalog[ $code ]['hint'];
		}
		return __( 'Unknown error — retry or contact support with the operation log.', 'infinity-migratex-pro' );
	}
}

/* Endpoint d'arrière-plan (jobs cron) + reprise au fil de l'eau. */
add_action( 'admin_post_imp_bg_step', array( 'IMP_Job', 'background_step' ) );
add_action( 'admin_post_nopriv_imp_bg_step', array( 'IMP_Job', 'background_step' ) );
add_action( 'admin_init', array( 'IMP_Job', 'piggyback' ), 5 );
