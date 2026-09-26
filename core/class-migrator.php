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
 * Orchestrateur de migration : vérifications pré-vol réelles, puis
 * exécution par phases reprise-où-on-s'était-arrêté.
 *
 * Types :
 *  - domain : changement de domaine/URL sur ce site ;
 *  - clone  : copie du site vers un autre dossier du même serveur
 *             (nouvelle base de données requise, identifiants chiffrés
 *             puis détruits) ;
 *  - export : géré comme un backup avec option « package ».
 */
final class IMP_Migrator {

	/**
	 * Vérifications pré-vol complètes (résultats réels, jamais simulés).
	 *
	 * @param array $config {type:domain|clone|export, old_url, new_url, dest_path, db:{host,user,pass,name}}.
	 * @return array{checks:array[],summary:array}
	 */
	public static function preflight( array $config ) {
		$checks = array();
		$type   = isset( $config['type'] ) ? (string) $config['type'] : 'domain';

		$stats = IMP_Site_Stats::get();

		/* ---------- Communs ---------- */

		$checks[] = array(
			'id'     => 'disk',
			'label'  => __( 'Free disk space', 'infinity-migratex-pro' ),
			'status' => self::disk_status( $stats['bytes'] ),
			'value'  => imp_format_bytes( IMP_Site_Stats::disk_free() ),
			'hint'   => sprintf(
				/* translators: %s: site size */
				__( 'Site size: %s — at least twice as much free space is recommended for a safe migration.', 'infinity-migratex-pro' ),
				imp_format_bytes( $stats['bytes'] )
			),
		);

		$zip_ok = (bool) IMP_Compatibility::php_modules()['zip'];
		if ( 'export' === $type || 'clone' === $type ) {
			$checks[] = array(
				'id'     => 'zip',
				'label'  => __( 'ZipArchive extension', 'infinity-migratex-pro' ),
				'status' => $zip_ok ? 'pass' : 'error',
				'value'  => $zip_ok ? __( 'Available', 'infinity-migratex-pro' ) : __( 'Missing', 'infinity-migratex-pro' ),
				'hint'   => __( 'Required to build the files archive of the migration package.', 'infinity-migratex-pro' ),
			);
		}

		/* ---------- Tables sans clé primaire (dump par OFFSET) ---------- */

		$no_pk = self::tables_without_pk();
		$checks[] = array(
			'id'     => 'no_pk',
			'label'  => __( 'Tables without primary key', 'infinity-migratex-pro' ),
			'status' => empty( $no_pk ) ? 'pass' : 'warn',
			'value'  => empty( $no_pk ) ? __( 'None', 'infinity-migratex-pro' ) : implode( ', ', array_slice( $no_pk, 0, 5 ) ) . ( count( $no_pk ) > 5 ? '…' : '' ),
			'hint'   => empty( $no_pk )
				? __( 'All tables have a primary key: resumable chunked dumps are exact.', 'infinity-migratex-pro' )
				: __( 'These tables are dumped with OFFSET pagination: avoid editing the site while the migration runs.', 'infinity-migratex-pro' ),
		);

		/* ---------- Très gros fichiers ---------- */

		$large = self::large_files( 50 * MB_IN_BYTES, 5 );
		$checks[] = array(
			'id'     => 'large_files',
			'label'  => __( 'Very large files (> 50 MB)', 'infinity-migratex-pro' ),
			'status' => empty( $large ) ? 'pass' : 'warn',
			/* translators: %d: number of large files. */
			'value'  => empty( $large ) ? __( 'None', 'infinity-migratex-pro' ) : sprintf( _n( '%d file', '%d files', count( $large ), 'infinity-migratex-pro' ), count( $large ) ),
			'hint'   => empty( $large )
				? __( 'No unusually large files — copy will be smooth.', 'infinity-migratex-pro' )
				: implode( ', ', $large ),
		);

		/* ---------- Plugins de cache actifs (avertissement) ---------- */

		$cached = IMP_Integrations_WordPress::active_cache_plugins();
		$checks[] = array(
			'id'     => 'cache_plugins',
			'label'  => __( 'Caching plugins active', 'infinity-migratex-pro' ),
			'status' => empty( $cached ) ? 'pass' : 'warn',
			'value'  => empty( $cached ) ? __( 'None', 'infinity-migratex-pro' ) : implode( ', ', $cached ),
			'hint'   => empty( $cached )
				? __( 'No cache layer will serve stale URLs during the migration.', 'infinity-migratex-pro' )
				: __( 'Consider enabling maintenance mode or clearing caches before and after the migration.', 'infinity-migratex-pro' ),
		);

		/* ---------- Spécifiques par type ---------- */

		if ( 'domain' === $type ) {
			$old = isset( $config['old_url'] ) ? trim( (string) $config['old_url'] ) : home_url();
			$new = isset( $config['new_url'] ) ? trim( (string) $config['new_url'] ) : '';

			$new_valid = false !== filter_var( $new, FILTER_VALIDATE_URL ) && ( 0 === strpos( $new, 'http://' ) || 0 === strpos( $new, 'https://' ) );
			$checks[]  = array(
				'id'     => 'new_url',
				'label'  => __( 'New URL', 'infinity-migratex-pro' ),
				'status' => ( $new_valid && untrailingslashit( $new ) !== untrailingslashit( $old ) ) ? 'pass' : 'error',
				'value'  => $new,
				'hint'   => $new_valid
					? __( 'Valid absolute URL (http/https), different from the current URL.', 'infinity-migratex-pro' )
					: __( 'Enter a valid absolute URL, e.g. https://newsite.com — different from the current one.', 'infinity-migratex-pro' ),
			);

			if ( $new_valid && 0 === strpos( $old, 'https://' ) && 0 === strpos( $new, 'http://' ) ) {
				$checks[] = array(
					'id'     => 'https_downgrade',
					'label'  => __( 'HTTPS → HTTP downgrade', 'infinity-migratex-pro' ),
					'status' => 'warn',
					'value'  => __( 'Downgrade detected', 'infinity-migratex-pro' ),
					'hint'   => __( 'Migrating from HTTPS to HTTP is possible but discouraged: browsers and CDN caches may break. A valid HTTPS certificate on the new domain is strongly recommended.', 'infinity-migratex-pro' ),
				);
			}
		}

		if ( 'clone' === $type ) {
			$dest = isset( $config['dest_path'] ) ? trim( (string) $config['dest_path'] ) : '';
			$dest = imp_normalize_path( $dest );
			$src  = imp_normalize_path( ABSPATH );

			$status = 'error';
			$value  = $dest;
			$hint   = __( 'Enter an absolute path on this server, different from the current site.', 'infinity-migratex-pro' );

			if ( '' !== $dest ) {
				if ( $dest === $src ) {
					$hint = __( 'The destination path is identical to the source site.', 'infinity-migratex-pro' );
				} elseif ( 0 === strpos( $src . '/', $dest . '/' ) ) {
					$status = 'error';
					$hint   = __( 'The destination contains the source site — restoring a site inside itself is refused.', 'infinity-migratex-pro' );
				} else {
					$exists = is_dir( $dest );
					if ( ! $exists ) {
						$made = wp_mkdir_p( $dest );
						if ( ! $made ) {
							$hint = __( 'The destination directory cannot be created — check parent permissions.', 'infinity-migratex-pro' );
						}
					}
					if ( $exists || is_dir( $dest ) ) {
						$writable = wp_is_writable( $dest );
						$status   = $writable ? 'pass' : 'error';
						$hint     = $writable
							? ( 0 === strpos( $dest . '/', $src . '/' )
								? __( 'Writable — note: the destination is inside the source site; it will be excluded from the copy automatically.', 'infinity-migratex-pro' )
								: __( 'Writable destination — files and a fresh wp-config.php will be created there.', 'infinity-migratex-pro' ) )
							: __( 'The destination is not writable by PHP.', 'infinity-migratex-pro' );
					}
				}
			}

			$checks[] = array(
				'id'     => 'dest_path',
				'label'  => __( 'Destination path', 'infinity-migratex-pro' ),
				'status' => $status,
				'value'  => '' !== $value ? $value : __( 'Not set', 'infinity-migratex-pro' ),
				'hint'   => $hint,
			);

			// Base cible.
			$db = isset( $config['db'] ) && is_array( $config['db'] ) ? $config['db'] : array();
			$db_status = 'error';
			$db_value  = __( 'Not tested', 'infinity-migratex-pro' );
			$db_hint   = __( 'Enter the target database credentials — they are encrypted immediately and destroyed at the end of the migration.', 'infinity-migratex-pro' );

			if ( ! empty( $db['host'] ) && ! empty( $db['user'] ) && ! empty( $db['name'] ) ) {
				$connect = IMP_Database::connect_target( $db );
				if ( is_string( $connect ) ) {
					$db_hint = __( 'Connection to the target database failed — check host, user, password and that the user has CREATE privileges.', 'infinity-migratex-pro' );
				} else {
					/** @var wpdb $connect */
					$tables = $connect->get_col( 'SHOW TABLES' );
					$tables = is_array( $tables ) ? $tables : array();
					$prefix = isset( $config['db_prefix'] ) && '' !== trim( (string) $config['db_prefix'] ) ? trim( (string) $config['db_prefix'] ) : null;
					$collide = null;
					if ( null !== $prefix ) {
						foreach ( $tables as $table ) {
							if ( 0 === strpos( (string) $table, $prefix ) ) {
								$collide = (string) $table;
								break;
							}
						}
					}
					if ( null !== $collide && empty( $config['db_overwrite'] ) ) {
						$db_status = 'error';
						$db_value  = sprintf( /* translators: %s: table */ __( 'Table %s already exists', 'infinity-migratex-pro' ), $collide );
						$db_hint   = __( 'The target database already contains tables with this prefix. Empty it first or explicitly allow overwriting.', 'infinity-migratex-pro' );
					} else {
						$db_status = 'pass';
						$db_value  = $connect->db_version();
						$db_hint   = __( 'Connected — the target database is ready to receive the migrated tables.', 'infinity-migratex-pro' );
					}
				}
			}

			$checks[] = array(
				'id'     => 'target_db',
				'label'  => __( 'Target database', 'infinity-migratex-pro' ),
				'status' => $db_status,
				'value'  => $db_value,
				'hint'   => $db_hint,
			);

			$new_url = isset( $config['new_url'] ) ? trim( (string) $config['new_url'] ) : '';
			$checks[] = array(
				'id'     => 'clone_url',
				'label'  => __( 'URL of the clone', 'infinity-migratex-pro' ),
				'status' => ( false !== filter_var( $new_url, FILTER_VALIDATE_URL ) && ( 0 === strpos( $new_url, 'http://' ) || 0 === strpos( $new_url, 'https://' ) ) ) ? 'pass' : 'error',
				'value'  => '' !== $new_url ? $new_url : __( 'Not set', 'infinity-migratex-pro' ),
				'hint'   => __( 'The full URL where the clone will be served (domain + folder), used to rewrite all URLs in the target database.', 'infinity-migratex-pro' ),
			);
		}

		$summary = array(
			'pass' => 0,
			'warn' => 0,
			'error' => 0,
		);
		foreach ( $checks as $check ) {
			$summary[ $check['status'] ]++;
		}

		/**
		 * Filtre les vérifications pré-vol.
		 *
		 * @param array $checks  Vérifications.
		 * @param array $config Configuration de migration.
		 */
		$checks = apply_filters( 'imp_preflight_checks', $checks, $config );

		return array(
			'checks'  => $checks,
			'summary' => $summary,
		);
	}

