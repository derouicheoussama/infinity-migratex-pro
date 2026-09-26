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

/* phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.RestrictedFunctions, Squiz.PHP.DiscouragedFunctions -- Un plugin de sauvegarde doit lire/ecrire de tres gros fichiers binaires en flux (fopen/fwrite/fread) et dialoguer directement avec la base pour les dumps: WP_Filesystem et prepare() ne supportent pas ce mode. Nonces et capabilities restent verifies sur chaque action. */

/**
 * Moteur de sauvegarde / restauration / import de package.
 *
 * Un backup est un dossier protégé :
 *   backups/backup-YYYYmmdd-His-slug/manifest.json + files.zip + database.sql.gz
 * Chaque phase tourne par chunks et reprend après interruption.
 */
final class IMP_Backup_Engine {

	/* ------------------------------------------------------------------ *
	 * Inventaire
	 * ------------------------------------------------------------------ */

	/**
	 * Dossier backups.
	 *
	 * @return string
	 */
	public static function dir() {
		return IMP_Plugin::storage_dir() . 'backups/';
	}

	/** @var array|null Cache statique de la liste (durée de la requête). */
	private static $list_cache = null;

	/**
	 * Liste réelle des backups (dossiers avec manifest valide).
	 *
	 * PERFORMANCE — le scan du dossier + lecture de tous les manifests est
	 * mis en cache 60 s (transient) : le dashboard, la page Backups et la
	 * restauration partagent la même liste sans re-scanner à chaque vue.
	 * Invalidation : flush_cache() après chaque mutation, et TTL court.
	 *
	 * @return array[] {id,name,created,created_ts,components,counts,sizes,checksums,type}
	 */
	public static function all() {
		if ( null !== self::$list_cache ) {
			return self::$list_cache;
		}
		$cached = get_transient( 'imp_backups_list' );
		if ( is_array( $cached ) ) {
			self::$list_cache = $cached;
			return $cached;
		}

		$out     = array();
		$dir     = self::dir();
		$entries = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || 'index.php' === $entry ) {
					continue;
				}
				$path = $dir . $entry;
				if ( ! is_dir( $path ) ) {
					continue;
				}
				$manifest = self::read_manifest( $entry );
				if ( null === $manifest ) {
					continue;
				}
				$manifest['id'] = $entry;
				$out[]          = $manifest;
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['created_ts'] <=> $a['created_ts'];
			}
		);

		set_transient( 'imp_backups_list', $out, 60 );
		self::$list_cache = $out;
		return $out;
	}

	/**
	 * Invalide le cache de la liste des backups (après création,
	 * suppression, restauration ou purge de rétention).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$list_cache = null;
		delete_transient( 'imp_backups_list' );
	}

	/**
	 * Lit le manifest d'un backup.
	 *
	 * @param string $id Nom du dossier.
	 * @return array|null
	 */
	public static function read_manifest( $id ) {
		$id   = self::sanitize_id( $id );
		$file = self::dir() . $id . '/manifest.json';
		if ( '' === $id || ! is_readable( $file ) ) {
			return null;
		}
		$manifest = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $manifest ) || empty( $manifest['created_ts'] ) ) {
			return null;
		}
		return wp_parse_args(
			$manifest,
			array(
				'name'        => $id,
				'created'     => '',
				'created_ts'  => 0,
				'type'        => 'backup',
				'components'  => array(),
				'counts'      => array(),
				'sizes'       => array(),
				'checksums'   => array(),
				'checksum_algo' => 'sha256',
				'wp_version'  => '',
				'php_version' => '',
				'plugin_version' => '',
				'site_url'    => '',
				'exclusions'  => array(),
			)
		);
	}

	/**
	 * Valide un identifiant de backup (anti-traversal).
	 *
	 * @param string $id Identifiant.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		$id = basename( (string) $id );
		return preg_match( '/^[A-Za-z0-9._\-]+$/', $id ) ? $id : '';
	}

	/**
	 * Renomme un backup (manifest uniquement).
	 *
	 * @param string $id      Identifiant.
	 * @param string $new_name Nouveau nom.
	 * @return bool
	 */
	public static function rename( $id, $new_name ) {
		$id   = self::sanitize_id( $id );
		$name = sanitize_text_field( (string) $new_name );
		if ( '' === $id || '' === $name ) {
			return false;
		}
		$manifest = self::read_manifest( $id );
		if ( null === $manifest ) {
			return false;
		}
		$manifest['name'] = $name;
		$written = false !== @file_put_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			self::dir() . $id . '/manifest.json',
			wp_json_encode( $manifest )
		);
		if ( $written ) {
			// Le nom lu par Backups/Dashboard vient du cache de liste.
			self::flush_cache();
		}
		return $written;
	}

	/**
	 * Supprime un backup.
	 *
	 * @param string $id Identifiant.
	 * @return bool
	 */
	public static function delete( $id ) {
		$id = self::sanitize_id( $id );
		if ( '' === $id ) {
			return false;
		}
		$dir = self::dir() . $id;
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$deleted = IMP_Security::rrmdir( $dir );
		if ( $deleted ) {
			self::flush_cache();
		}
		return $deleted;
	}

	/**
	 * Applique la rétention : garde les N plus récents.
	 *
	 * @param int $keep Nombre à conserver (0 = illimité).
	 * @return int Supprimés.
	 */
	public static function apply_retention( $keep ) {
		$keep = (int) $keep;
		if ( $keep < 1 ) {
			return 0;
		}
		$deleted = 0;
		$all     = self::all();
		foreach ( array_slice( $all, $keep ) as $backup ) {
			if ( self::delete( $backup['id'] ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	/**
	 * Taille réelle d'un backup sur disque.
	 *
	 * @param string $id Identifiant.
	 * @return int
	 */
	public static function disk_size( $id ) {
		$id  = self::sanitize_id( $id );
		$dir = self::dir() . $id;
		if ( '' === $id || ! is_dir( $dir ) ) {
			return 0;
		}
		$total = 0;
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iterator as $fileinfo ) {
				/** @var SplFileInfo $fileinfo */
				$total += (int) $fileinfo->getSize();
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyCatch.Detected
			// illisible : 0.
		}
		return $total;
	}

	/**
	 * Prépare l'archive zip téléchargeable d'un backup (dans tmp/) et
	 * renvoie son chemin — streaming ensuite par admin-post.
	 *
	 * @param string $id Identifiant.
	 * @return string|WP_Error
	 */
	public static function build_download_zip( $id ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'IMP-205', __( 'The PHP zip extension is required to build downloadable archives.', 'infinity-migratex-pro' ) );
		}

		$id   = self::sanitize_id( $id );
		$dir  = self::dir() . $id;
		if ( '' === $id || ! is_dir( $dir ) ) {
			return new WP_Error( 'IMP-218', __( 'Backup not found.', 'infinity-migratex-pro' ) );
		}

		IMP_Security::bootstrap_storage();
		$target = IMP_Security::tmp_dir() . 'download-' . $id . '.zip';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $target, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'IMP-206', __( 'Unable to create the download archive (storage not writable?).', 'infinity-migratex-pro' ) );
		}

		foreach ( array( 'manifest.json', 'files.zip', 'database.sql', 'database.sql.gz' ) as $part ) {
			if ( is_file( $dir . '/' . $part ) ) {
				$zip->addFile( $dir . '/' . $part, $part );
			}
		}
		$zip->close();

		if ( ! is_file( $target ) ) {
			return new WP_Error( 'IMP-206', __( 'Unable to create the download archive.', 'infinity-migratex-pro' ) );
		}
		return $target;
	}

	/* ------------------------------------------------------------------ *
	 * Phases du job « backup »
	 * ------------------------------------------------------------------ */

	/**
	 * Prépare le dossier de backup et initialise l'état.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_prepare( array &$job ) {
		IMP_Security::bootstrap_storage();

		$config = $job['data'];
		$slug   = imp_slugify( isset( $config['name'] ) && '' !== $config['name'] ? $config['name'] : __( 'backup', 'infinity-migratex-pro' ) );
		$id     = 'backup-' . gmdate( 'Ymd-His' ) . '-' . $slug;

		// Anti-collision : deux backups même seconde + même nom.
		if ( is_dir( self::dir() . $id ) ) {
			$id .= '-' . strtolower( wp_generate_password( 4, false, false ) );
		}

		// Garde-fou disque : ne pas démarrer si l'espace est critique.
		$free = IMP_Site_Stats::disk_free();
		if ( $free > 0 && $free < 50 * MB_IN_BYTES ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-221',
				'message' => __( 'Less than 50 MB free on the server — free disk space before backing up.', 'infinity-migratex-pro' ),
			);
		}

		$dir = self::dir() . $id;
		if ( ! wp_mkdir_p( $dir ) ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-219',
				'message' => __( 'Unable to create the backup directory — check storage permissions in Settings.', 'infinity-migratex-pro' ),
			);
		}

		$files_needed     = self::wants_files( $config );
		$database_needed  = self::wants_database( $config );
		$selection        = self::normalize_selection( $config );

		$job['data']['backup_id'] = $id;
		$job['data']['selection'] = $selection;
		$job['state']             = array(
			'index'    => array(),
			'files'    => array(),
			'database' => array(),
			'meta'     => array(
				'files_needed'    => $files_needed,
				'database_needed' => $database_needed,
			),
		);

		return array(
			'done'    => true,
			'message' => sprintf(
				/* translators: %s: backup id */
				__( 'Backup %s initialized.', 'infinity-migratex-pro' ),
				$id
			),
		);
	}

	/**
	 * Indexation des fichiers (reprise intégrale).
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	/**
	 * Watchdog disque : arrêt propre si l'espace devient critique
	 * (mieux vaut un backup en pause qu'un disque plein).
	 *
	 * @return array|null Erreur, ou null si OK.
	 */
	private static function disk_guard() {
		$free = IMP_Site_Stats::disk_free();
		if ( $free > 0 && $free < 50 * MB_IN_BYTES ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-221',
				'message' => __( 'Disk space critically low — the operation stopped to protect your data. Free space, then resume.', 'infinity-migratex-pro' ),
			);
		}
		return null;
	}

	/**
	 * Facteurs du preset de vitesse du job en cours.
	 *
	 * @param array $job Job.
	 * @return array{budget:float,files:int,rows:float,bytes:int}
	 */
	private static function speed_factors( array $job ) {
		$speed = isset( $job['data']['speed'] ) ? (string) $job['data']['speed'] : 'balanced';
		switch ( $speed ) {
			case 'safe':
				return array( 'budget' => 0.6, 'files' => 1, 'rows' => 0.6, 'bytes' => 1024 * 1024 );
			case 'turbo':
				return array( 'budget' => 1.15, 'files' => 3, 'rows' => 2.0, 'bytes' => 4 * 1024 * 1024 );
			default:
				return array( 'budget' => 1.0, 'files' => 1, 'rows' => 1.0, 'bytes' => 2 * 1024 * 1024 );
		}
	}

	public static function step_index( array &$job, $budget ) {
		$guard = self::disk_guard();
		if ( null !== $guard ) {
			return $guard;
		}

		$speed  = self::speed_factors( $job );
		$budget = $budget * $speed['budget'];

		$id     = $job['data']['backup_id'];
		$config = $job['data'];
		$state  =& $job['state']['index'];

		$list_file = IMP_Security::tmp_dir() . $id . '-filelist.jsonl';

		$result = IMP_Files::index(
			array(
				'root'        => ABSPATH,
				'components'  => $job['data']['selection'],
				'exclusions'  => isset( $config['exclusions'] ) ? (array) $config['exclusions'] : imp_default_exclusions(),
				'list_file'   => $list_file,
				'skip_config' => true,
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['index_totals'] = array(
			'files' => $result['files'],
			'bytes' => $result['bytes'],
		);

		return array(
			'done'    => $result['done'],
			'percent' => $result['bytes'] > 0 ? 100.0 : 100.0,
			'message' => sprintf(
				/* translators: 1: files 2: size */
				__( 'Indexing: %1$s files (%2$s).', 'infinity-migratex-pro' ),
				number_format_i18n( $result['files'] ),
				imp_format_bytes( $result['bytes'] )
			),
		);
	}

	/**
	 * Zip des fichiers par lots.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_files( array &$job, $budget ) {
		$guard = self::disk_guard();
		if ( null !== $guard ) {
			return $guard;
		}

		$speed     = self::speed_factors( $job );
		$budget    = $budget * $speed['budget'];
		$id        = $job['data']['backup_id'];
		$zip_file  = self::dir() . $id . '/files.zip';
		$list_file = IMP_Security::tmp_dir() . $id . '-filelist.jsonl';
		$state    =& $job['state']['files'];

		$result = IMP_Files::zip_chunk(
			array(
				'list_file'   => $list_file,
				'source_root' => ABSPATH,
				'zip_file'    => $zip_file,
				'chunk'       => max( 10, (int) ( imp_settings()['chunk_files'] * $speed['files'] ) ),
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$percent = $result['total_files'] > 0
			? min( 100, 100 * $result['files_done'] / max( 1, $result['total_files'] ) )
			: 100.0;

		$job['state']['meta']['files_done']  = $result['files_done'];
		$job['state']['meta']['files_total'] = $result['total_files'];
		$job['state']['meta']['zip_failed']  = $result['failed'];

		return array(
			'done'    => $result['done'],
			'percent' => $percent,
			'message' => sprintf(
				/* translators: 1: files done 2: total 3: current file */
				__( 'Files: %1$d / %2$d — %3$s', 'infinity-migratex-pro' ),
				$result['files_done'],
				$result['total_files'],
				$result['current']
			),
		);
	}

	/**
	 * Dump de la base par lots.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_database( array &$job, $budget ) {
		$guard = self::disk_guard();
		if ( null !== $guard ) {
			return $guard;
		}

		$speed    = self::speed_factors( $job );
		$budget   = $budget * $speed['budget'];
		$id       = $job['data']['backup_id'];
		$settings = imp_settings();
		$compress = ! empty( $settings['compression'] );

		$out  = self::dir() . $id . ( $compress ? '/database.sql.gz' : '/database.sql' );
		$state =& $job['state']['database'];

		$result = IMP_Database::dump_chunk(
			array(
				'out'         => $out,
				'compression' => $compress,
				'chunk_rows'  => max( 20, (int) ( $settings['chunk_db_rows'] * $speed['rows'] ) ),
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$percent = $result['total_rows'] > 0
			? min( 100, 100 * $result['rows_done'] / max( 1, $result['total_rows'] ) )
			: 100.0;

		$job['state']['meta']['db_tables']   = $result['tables_done'];
		$job['state']['meta']['db_rows']     = $result['rows_done'];
		$job['state']['meta']['db_total']    = $result['total_rows'];

		return array(
			'done'    => $result['done'],
			'percent' => $percent,
			'message' => sprintf(
				/* translators: 1: rows 2: table */
				__( 'Database: %1$d rows — table %2$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['rows_done'] ),
				$result['current']
			),
		);
	}

	/**
	 * Finalisation : checksums, manifest, package éventuel, rétention.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_finalize( array &$job ) {
		global $wp_version;

		$id         = $job['data']['backup_id'];
		$dir        = self::dir() . $id;
		$meta       = $job['state']['meta'];
		$config     = $job['data'];
		$settings   = imp_settings();
		$algo       = 'sha256';

		$checksums = array();
		foreach ( array( 'files.zip', 'database.sql', 'database.sql.gz' ) as $part ) {
			$path = $dir . '/' . $part;
			if ( is_file( $path ) ) {
				$checksums[ $part ] = hash_file( $algo, $path );
			}
		}

		// Auto-vérification : l'archive fraîchement écrite est relue et
		// son nombre d'entrées comparé à l'index (détecte immédiatement
		// une écriture silencieusement incomplète ou corrompue).
		if ( is_file( $dir . '/files.zip' ) && class_exists( 'ZipArchive' ) ) {
			$verify   = new ZipArchive();
			$opened   = $verify->open( $dir . '/files.zip' );
			$expected = (int) ( isset( $meta['files_total'] ) ? $meta['files_total'] : 0 )
				- (int) ( isset( $meta['zip_failed'] ) ? $meta['zip_failed'] : 0 );
			if ( true !== $opened ) {
				return array(
					'done'    => false,
					'error'   => 'IMP-213',
					'message' => __( 'The backup archive just written is unreadable — the storage may be failing. The backup was NOT validated.', 'infinity-migratex-pro' ),
				);
			}
			$entries = (int) $verify->numFiles;
			$verify->close();
			if ( $entries !== $expected ) {
				return array(
					'done'    => false,
					'error'   => 'IMP-213',
					'message' => sprintf(
						/* translators: 1: entries 2: expected */
						__( 'Backup archive incomplete: %1$d entries instead of %2$d expected. The backup was NOT validated — retry the backup.', 'infinity-migratex-pro' ),
						$entries,
						$expected
					),
				);
			}
		}

		$files_zip_size = is_file( $dir . '/files.zip' ) ? (int) @filesize( $dir . '/files.zip' ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$db_size        = 0;
		foreach ( array( 'database.sql.gz', 'database.sql' ) as $part ) {
			if ( is_file( $dir . '/' . $part ) ) {
				$db_size = (int) @filesize( $dir . '/' . $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		$manifest = array(
			'format'         => 1,
			'type'           => 'backup',
			'name'           => isset( $config['name'] ) && '' !== $config['name'] ? $config['name'] : $id,
			'created'        => current_time( 'mysql' ),
			'encrypted'      => ! empty( $job['data']['encrypted_done'] ),
			'created_ts'     => time(),
			'plugin_version' => IMP_VERSION,
			'wp_version'     => $wp_version,
			'php_version'    => PHP_VERSION,
			'site_url'       => home_url(),
			'abspath'        => imp_normalize_path( ABSPATH ),
			'components'     => array(
				'files'     => ! empty( $meta['files_needed'] ),
				'database'  => ! empty( $meta['database_needed'] ),
				'selection' => isset( $job['data']['selection'] ) ? $job['data']['selection'] : array(),
			),
			'exclusions'     => isset( $config['exclusions'] ) ? (array) $config['exclusions'] : array(),
			'counts'         => array(
				'files'     => isset( $meta['files_total'] ) ? (int) $meta['files_total'] : 0,
				'db_tables' => isset( $meta['db_tables'] ) ? (int) $meta['db_tables'] : 0,
				'db_rows'   => isset( $meta['db_rows'] ) ? (int) $meta['db_rows'] : 0,
			),
			'sizes'          => array(
				'files_zip'   => $files_zip_size,
				'database'    => $db_size,
				'total'       => $files_zip_size + $db_size,
			),
			'checksum_algo'  => $algo,
			'checksums'      => $checksums,
		);

		$written = @file_put_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			$dir . '/manifest.json',
			wp_json_encode( $manifest )
		);
		if ( false === $written ) {
			return array( 'done' => false, 'error' => 'IMP-220', 'message' => __( 'Unable to write the backup manifest.', 'infinity-migratex-pro' ) );
		}

		// Nettoyage des fichiers de travail.
		@wp_delete_file( IMP_Security::tmp_dir() . $id . '-filelist.jsonl' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		// Liste des backups : le nouveau dossier doit être visible partout
		// immédiatement (dashboard, restauration) — invalidation du cache.
		self::flush_cache();

		// Package .infinitymigrate demandé (export).
		if ( ! empty( $config['package'] ) ) {
			IMP_Package::create_from_backup( $dir, $manifest['name'] );
		}

		// Rétention (hors backup de sécurité).
		if ( 'safety' !== ( isset( $config['origin'] ) ? $config['origin'] : '' ) ) {
			self::apply_retention( (int) $settings['backup_retention'] );
		}

		$job['result'] = array(
			'backup_id' => $id,
			'counts'    => $manifest['counts'],
			'sizes'     => $manifest['sizes'],
		);

		return array(
			'done'    => true,
			'message' => sprintf(
				/* translators: 1: files 2: size */
				__( 'Backup complete: %1$s files, %2$s.', 'infinity-migratex-pro' ),
				number_format_i18n( $manifest['counts']['files'] ),
				imp_format_bytes( $manifest['sizes']['total'] )
			),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Phases du job « restore »
	 * ------------------------------------------------------------------ */

	/**
	 * Phase de chiffrement AES-256 des parties du backup (Pro).
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_encrypt_backup( array &$job ) {
		if ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) {
			return array( 'done' => true, 'message' => '' ); // Silencieux : rétrogradé côté sanitize.
		}
		if ( empty( $job['data']['encrypt'] ) || empty( $job['data']['enc_pass_enc'] ) ) {
			return array( 'done' => true, 'message' => '' );
		}
		if ( ! IMP_Crypto::available() ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-256',
				'message' => __( 'OpenSSL is missing — encryption impossible. Disable backup encryption or enable OpenSSL.', 'infinity-migratex-pro' ),
			);
		}

		$id       = $job['data']['backup_id'];
		$dir      = self::dir() . $id;
		$password = IMP_Security::decrypt( (string) $job['data']['enc_pass_enc'] );

		foreach ( array( 'files.zip', 'database.sql.gz', 'database.sql' ) as $part ) {
			$path = $dir . '/' . $part;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$result = IMP_Crypto::encrypt_file( $path, $path . '.enc', $password );
			if ( empty( $result['ok'] ) ) {
				return array(
					'done'    => false,
					'error'   => isset( $result['error'] ) ? $result['error'] : 'IMP-257',
					'message' => sprintf( /* translators: %s: file */ __( 'Encryption failed for %s — the backup was not validated.', 'infinity-migratex-pro' ), $part ),
				);
			}
			// Partie en clair supprimée uniquement après chiffrage réussi.
			wp_delete_file( $path );
		}

		$job['data']['encrypted_done'] = 1;

		return array(
			'done'    => true,
			'message' => __( 'Backup parts encrypted (AES-256-GCM).', 'infinity-migratex-pro' ),
		);
	}

	/**
	 * Pré-restore : manifest lu, checksums vérifiés AVANT toute écriture.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_restore_prepare( array &$job ) {
		$config = $job['data'];
		$id     = self::sanitize_id( isset( $config['backup'] ) ? $config['backup'] : '' );
		if ( '' === $id ) {
			return array( 'done' => false, 'error' => 'IMP-218', 'message' => __( 'Invalid backup reference.', 'infinity-migratex-pro' ) );
		}

		$manifest = self::read_manifest( $id );
		if ( null === $manifest ) {
			return array( 'done' => false, 'error' => 'IMP-211', 'message' => __( 'Backup manifest missing or unreadable.', 'infinity-migratex-pro' ) );
		}

		$verify = IMP_Integrity::verify_backup_dir( self::dir() . $id );
		if ( ! $verify['ok'] ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-217',
				'message' => __( 'Integrity check failed: checksums do not match — this backup is corrupted or altered. Restore refused.', 'infinity-migratex-pro' ),
			);
		}

		$job['data']['backup_id'] = $id;
		$job['data']['manifest']  = $manifest;

		// Espace disque avant restauration (décompressé estimé ×1.5).
		$needed = ( (int) $manifest['counts']['files'] > 0 ? 500 * 1024 * 1024 : 0 ); // borne basse raisonnable.
		$free   = IMP_Site_Stats::disk_free();
		if ( $free > 0 && $free < $needed ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-221',
				'message' => __( 'Not enough free disk space to restore safely.', 'infinity-migratex-pro' ),
			);
		}

		// Backup chiffré (AES-256) : vérifier le mot de passe puis
		// déchiffrer les parties vers le stockage protégé temporaire.
		if ( ! empty( $manifest['encrypted'] ) ) {
			$enc_parts = array();
			foreach ( array( 'files.zip.enc', 'database.sql.gz.enc', 'database.sql.enc' ) as $part ) {
				if ( is_file( self::dir() . $id . '/' . $part ) ) {
					$enc_parts[] = $part;
				}
			}

			if ( empty( $enc_parts ) ) {
				return array(
					'done'    => false,
					'error'   => 'IMP-259',
					'message' => __( 'This backup is flagged as encrypted but no encrypted part was found — it may be corrupted.', 'infinity-migratex-pro' ),
				);
			}

			$password = IMP_Security::decrypt( (string) ( isset( $job['data']['enc_pass_enc'] ) ? $job['data']['enc_pass_enc'] : '' ) );
			if ( '' === (string) $password ) {
				return array(
					'done'    => false,
					'error'   => 'IMP-259',
					'message' => __( 'This backup is encrypted — enter the decryption password in the restore form.', 'infinity-migratex-pro' ),
				);
			}

			$dec_dir = IMP_Security::tmp_dir() . 'restore-' . $id;
			if ( ! is_dir( $dec_dir ) && ! wp_mkdir_p( $dec_dir ) ) {
				return array( 'done' => false, 'error' => 'IMP-257', 'message' => '' );
			}

			foreach ( $enc_parts as $part ) {
				$plain_name = substr( $part, 0, -4 ); // retire ".enc".
				$result     = IMP_Crypto::decrypt_file(
					self::dir() . $id . '/' . $part,
					$dec_dir . '/' . $plain_name,
					(string) $password
				);
				if ( empty( $result['ok'] ) ) {
					return array(
						'done'    => false,
						'error'   => 'IMP-259',
						'message' => __( 'Wrong decryption password (or altered file) — decrypt failed. Nothing was restored.', 'infinity-migratex-pro' ),
					);
				}
			}

			$job['data']['source_dir'] = $dec_dir;
		}

		$job['state'] = array(
			'files'    => array( 'index' => 0, 'done' => 0 ),
			'database' => array(),
		);

		// Mode maintenance : protège le site des visites pendant que ses
		// fichiers et tables sont remplacés (retiré à la finalisation).
		if ( ! empty( $config['maintenance'] ) ) {
			@file_put_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				ABSPATH . '.maintenance',
				'<?php $upgrading = ' . time() . ';'
			);
			$job['data']['maintenance_on'] = 1;
		}

		$message = __( 'Pre-restore checks passed: manifest and checksums verified.', 'infinity-migratex-pro' );

		// Alerte honnête si le backup provient d'une autre version majeure
		// de WordPress (le schéma de base peut différer).
		$manifest_wp = isset( $manifest['wp_version'] ) ? (string) $manifest['wp_version'] : '';
		if ( '' !== $manifest_wp ) {
			global $wp_version;
			$manifest_major = implode( '.', array_slice( explode( '.', $manifest_wp ), 0, 2 ) );
			$current_major  = implode( '.', array_slice( explode( '.', (string) $wp_version ), 0, 2 ) );
			if ( 0 !== version_compare( $manifest_major, $current_major ) ) {
				/* translators: 1: backup WP version 2: current WP version */
				$message .= ' ' . sprintf( __( 'Warning: this backup comes from WordPress %1$s while this site runs %2$s — database schema differences are possible.', 'infinity-migratex-pro' ), $manifest_wp, $wp_version );
			}
		}

		return array(
			'done'    => true,
			'message' => $message,
		);
	}

	/**
	 * Restauration des fichiers — extraction chunked et validée.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_restore_files( array &$job, $budget ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'done' => false, 'error' => 'IMP-205', 'message' => '' );
		}

		// Source : dossier backup OU dossier de staging d'un package importé.
		$source_dir = isset( $job['data']['source_dir'] )
			? imp_normalize_path( (string) $job['data']['source_dir'] )
			: self::dir() . self::sanitize_id( isset( $job['data']['backup'] ) ? $job['data']['backup'] : '' );
		$zipfile    = $source_dir . '/files.zip';

		if ( ! is_file( $zipfile ) ) {
			// Backup base seule : phase vide, terminée.
			return array( 'done' => true, 'message' => __( 'No files part in this backup.', 'infinity-migratex-pro' ) );
		}

		$components = isset( $job['data']['restore_components'] ) ? (array) $job['data']['restore_components'] : null;

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zipfile ) ) {
			return array( 'done' => false, 'error' => 'IMP-213', 'message' => __( 'Backup archive unreadable.', 'infinity-migratex-pro' ) );
		}

		$state    =& $job['state']['files'];
		$started  = microtime( true );
		$total    = (int) $zip->numFiles;
		$index    = isset( $state['index'] ) ? (int) $state['index'] : 0;
		$done_n   = isset( $state['done'] ) ? (int) $state['done'] : 0;
		$skipped  = 0;
		$complete = false;

		$root = imp_normalize_path( ABSPATH );

		try {
			while ( $index < $total && microtime( true ) - $started <= $budget ) {
				$entry = $zip->statIndex( $index );
				$name  = is_array( $entry ) && isset( $entry['name'] ) ? (string) $entry['name'] : '';

				// Les entrées sont préfixées "files/" dans les backups.
				$rel = ( 0 === strpos( $name, 'files/' ) ) ? substr( $name, 6 ) : $name;

				$safe = imp_validate_zip_entry( $rel );
				if ( false !== $safe && '' !== $safe && 'wp-config.php' !== $safe ) {
					if ( null === $components || IMP_Files::should_include( $safe, $components ) || preg_match( '#^wp-content/#', $safe ) ) {
						$dest = $root . '/' . $safe;

						$dir = dirname( $dest );
						if ( ! is_dir( $dir ) ) {
							wp_mkdir_p( $dir );
						}

						$stream = $zip->getStream( $name );
						if ( is_resource( $stream ) ) {
							$out = @fopen( $dest, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
							if ( is_resource( $out ) ) {
								while ( ! feof( $stream ) ) {
									$data = fread( $stream, 1024 * 1024 );
									if ( false === $data ) {
										break;
									}
									fwrite( $out, $data );
									if ( microtime( true ) - $started > $budget ) {
										break;
									}
								}
								fclose( $out );
								$done_n++;
							}
							fclose( $stream );
						}
					} else {
						$skipped++;
					}
				} else {
					$skipped++;
				}

				$index++;
			}

			$complete = $index >= $total;
		} finally {
			$zip->close();
		}

		$state['index'] = $index;
		$state['done']  = $done_n;

		$job['state']['meta']['restored_files'] = $done_n;

		return array(
			'done'    => $complete,
			'percent' => $total > 0 ? min( 100, 100 * $index / $total ) : 100.0,
			'message' => sprintf(
				/* translators: 1: entries processed 2: total */
				__( 'Restoring files: %1$d / %2$d entries.', 'infinity-migratex-pro' ),
				$index,
				$total
			),
		);
	}

	/**
	 * Restauration cross-site : détecte l'ancienne URL dans la base
	 * restaurée et la remplace par l'URL du site courant (resumable).
	 *
	 * Cas d'usage : backup exporté du site A, restauré sur le site B —
	 * toutes les URLs de l'ancien site sont réécrites vers le nouveau.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_restore_reurl( array &$job, $budget ) {
		global $wpdb;

		// Cross-site URL rewrite = fonctionnalité Pro (vérifiée serveur).
		if ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) {
			$job['data']['reurl_done'] = 1;
			return array(
				'done'    => true,
				'percent' => 100.0,
				'message' => __( 'Cross-site URL rewrite is a Pro feature — the restore is complete but old-site URLs were kept. Upgrade to rewrite them automatically.', 'infinity-migratex-pro' ),
			);
		}

		// Option désactivée ou déjà traitée.
		if ( empty( $job['data']['reurl'] ) || ! empty( $job['data']['reurl_done'] ) ) {
			return array( 'done' => true, 'message' => '', 'percent' => 100.0 );
		}

		// L'URL actuelle ne doit jamais être écrasée par le detect.
		$current = untrailingslashit( home_url() );

		if ( empty( $job['data']['reurl_from'] ) ) {
			// Détection : siteurl dans la base restaurée.
			$old = $wpdb->get_var( "SELECT option_value FROM `{$wpdb->prefix}options` WHERE option_name = 'siteurl' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_string( $old ) || '' === $old ) {
				$old = $wpdb->get_var( "SELECT option_value FROM `{$wpdb->prefix}options` WHERE option_name = 'home' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			if ( ! is_string( $old ) || '' === $old ) {
				$job['data']['reurl_done'] = 1;
				return array( 'done' => true, 'message' => __( 'No old site URL detected in the restored database — nothing to rewrite.', 'infinity-migratex-pro' ), 'percent' => 100.0 );
			}

			$old = untrailingslashit( esc_url_raw( $old ) );
			if ( '' === $old || 0 === strcasecmp( $old, $current ) ) {
				$job['data']['reurl_done'] = 1;
				return array( 'done' => true, 'message' => __( 'Restored URLs already match this site — no rewrite needed.', 'infinity-migratex-pro' ), 'percent' => 100.0 );
			}

			$variants          = imp_url_variants( $old, $current );
			$job['data']['reurl_from'] = $variants['from'];
			$job['data']['reurl_to']   = $variants['to'];
			$job['state']['reurl']     = array();
		}

		$state  =& $job['state']['reurl'];
		$result = IMP_URL_Replacer::run(
			array(
				'from' => (array) $job['data']['reurl_from'],
				'to'   => (array) $job['data']['reurl_to'],
				'json' => true,
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['meta']['reurl'] = array(
			'from'           => (array) $job['data']['reurl_from'],
			'to'             => $current,
			'rows_changed'   => (int) $result['rows_changed'],
			'values_changed' => (int) $result['values_changed'],
			'errors'         => (int) $result['errors'],
		);

		$total_rows = 0;
		if ( ! empty( $state['tables'] ) ) {
			foreach ( $state['tables'] as $table ) {
				$total_rows += (int) $table['rows'];
			}
		}
		$percent = $total_rows > 0 ? min( 100.0, 100.0 * $result['rows_scanned'] / $total_rows ) : 100.0;

		if ( ! empty( $result['done'] ) ) {
			$job['data']['reurl_done'] = 1;

			// Les URLs ont changé : vider les caches qui pourraient
			// servir l'ancien domaine.
			IMP_Integrations_WordPress::clear_known_caches();

			return array(
				'done'    => true,
				'percent' => 100.0,
				'message' => sprintf(
					/* translators: 1: values 2: url */
					__( 'Cross-site restore: %1$d URL values rewritten to %2$s.', 'infinity-migratex-pro' ),
					number_format_i18n( (int) $result['values_changed'] ),
					$current
				),
			);
		}

		return array(
			'done'    => false,
			'percent' => $percent,
			'message' => sprintf(
				/* translators: 1: rows 2: values 3: table */
				__( 'Rewriting old-site URLs: %1$d rows scanned, %2$d values updated — %3$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['rows_scanned'] ),
				number_format_i18n( $result['values_changed'] ),
				$result['current']
			),
		);
	}

	/**
	 * Restauration de la base.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_restore_database( array &$job, $budget ) {
		$source_dir = isset( $job['data']['source_dir'] )
			? imp_normalize_path( (string) $job['data']['source_dir'] )
			: self::dir() . self::sanitize_id( isset( $job['data']['backup'] ) ? $job['data']['backup'] : '' );
		$gz    = $source_dir . '/database.sql.gz';
		$plain = $source_dir . '/database.sql';
		$file  = is_file( $gz ) ? $gz : ( is_file( $plain ) ? $plain : '' );

		if ( '' === $file ) {
			return array( 'done' => true, 'message' => __( 'No database part in this backup.', 'infinity-migratex-pro' ) );
		}

		$state =& $job['state']['database'];
		$result = IMP_Database::import_chunk( array( 'file' => $file ), $state, $budget );

		if ( isset( $result['error'] ) ) {
			return array(
				'done'    => false,
				'error'   => $result['error'],
				'message' => ( 'IMP-227' === $result['error'] || 'IMP-228' === $result['error'] )
					? sprintf(
						/* translators: 1: failed count 2: SQL error */
						__( '%1$d SQL statements failed — the import stopped to protect your data. Last error: %2$s', 'infinity-migratex-pro' ),
						(int) ( isset( $result['errors'] ) ? $result['errors'] : 0 ),
						(string) ( isset( $result['last_error'] ) ? $result['last_error'] : '' )
					)
					: '',
			);
		}

		// Import terminé mais des statements ont échoué en chemin :
		// la restauration n'est PAS déclarée réussie.
		if ( ! empty( $result['done'] ) && ! empty( $result['errors'] ) ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-227',
				'message' => sprintf(
					/* translators: 1: failed count 2: SQL error */
					__( '%1$d SQL statements failed during the import. Last error: %2$s', 'infinity-migratex-pro' ),
					(int) $result['errors'],
					(string) ( isset( $result['last_error'] ) ? $result['last_error'] : '' )
				),
			);
		}

		$job['state']['meta']['restored_statements'] = $result['statements'];

		return array(
			'done'    => $result['done'],
			'percent' => $result['total_bytes'] > 0 ? min( 100, 100 * $result['bytes'] / $result['total_bytes'] ) : 100.0,
			'message' => sprintf(
				/* translators: 1: statements 2: table */
				__( 'Importing database: %1$d statements — %2$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['statements'] ),
				$result['current']
			),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Helpers de configuration
	 * ------------------------------------------------------------------ */

	/**
	 * Le backup veut-il des fichiers ?
	 *
	 * @param array $config Config.
	 * @return bool
	 */
	public static function wants_files( array $config ) {
		$c = isset( $config['components'] ) ? (string) $config['components'] : 'full';
		return 'database' !== $c;
	}

	/**
	 * Le backup veut-il la base ?
	 *
	 * @param array $config Config.
	 * @return bool
	 */
	public static function wants_database( array $config ) {
		$c = isset( $config['components'] ) ? (string) $config['components'] : 'full';
		return 'files' !== $c;
	}

	/**
	 * Normalise la sélection de zones depuis la config.
	 *
	 * @param array $config Config.
	 * @return array<string>
	 */
	public static function normalize_selection( array $config ) {
		if ( ! empty( $config['selection'] ) && is_array( $config['selection'] ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $config['selection'] ) ) );
		}

		switch ( isset( $config['components'] ) ? (string) $config['components'] : 'full' ) {
			case 'files':
			case 'full':
				return array( 'core', 'plugins', 'themes', 'uploads', 'mu-plugins', 'wpcontent' );
			default:
				return array();
		}
	}
}