	/**
	 * Tables sans clé primaire (dump par OFFSET).
	 *
	 * @return array<string>
	 */
	public static function tables_without_pk() {
		global $wpdb;
		$out = array();
		foreach ( IMP_Database::site_tables() as $table ) {
			$pk = $wpdb->get_row( $wpdb->prepare( 'SHOW INDEX FROM `' . $table . '` WHERE Key_name = %s', 'PRIMARY' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $pk || empty( $pk['Column_name'] ) ) {
				$out[] = $table;
			}
		}
		return $out;
	}

	/**
	 * Les N plus gros fichiers du site (chemins réels).
	 *
	 * @param int  $min_bytes Taille min.
	 * @param int  $limit     Nombre max.
	 * @return array<string>
	 */
	public static function large_files( $min_bytes, $limit = 5 ) {
		$out  = array();
		$root = imp_normalize_path( ABSPATH );
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			$sizes = array();
			foreach ( $iterator as $fileinfo ) {
				/** @var SplFileInfo $fileinfo */
				try {
					if ( ! $fileinfo->isFile() ) {
						continue;
					}
					$size = $fileinfo->getSize();
				} catch ( RuntimeException $e ) {
					continue;
				}
				if ( $size >= $min_bytes ) {
					$sizes[] = array( $fileinfo->getPathname(), $size );
				}
			}
			usort(
				$sizes,
				static function ( $a, $b ) {
					return $b[1] <=> $a[1];
				}
			);
			foreach ( array_slice( $sizes, 0, $limit ) as $item ) {
				$out[] = basename( $item[0] ) . ' (' . imp_format_bytes( $item[1] ) . ')';
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyCatch.Detected
			// illisible : liste vide.
		}
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Phases du job « migration »
	 * ------------------------------------------------------------------ */

	/**
	 * Préparation : validation, creds chiffrés, état initial.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_prepare( array &$job ) {
		IMP_Security::bootstrap_storage();

		$config = $job['data'];
		$type   = isset( $config['type'] ) ? (string) $config['type'] : 'domain';

		$job['state'] = array();

		if ( 'domain' === $type ) {
			$variants = imp_url_variants( $config['old_url'], $config['new_url'] );
			if ( empty( $variants['from'] ) ) {
				return array( 'done' => false, 'error' => 'IMP-222', 'message' => __( 'Invalid source or target URL.', 'infinity-migratex-pro' ) );
			}
			$job['data']['variants'] = $variants;
			$job['state']            = array( 'replace' => array() );

			return array(
				'done'    => true,
				'message' => __( 'Preparation complete — URL replacement starts now.', 'infinity-migratex-pro' ),
			);
		}

		if ( 'clone' === $type ) {
			$dest = imp_normalize_path( isset( $config['dest_path'] ) ? (string) $config['dest_path'] : '' );
			$src  = imp_normalize_path( ABSPATH );

			if ( '' === $dest || $dest === $src || 0 === strpos( $src . '/', $dest . '/' ) ) {
				return array( 'done' => false, 'error' => 'IMP-223', 'message' => __( 'Invalid destination path.', 'infinity-migratex-pro' ) );
			}
			if ( ! is_dir( $dest ) && ! wp_mkdir_p( $dest ) ) {
				return array( 'done' => false, 'error' => 'IMP-204', 'message' => __( 'Destination directory cannot be created.', 'infinity-migratex-pro' ) );
			}

			// Identifiants cible : déjà chiffrés au démarrage du wizard, sinon
			// fournis ici (rare) puis chiffrés immédiatement.
			$creds_json = IMP_Security::decrypt( (string) get_transient( 'imp_migration_creds_' . $job['id'] ) );
			$creds      = is_string( $creds_json ) ? json_decode( $creds_json, true ) : null;
			$have_transient = is_array( $creds );

			if ( ! $have_transient ) {
				$creds = isset( $config['db'] ) ? (array) $config['db'] : array();
			}
			if ( empty( $creds['host'] ) || empty( $creds['user'] ) || empty( $creds['name'] ) ) {
				return array( 'done' => false, 'error' => 'IMP-208', 'message' => __( 'Target database credentials expired — restart the migration wizard.', 'infinity-migratex-pro' ) );
			}

			$target = IMP_Database::connect_target( $creds );
			if ( is_string( $target ) ) {
				return array( 'done' => false, 'error' => $target, 'message' => __( 'Target database connection failed.', 'infinity-migratex-pro' ) );
			}

			if ( ! $have_transient ) {
				set_transient(
					'imp_migration_creds_' . $job['id'],
					IMP_Security::encrypt( (string) wp_json_encode( $creds ) ),
					6 * HOUR_IN_SECONDS
				);
			}

			// Exclusions : défauts + destination si imbriquée.
			$exclusions = imp_default_exclusions();
			if ( 0 === strpos( $dest . '/', $src . '/' ) ) {
				$exclusions[] = ltrim( str_replace( $src, '', $dest ), '/' ) . '/';
			}

			$job['data']['dest']        = $dest;
			$job['data']['exclusions']  = $exclusions;
			$job['data']['selection']   = IMP_Backup_Engine::normalize_selection( $config );
			$job['data']['new_url']     = untrailingslashit( esc_url_raw( (string) $config['new_url'] ) );
			$job['data']['old_url']     = untrailingslashit( home_url() );
			$job['data']['db_prefix']   = ! empty( $config['db_prefix'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) $config['db_prefix'] ) : 'wp_';

			$job['state'] = array(
				'index'   => array(),
				'files'   => array(),
				'dump'    => array(),
				'import'  => array(),
				'replace' => array(),
			);

			return array(
				'done'    => true,
				'message' => __( 'Preparation complete — copying files to the destination.', 'infinity-migratex-pro' ),
			);
		}

		return array( 'done' => false, 'error' => 'IMP-224', 'message' => __( 'Unknown migration type.', 'infinity-migratex-pro' ) );
	}

	/**
	 * Phase remplacement d'URLs (type domain OU clone — base cible).
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_replace( array &$job, $budget ) {
		$config = $job['data'];
		$db     = null;

		if ( 'clone' === $config['type'] ) {
			$creds_json = IMP_Security::decrypt( (string) get_transient( 'imp_migration_creds_' . $job['id'] ) );
			$creds      = json_decode( (string) $creds_json, true );
			if ( ! is_array( $creds ) ) {
				return array( 'done' => false, 'error' => 'IMP-208', 'message' => __( 'Target credentials expired — restart the migration.', 'infinity-migratex-pro' ) );
			}
			$db = IMP_Database::connect_target( $creds );
			if ( is_string( $db ) ) {
				return array( 'done' => false, 'error' => $db, 'message' => __( 'Target database connection lost.', 'infinity-migratex-pro' ) );
			}
		}

		$variants = imp_url_variants( $config['old_url'], $config['new_url'] );
		$args     = array(
			'from' => $variants['from'],
			'to'   => $variants['to'],
			'json' => (bool) imp_setting( 'urlreplace_in_json', 1 ),
		);
		if ( null !== $db ) {
			$args['wpdb'] = $db;
		}

		$state  =& $job['state']['replace'];
		$result = IMP_URL_Replacer::run( $args, $state, $budget );

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$total_rows = 0;
		if ( ! empty( $state['tables'] ) ) {
			foreach ( $state['tables'] as $table ) {
				$total_rows += (int) $table['rows'];
			}
		}
		$percent = $total_rows > 0
			? min( 100, 100 * ( $result['rows_scanned'] ) / max( 1, $total_rows ) )
			: 0.0;

		$job['state']['meta']['replace'] = $result;

		return array(
			'done'    => $result['done'],
			'percent' => $percent,
			'message' => sprintf(
				/* translators: 1: rows scanned 2: values changed 3: current table */
				__( 'URL replacement: %1$d rows scanned, %2$d values updated — %3$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['rows_scanned'] ),
				number_format_i18n( $result['values_changed'] ),
				$result['current']
			),
		);
	}

	/**
	 * Phase finalisation (domain) : options, caches, rewrite.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_finalize( array &$job ) {
		$config = $job['data'];
		$new    = untrailingslashit( esc_url_raw( (string) $config['new_url'] ) );

		if ( 'domain' === $config['type'] ) {
			// Options officielles de site.
			update_option( 'siteurl', $new );
			update_option( 'home', $new );

			IMP_Integrations_WordPress::clear_known_caches();
			IMP_Integrations_WordPress::flush_rewrites();

			$replace = isset( $job['state']['meta']['replace'] ) ? $job['state']['meta']['replace'] : array();

			$job['result'] = array(
				'new_url'       => $new,
				'rows_changed'  => isset( $replace['rows_changed'] ) ? $replace['rows_changed'] : 0,
				'values_changed' => isset( $replace['values_changed'] ) ? $replace['values_changed'] : 0,
				'elementor'     => IMP_Integrations_Elementor::is_active(),
			);

			return array(
				'done'    => true,
				'message' => sprintf(
					/* translators: 1: values count 2: new URL */
					__( 'Domain migration complete: %1$d URL values updated — site now at %2$s.', 'infinity-migratex-pro' ),
					number_format_i18n( isset( $replace['values_changed'] ) ? $replace['values_changed'] : 0 ),
					$new
				),
			);
		}

		if ( 'clone' === $config['type'] ) {
			// Destruction immédiate des identifiants.
			delete_transient( 'imp_migration_creds_' . $job['id'] );

			@wp_delete_file( IMP_Security::tmp_dir() . $job['id'] . '-filelist.jsonl' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@wp_delete_file( IMP_Security::tmp_dir() . $job['id'] . '-dump.sql' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

			$hashes = IMP_Security::tmp_dir() . $job['id'] . '-hashes.txt';
			$integrity = IMP_Integrity::verify_copy_hashes( $hashes, $config['dest'], 20 );
			@wp_delete_file( $hashes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

			$job['result'] = array(
				'dest'         => $config['dest'],
				'new_url'      => $new,
				'integrity'    => $integrity,
				'elementor'    => IMP_Integrations_Elementor::is_active(),
			);

			$mismatch = (int) $integrity['mismatched'] + (int) $integrity['missing'];
			return array(
				'done'    => true,
				'message' => sprintf(
					/* translators: 1: destination path 2: checked files */
					_n( 'Clone complete at %1$s — %2$d file verified.', 'Clone complete at %1$s — %2$d files verified.', (int) $integrity['checked'], 'infinity-migratex-pro' ),
					$config['dest'],
					(int) $integrity['checked']
				) . ( $mismatch > 0 ? ' ' . sprintf( /* translators: %d: files */ __( '(%d files need attention.)', 'infinity-migratex-pro' ), $mismatch ) : '' ),
			);
		}

		return array( 'done' => false, 'error' => 'IMP-224', 'message' => '' );
	}

	/* ------------------------------------------------------------------ *
	 * Phases spécifiques au clone
	 * ------------------------------------------------------------------ */

	/**
	 * Indexation des fichiers source.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string}
	 */
	/**
	 * Facteur de vitesse du job (Safe 0.6 / Balanced 1 / Turbo 1.15).
	 *
	 * @param array $job Job.
	 * @return float
	 */
	private static function speed_budget_factor( array $job ) {
		$speed = isset( $job['data']['speed'] ) ? (string) $job['data']['speed'] : 'balanced';
		if ( 'safe' === $speed ) {
			return 0.6;
		}
		if ( 'turbo' === $speed ) {
			return 1.15;
		}
		return 1.0;
	}

	public static function step_index( array &$job, $budget ) {
		$budget    = $budget * self::speed_budget_factor( $job );
		$list_file = IMP_Security::tmp_dir() . $job['id'] . '-filelist.jsonl';
		$state    =& $job['state']['index'];

		$result = IMP_Files::index(
			array(
				'root'        => ABSPATH,
				'components'  => $job['data']['selection'],
				'exclusions'  => $job['data']['exclusions'],
				'list_file'   => $list_file,
				'skip_config' => true,
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		return array(
			'done'    => $result['done'],
			'message' => sprintf(
				/* translators: 1: files 2: size */
				__( 'Indexing source: %1$s files (%2$s).', 'infinity-migratex-pro' ),
				number_format_i18n( $result['files'] ),
				imp_format_bytes( $result['bytes'] )
			),
		);
	}

	/**
	 * Copie des fichiers vers la destination (avec hashs).
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_files( array &$job, $budget ) {
		$budget     = $budget * self::speed_budget_factor( $job );
		$list_file  = IMP_Security::tmp_dir() . $job['id'] . '-filelist.jsonl';
		$hashes     = IMP_Security::tmp_dir() . $job['id'] . '-hashes.txt';
		$state     =& $job['state']['files'];

		$result = IMP_Files::copy_chunk(
			array(
				'list_file'   => $list_file,
				'source_root' => ABSPATH,
				'dest_root'   => $job['data']['dest'],
				'hashes_file' => $hashes,
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['meta']['files'] = $result;

		return array(
			'done'    => $result['done'],
			'percent' => $result['total_bytes'] > 0 ? min( 100, 100 * $result['bytes_done'] / max( 1, $result['total_bytes'] ) ) : 0.0,
			'message' => sprintf(
				/* translators: 1: done 2: total 3: copied size */
				__( 'Copying files: %1$d / %2$d (%3$s).', 'infinity-migratex-pro' ),
				$result['files_done'],
				$result['total_files'],
				imp_format_bytes( $result['bytes_done'] )
			),
		);
	}

	/**
	 * Dump de la base source vers tmp.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_db_dump( array &$job, $budget ) {
		$budget = $budget * self::speed_budget_factor( $job );
		$out    = IMP_Security::tmp_dir() . $job['id'] . '-dump.sql';
		$state =& $job['state']['dump'];

		$result = IMP_Database::dump_chunk(
			array(
				'out'         => $out,
				'compression' => false, // réimport immédiat : pas de gzip.
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['meta']['dump'] = $result;

		return array(
			'done'    => $result['done'],
			'percent' => $result['total_rows'] > 0 ? min( 100, 100 * $result['rows_done'] / max( 1, $result['total_rows'] ) ) : 0.0,
			'message' => sprintf(
				/* translators: 1: rows 2: table */
				__( 'Exporting database: %1$d rows — %2$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['rows_done'] ),
				$result['current']
			),
		);
	}

	/**
	 * Import du dump dans la base cible.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_db_import( array &$job, $budget ) {
		$creds_json = IMP_Security::decrypt( (string) get_transient( 'imp_migration_creds_' . $job['id'] ) );
		$creds      = json_decode( (string) $creds_json, true );
		if ( ! is_array( $creds ) ) {
			return array( 'done' => false, 'error' => 'IMP-208', 'message' => __( 'Target credentials expired — restart the migration.', 'infinity-migratex-pro' ) );
		}
		$db = IMP_Database::connect_target( $creds );
		if ( is_string( $db ) ) {
			return array( 'done' => false, 'error' => $db, 'message' => '' );
		}

		$state =& $job['state']['import'];
		$result = IMP_Database::import_chunk(
			array(
				'file' => IMP_Security::tmp_dir() . $job['id'] . '-dump.sql',
				'wpdb' => $db,
			),
			$state,
			$budget
		);

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		$job['state']['meta']['import'] = $result;

		return array(
			'done'    => $result['done'],
			'percent' => $result['total_bytes'] > 0 ? min( 100, 100 * $result['bytes'] / max( 1, $result['total_bytes'] ) ) : 0.0,
			'message' => sprintf(
				/* translators: 1: statements 2: table */
				__( 'Importing database: %1$d statements — %2$s', 'infinity-migratex-pro' ),
				number_format_i18n( $result['statements'] ),
				$result['current']
			),
		);
	}

	/**
	 * Génération du wp-config.php de la destination + siteurl cible.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_wpconfig( array &$job ) {
		$config = $job['data'];

		$creds_json = IMP_Security::decrypt( (string) get_transient( 'imp_migration_creds_' . $job['id'] ) );
		$creds      = json_decode( (string) $creds_json, true );
		if ( ! is_array( $creds ) ) {
			return array( 'done' => false, 'error' => 'IMP-208', 'message' => __( 'Target credentials expired — restart the migration.', 'infinity-migratex-pro' ) );
		}

		$db = IMP_Database::connect_target( $creds );
		if ( is_string( $db ) ) {
			return array( 'done' => false, 'error' => $db, 'message' => '' );
		}

		// Mise à jour des URLs dans la base cible (siteurl/home).
		$new    = $config['new_url'];
		$prefix = $config['db_prefix'];

		// Le dump a été importé avec le préfixe source : renommer si différent.
		global $wpdb;
		if ( $prefix !== $wpdb->prefix ) {
			self::rebind_prefix( $db, $wpdb->prefix, $prefix );
		}

		$set_url = function ( $name, $value ) use ( $db, $prefix ) {
			$table = $prefix . 'options';
			$found = $db->get_var( $db->prepare( "SELECT option_id FROM `{$table}` WHERE option_name = %s", $name ) );
			if ( $found ) {
				$db->query( $db->prepare( "UPDATE `{$table}` SET option_value = %s WHERE option_name = %s", $value, $name ) );
			} else {
				$db->insert( $table, array( 'option_name' => $name, 'option_value' => $value, 'autoload' => 'yes' ) );
			}
		};
		$set_url( 'siteurl', $new );
		$set_url( 'home', $new );

		// wp-config.php neuf pour la destination.
		$config_php = self::build_wp_config( $creds, $prefix );
		$dest_config = $config['dest'] . '/wp-config.php';
		$written = false !== @file_put_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			$dest_config,
			$config_php
		);

		if ( ! $written ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-220',
				'message' => __( 'Could not write wp-config.php to the destination — create it manually from the diagnostic report.', 'infinity-migratex-pro' ),
			);
		}

		// .htaccess basique si absent.
		$htaccess = $config['dest'] . '/.htaccess';
		if ( ! file_exists( $htaccess ) && got_mod_rewrite() ) {
			@file_put_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				$htaccess,
				"# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n"
			);
		}

		return array(
			'done'    => true,
			'message' => __( 'Destination wp-config.php created and target URLs set.', 'infinity-migratex-pro' ),
		);
	}

	/**
	 * Vérification d'intégrité du clone (échantillon de hashs).
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string,percent?:float}
	 */
	public static function step_integrity( array &$job ) {
		$hashes   = IMP_Security::tmp_dir() . $job['id'] . '-hashes.txt';
		$integrity = IMP_Integrity::verify_copy_hashes( $hashes, $job['data']['dest'], 10 );

		$job['state']['meta']['integrity'] = $integrity;

		$mismatch = (int) $integrity['mismatched'] + (int) $integrity['missing'];

		return array(
			'done'    => true,
			'percent' => 100.0,
			'message' => sprintf(
				/* translators: 1: checked 2: ok */
				__( 'Integrity: %1$d files verified, %2$d identical.', 'infinity-migratex-pro' ),
				$integrity['checked'],
				$integrity['ok']
			) . ( $mismatch > 0 ? ' ' . sprintf( /* translators: %d */ __( '%d mismatches!', 'infinity-migratex-pro' ), $mismatch ) : '' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Renomme les tables d'un préfixe vers un autre (clone).
	 *
	 * @param wpdb   $db    Base cible.
	 * @param string $from  Préfixe source.
	 * @param string $to    Préfixe cible.
	 * @return void
	 */
	private static function rebind_prefix( $db, $from, $to ) {
		$tables = $db->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) ) {
			return;
		}
		foreach ( $tables as $table ) {
			$table = (string) $table;
			if ( 0 === strpos( $table, $from ) ) {
				$new_name = $to . substr( $table, strlen( $from ) );
				$db->query( "RENAME TABLE `{$table}` TO `{$new_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/**
	 * Génère un wp-config.php minimal et propre pour la destination.
	 *
	 * @param array  $creds  Identifiants cible.
	 * @param string $prefix Préfixe de tables.
	 * @return string
	 */
	private static function build_wp_config( array $creds, $prefix ) {
		$keys = array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' );

		$lines = array(
			'<?php',
			'/**',
			' * WordPress configuration — generated by Infinity Migrate Pro.',
			' * By Derouiche Oussama — Infinity Coder. GPL v2+.',
			' */',
			'',
			"define( 'DB_NAME', '" . addslashes( (string) $creds['name'] ) . "' );",
			"define( 'DB_USER', '" . addslashes( (string) $creds['user'] ) . "' );",
			"define( 'DB_PASSWORD', '" . addslashes( (string) $creds['pass'] ) . "' );",
			"define( 'DB_HOST', '" . addslashes( (string) $creds['host'] ) . "' );",
			"define( 'DB_CHARSET', 'utf8mb4' );",
			"define( 'DB_COLLATE', '' );",
			'',
		);

		foreach ( $keys as $key ) {
			$lines[] = "define( '" . $key . "', '" . wp_generate_password( 64, true, true ) . "' );";
		}

		$lines[] = '';
		$lines[] = '$table_prefix = \'' . addslashes( (string) $prefix ) . '\';';
		$lines[] = '';
		$lines[] = "define( 'WP_DEBUG', false );";
		$lines[] = '';
		$lines[] = '/* C\'est tout, ne touchez pas à ce qui suit ! Bonne publication. */';
		$lines[] = "if ( ! defined( 'ABSPATH' ) ) {";
		$lines[] = "\tdefine( 'ABSPATH', __DIR__ . '/' );";
		$lines[] = '}';
		$lines[] = '';
		$lines[] = "require_once ABSPATH . 'wp-settings.php';";
		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Statut disque.
	 *
	 * @param int $site_bytes Taille du site.
	 * @return string
	 */
	private static function disk_status( $site_bytes ) {
		$free = IMP_Site_Stats::disk_free();
		if ( 0 === $free ) {
			return 'warn'; // indéterminable.
		}
		return ( $free > $site_bytes * 2 ) ? 'pass' : ( $free > $site_bytes ? 'warn' : 'error' );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
