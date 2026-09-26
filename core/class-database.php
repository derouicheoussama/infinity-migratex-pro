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
 * Moteur base de données : dump chunked reprise (clé primaire quand
 * possible), import par statements, informations tables, optimisation.
 */
final class IMP_Database {

	/**
	 * Tables WordPress de ce site (préfixe inclus), tables de base uniquement.
	 *
	 * @return array<string>
	 */
	public static function site_tables() {
		global $wpdb;

		$like    = $wpdb->esc_like( $wpdb->prefix );
		$tables  = $wpdb->get_col( $wpdb->prepare( 'SHOW FULL TABLES WHERE Table_type = %s AND `Tables_in_' . DB_NAME . '` LIKE %s', 'BASE TABLE', $like . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tables  = is_array( $tables ) ? $tables : array();

		// Tables globales sans préfixe (multisite : users, usermeta…).
		foreach ( array( $wpdb->users, $wpdb->usermeta ) as $global_table ) {
			if ( $global_table && ! in_array( $global_table, $tables, true ) ) {
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $global_table ) );
				if ( $exists === $global_table ) {
					$tables[] = $global_table;
				}
			}
		}

		// Optimisation WooCommerce : sessions et logs d'Action Scheduler
		// sont éphémères — exclus des dumps quand les options sont actives.
		$settings = imp_settings();
		$excluded = array();
		if ( ! empty( $settings['wc_skip_sessions'] ) ) {
			$excluded[] = $wpdb->prefix . 'woocommerce_sessions';
		}
		if ( ! empty( $settings['wc_skip_scheduler'] ) ) {
			foreach ( $tables as $table ) {
				if ( false !== strpos( (string) $table, '_actionscheduler' ) ) {
					$excluded[] = (string) $table;
				}
			}
		}
		if ( ! empty( $excluded ) ) {
			$tables = array_values( array_diff( $tables, $excluded ) );
		}

		sort( $tables );
		return $tables;
	}

	/**
	 * Tables exclues par défaut du remplacement d'URLs (journaux, caches,
	 * sessions — données volatiles ou volumineuses sans URLs utiles).
	 *
	 * @return array<string>
	 */
	public static function default_replace_skip_tables() {
		global $wpdb;
		return array(
			$wpdb->prefix . 'imp_logs',
			$wpdb->prefix . 'actionscheduler_actions',
			$wpdb->prefix . 'actionscheduler_logs',
			$wpdb->prefix . 'wc_session_ca', // sessions WooCommerce éphémères.
		);
	}

	/**
	 * Informations détaillées des tables (page Database).
	 *
	 * @return array[] {name,rows,bytes,data_bytes,index_bytes,engine,collation,comment}
	 */
	public static function tables_info() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$name = isset( $row['Name'] ) ? (string) $row['Name'] : '';
				if ( '' === $name ) {
					continue;
				}
				$out[] = array(
					'name'        => $name,
					'rows'        => isset( $row['Rows'] ) ? (int) $row['Rows'] : 0,
					'bytes'       => ( isset( $row['Data_length'] ) ? (int) $row['Data_length'] : 0 ) + ( isset( $row['Index_length'] ) ? (int) $row['Index_length'] : 0 ),
					'data_bytes'  => isset( $row['Data_length'] ) ? (int) $row['Data_length'] : 0,
					'index_bytes' => isset( $row['Index_length'] ) ? (int) $row['Index_length'] : 0,
					'engine'      => isset( $row['Engine'] ) ? (string) $row['Engine'] : '',
					'collation'   => isset( $row['Collation'] ) ? (string) $row['Collation'] : '',
					'comment'     => isset( $row['Comment'] ) ? (string) $row['Comment'] : '',
				);
			}
		}
		return $out;
	}

	/**
	 * Échappe une valeur pour un dump SQL (mysqli > PDO > addslashes).
	 *
	 * @param mixed $value Valeur.
	 * @return string
	 */
	public static function escape_value( $value ) {
		global $wpdb;

		if ( null === $value ) {
			return 'NULL';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		$dbh = isset( $wpdb->dbh ) ? $wpdb->dbh : null;
		if ( $dbh instanceof mysqli && function_exists( 'mysqli_real_escape_string' ) ) {
			return "'" . mysqli_real_escape_string( $dbh, (string) $value ) . "'";
		}
		if ( $dbh instanceof PDO ) {
			$quoted = $dbh->quote( (string) $value );
			if ( false !== $quoted ) {
				return $quoted;
			}
		}
		return "'" . addslashes( (string) $value ) . "'"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Ouvre un writer SQL (gzip si demandé et disponible).
	 *
	 * @param string $file        Chemin.
	 * @param bool   $compression Compacter en gzip.
	 * @return array{handle:resource,compressed:bool}|string Code d'erreur IMP-XXX.
	 */
	public static function open_writer( $file, $compression ) {
		$compressed = $compression && function_exists( 'gzopen' );
		if ( $compressed ) {
			$handle = @gzopen( $file, 'ab' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		} else {
			$handle = @fopen( $file, 'ab' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( ! is_resource( $handle ) ) {
			return 'IMP-204';
		}
		return array(
			'handle'     => $handle,
			'compressed' => $compressed,
		);
	}

	/**
	 * Écrit dans le writer.
	 *
	 * @param array    $writer Writer.
	 * @param string   $data   Données.
	 * @return void
	 */
	public static function write( array $writer, $data ) {
		if ( $writer['compressed'] ) {
			gzwrite( $writer['handle'], $data );
		} else {
			fwrite( $writer['handle'], $data );
		}
	}

	/**
	 * Ferme le writer.
	 *
	 * @param array $writer Writer.
	 * @return void
	 */
	public static function close_writer( array $writer ) {
		if ( $writer['compressed'] ) {
			gzclose( $writer['handle'] );
		} else {
			fclose( $writer['handle'] );
		}
	}

	/**
	 * Dump chunked de la base — reprise par table + position.
	 *
	 * @param array $args   {out, tables[], compression, chunk_rows, wpdb(custom?)}.
	 * @param array $state  {table_index, header_written, tables_done, rows_done, total_rows, tables:[{name,rows,rows_done,last_pk,done}]}.
	 * @param float $budget Secondes.
	 * @param float $start  Timestamp début (durée totale).
	 * @return array{done:bool,error?:string,tables_done:int,rows_done:int,total_rows:int,bytes:int,current:string}
	 */
	public static function dump_chunk( array $args, array &$state, $budget, $start = 0 ) {
		global $wpdb;
		$db = ! empty( $args['wpdb'] ) ? $args['wpdb'] : $wpdb;
		/** @var wpdb $db */

		$settings   = imp_settings();
		$chunk_rows = max( 20, (int) ( isset( $args['chunk_rows'] ) ? $args['chunk_rows'] : $settings['chunk_db_rows'] ) );

		$state = wp_parse_args(
			$state,
			array(
				'table_index'    => 0,
				'header_written' => false,
				'tables_done'    => 0,
				'rows_done'      => 0,
				'total_rows'     => 0,
				'tables'         => array(),
				'bytes'          => 0,
			)
		);

		// Plan des tables (une fois).
		if ( empty( $state['tables'] ) ) {
			$tables = ! empty( $args['tables'] ) ? (array) $args['tables'] : self::site_tables();
			$plan   = array();
			foreach ( $tables as $table ) {
				$exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $exists !== $table ) {
					continue;
				}
				$count = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM `' . $table . '`' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$plan[] = array(
					'name'      => $table,
					'rows'      => $count,
					'rows_done' => 0,
					'last_pk'   => null,
					'done'      => false,
				);
				$state['total_rows'] += $count;
			}
			$state['tables'] = $plan;
			if ( empty( $plan ) ) {
				return array( 'done' => true, 'tables_done' => 0, 'rows_done' => 0, 'total_rows' => 0, 'bytes' => 0, 'current' => '' );
			}
		}

		$writer = self::open_writer( $args['out'], ! empty( $args['compression'] ) );
		if ( is_string( $writer ) ) {
			return array( 'done' => false, 'error' => $writer, 'tables_done' => $state['tables_done'], 'rows_done' => $state['rows_done'], 'total_rows' => $state['total_rows'], 'bytes' => $state['bytes'], 'current' => '' );
		}

		$started = microtime( true );
		$current = '';

		try {
			if ( ! $state['header_written'] ) {
				$charset = defined( 'DB_CHARSET' ) ? DB_CHARSET : 'utf8mb4';
				self::write( $writer, "-- Infinity Migrate Pro database dump\n" );
				self::write( $writer, '-- Generated: ' . gmdate( 'c' ) . "\n" );
				self::write( $writer, '-- Plugin: ' . IMP_VERSION . ' — by Derouiche Oussama (Infinity Coder) — GPL v2+\n\n' );
				self::write( $writer, "SET NAMES {$charset};\n" );
				self::write( $writer, "SET FOREIGN_KEY_CHECKS = 0;\n" );
				self::write( $writer, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n" );
				self::write( $writer, "SET time_zone = '+00:00';\n\n" );
				$state['header_written'] = true;
			}

			while ( microtime( true ) - $started <= $budget ) {
				if ( $state['table_index'] >= count( $state['tables'] ) ) {
					self::write( $writer, "\nSET FOREIGN_KEY_CHECKS = 1;\n" );
					$state['done'] = true;
					break;
				}

				$table = &$state['tables'][ $state['table_index'] ];
				$current = $table['name'];

				// CREATE TABLE une fois par table.
				if ( 0 === $table['rows_done'] && null === $table['last_pk'] && ! $table['done'] ) {
					$create = $db->get_row( $db->prepare( 'SHOW CREATE TABLE `' . $table['name'] . '`' ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					if ( ! empty( $create[1] ) ) {
						self::write( $writer, "\nDROP TABLE IF EXISTS `" . $table['name'] . "`;\n" );
						self::write( $writer, $create[1] . ";\n" );

						// Clé primaire pour la pagination reprise.
						$pk = $db->get_row( $db->prepare( 'SHOW INDEX FROM `' . $table['name'] . '` WHERE Key_name = %s', 'PRIMARY' ), ARRAY_A );
						if ( $pk && ! empty( $pk['Column_name'] ) ) {
							$table['pk'] = (string) $pk['Column_name'];
						}
					} else {
						// Table illisible : marquée faite, signalée.
						$table['done'] = true;
						$state['table_index']++;
						$state['tables_done']++;
						continue;
					}
				}

				// Lecture d'un lot de lignes.
				$limit = min( $chunk_rows, max( 1, $table['rows'] - $table['rows_done'] ) );
				if ( isset( $table['pk'] ) ) {
					if ( null !== $table['last_pk'] ) {
						$sql = $db->prepare( 'SELECT * FROM `' . $table['name'] . '` WHERE `' . $table['pk'] . '` > %s ORDER BY `' . $table['pk'] . '` ASC LIMIT %d', (string) $table['last_pk'], $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					} else {
						$sql = $db->prepare( 'SELECT * FROM `' . $table['name'] . '` ORDER BY `' . $table['pk'] . '` ASC LIMIT %d', $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					}
					$rows = $db->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$sql = $db->prepare( 'SELECT * FROM `' . $table['name'] . '` LIMIT %d OFFSET %d', $limit, $table['rows_done'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$rows = $db->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}

				if ( ! is_array( $rows ) || empty( $rows ) ) {
					$table['done'] = true;
					$state['table_index']++;
					$state['tables_done']++;
					continue;
				}

				// INSERT étendu (paquets ≤ 512 Ko).
				$columns = '`' . implode( '`, `', array_keys( $rows[0] ) ) . '`';
				$prefix  = 'INSERT INTO `' . $table['name'] . '` (' . $columns . ') VALUES ';
				$values  = '';
				$length  = 0;

				foreach ( $rows as $row ) {
					$tuple = '(';
					$first = true;
					foreach ( $row as $value ) {
						if ( ! $first ) {
							$tuple .= ', ';
						}
						$tuple .= self::escape_value( $value );
						$first  = false;
					}
					$tuple .= ')';

					if ( '' !== $values && $length + strlen( $tuple ) > 512 * 1024 ) {
						self::write( $writer, $prefix . $values . ";\n" );
						$state['bytes'] += strlen( $prefix ) + strlen( $values );
						$values = '';
						$length = 0;
					}
					$values  .= ( '' === $values ? '' : ', ' ) . $tuple;
					$length  += strlen( $tuple ) + 2;
				}
				if ( '' !== $values ) {
					self::write( $writer, $prefix . $values . ";\n" );
					$state['bytes'] += strlen( $prefix ) + strlen( $values );
				}

				$last_row = $rows[ count( $rows ) - 1 ];
				if ( isset( $table['pk'] ) ) {
					$table['last_pk'] = (string) $last_row[ $table['pk'] ];
				}
				$table['rows_done'] += count( $rows );
				$state['rows_done'] += count( $rows );

				if ( $table['rows_done'] >= $table['rows'] || count( $rows ) < $limit ) {
					$table['done'] = true;
					$state['table_index']++;
					$state['tables_done']++;
				}
			}
			unset( $table );
		} finally {
			self::close_writer( $writer );
		}

		return array(
			'done'        => ! empty( $state['done'] ),
			'tables_done' => $state['tables_done'],
			'rows_done'   => $state['rows_done'],
			'total_rows'  => $state['total_rows'],
			'bytes'       => $state['bytes'],
			'current'     => $current,
		);
	}

	/**
	 * Import SQL chunked : lit le dump, exécute statement par statement.
	 * Compatible gzip. Cible : base courante ou wpdb personnalisée (clone).
	 *
	 * @param array $args   {file, wpdb(custom?), prefix_to(?string)}.
	 * @param array $state  {offset, statements, buffer, tables_seen}.
	 * @param float $budget Secondes.
	 * @return array{done:bool,error?:string,statements:int,bytes:int,total_bytes:int,current:string}
	 */
	public static function import_chunk( array $args, array &$state, $budget ) {
		global $wpdb;
		$db = ! empty( $args['wpdb'] ) ? $args['wpdb'] : $wpdb;
		/** @var wpdb $db */

		$file = (string) $args['file'];
		if ( ! is_readable( $file ) ) {
			return array( 'done' => false, 'error' => 'IMP-207', 'statements' => 0, 'bytes' => 0, 'total_bytes' => 0, 'current' => '' );
		}

		$is_gz   = ( '.gz' === strtolower( substr( $file, -3 ) ) ) && function_exists( 'gzopen' );
		$total   = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$state = wp_parse_args(
			$state,
			array(
				'offset'      => 0,
				'statements'  => 0,
				'buffer'      => '',
				'tables_seen' => 0,
				'errors'      => 0,
				'last_error'  => '',
			)
		);

		$started = microtime( true );
		$current = '';

		$in = $is_gz ? @gzopen( $file, 'rb' ) : @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_resource( $in ) ) {
			return array( 'done' => false, 'error' => 'IMP-207', 'statements' => $state['statements'], 'bytes' => $state['offset'], 'total_bytes' => $total, 'current' => '' );
		}

		try {
			if ( 0 === $state['offset'] ) {
				$db->query( 'SET FOREIGN_KEY_CHECKS = 0' );
			}

			// Se replacer à l'offset (gzip : lecture séquentielle depuis 0 —
			// rare et borné par le budget, sinon fseek direct).
			if ( ! $is_gz && $state['offset'] > 0 ) {
				fseek( $in, $state['offset'] );
			} elseif ( $is_gz && $state['offset'] > 0 ) {
				$to_skip = $state['offset'];
				while ( $to_skip > 0 && ! gzeof( $in ) ) {
					$read = gzread( $in, min( 1024 * 1024, $to_skip ) );
					if ( '' === $read ) {
						break;
					}
					$to_skip -= strlen( $read );
				}
			}

			$buffer = (string) $state['buffer'];

			while ( microtime( true ) - $started <= $budget ) {
				if ( $is_gz ? gzeof( $in ) : feof( $in ) ) {
					break;
				}
				$chunk = $is_gz ? gzread( $in, 512 * 1024 ) : fread( $in, 512 * 1024 );
				if ( false === $chunk || '' === $chunk ) {
					break;
				}
				$buffer .= $chunk;

				// Exécuter tous les statements complets du buffer.
				while ( true ) {
					$semi = self::find_statement_end( $buffer );
					if ( false === $semi ) {
						break;
					}
					$statement = trim( substr( $buffer, 0, $semi + 1 ) );
					$buffer    = (string) substr( $buffer, $semi + 1 );

					if ( '' === $statement || ';' === $statement ) {
						continue;
					}
					if ( 0 === strpos( $statement, '--' ) || 0 === strpos( $statement, '/*' ) || 0 === strpos( $statement, '#' ) ) {
						continue;
					}

					$current = self::table_of_statement( $statement );

					// Fiabilité : un statement en échec n'est JAMAIS
					// silencieux — compté, tracé, et l'import s'arrête
					// au-delà d'un seuil pour préserver les données.
					$exec = self::execute_verified_statement( $db, $statement );
					if ( null === $exec ) {
						// Statement hors liste blanche : refus immédiat.
						return array(
							'done'        => false,
							'error'       => 'IMP-228',
							'statements'  => $state['statements'],
							'errors'      => $state['errors'],
							'last_error'  => mb_substr( $statement, 0, 120 ),
							'bytes'       => $state['offset'],
							'total_bytes' => $total,
							'current'     => $current,
							'tables_seen' => $state['tables_seen'],
						);
					}
					if ( false === $exec ) {
						$state['errors']++;
						if ( '' === (string) $state['last_error'] ) {
							$state['last_error'] = mb_substr( (string) $db->last_error, 0, 190 );
						}
						if ( (int) $state['errors'] >= 50 ) {
							return array(
								'done'        => false,
								'error'       => 'IMP-227',
								'statements'  => $state['statements'],
								'errors'      => $state['errors'],
								'last_error'  => (string) $state['last_error'],
								'bytes'       => $state['offset'],
								'total_bytes' => $total,
								'current'     => $current,
								'tables_seen' => $state['tables_seen'],
							);
						}
					} else {
						$state['statements']++;
					}
					if ( 0 === strpos( strtoupper( $statement ), 'CREATE TABLE' ) ) {
						$state['tables_seen']++;
					}

					if ( microtime( true ) - $started > $budget ) {
						break 2;
					}
				}

				$state['offset'] += strlen( $chunk );
			}

			// Fin de fichier : le buffer restant contient-il un dernier statement ?
			if ( ( $is_gz ? gzeof( $in ) : feof( $in ) ) && '' !== trim( $buffer ) && ! imp_sql_statement_complete( $buffer ) ) {
				$stmt = trim( self::strip_trailing_comments( $buffer ) );
				if ( '' !== $stmt && ';' !== $stmt ) {
					$exec = self::execute_verified_statement( $db, rtrim( $stmt, ';' ) );
					if ( null !== $exec && false !== $exec ) {
						$state['statements']++;
					}
				}
				$buffer = '';
			}

			$state['buffer'] = $buffer;

			$done = ( $is_gz ? gzeof( $in ) : feof( $in ) ) && '' === trim( $buffer );
			if ( $done ) {
				$db->query( 'SET FOREIGN_KEY_CHECKS = 1' );
			}
		} finally {
			if ( $is_gz ) {
				gzclose( $in );
			} else {
				fclose( $in );
			}
		}

		return array(
			'done'        => $done,
			'statements'  => $state['statements'],
			'bytes'       => $state['offset'],
			'total_bytes' => $total,
			'current'     => $current,
			'tables_seen' => $state['tables_seen'],
		);
	}

	/**
	 * Exécute un statement d'import APRÈS validation par la liste blanche.
	 *
	 * C'est l'équivalent d'un client mysql qui rejoue un dump : la
	 * protection de sécurité est la liste blanche imp_sql_statement_allowed()
	 * (verbes légitimes d'un dump uniquement, refus de LOAD DATA /
	 * INTO OUTFILE / LOAD_FILE), complétée par le contrôle de capability
	 * et de nonce des endpoints qui mènent ici.
	 *
	 * @param wpdb   $db        Connexion cible.
	 * @param string $statement Statement complet.
	 * @return int|false|null Résultat wpdb, ou null si statement refusé.
	 */
	public static function execute_verified_statement( $db, $statement ) {
		if ( ! imp_sql_statement_allowed( $statement ) ) {
			IMP_Hardening::log_event(
				'sql-import-refused',
				__( 'Import stopped: a statement outside the allowed SQL verbs was found in this file.', 'infinity-migratex-pro' )
			);
			return null;
		}
		return call_user_func_array( array( $db, 'query' ), array( $statement ) );
	}

	/**
	 * Position du ";" finalisant le premier statement du buffer
	 * (chaînes, backticks et commentaires pris en compte).
	 *
	 * @param string $buffer Buffer.
	 * @return int|false
	 */
	private static function find_statement_end( $buffer ) {
		$pos = imp_sql_scan( $buffer, true );
		return ( false === $pos || true === $pos ) ? false : (int) $pos;
	}

	/**
	 * Nom de table visé par un statement (pour l'affichage de progression).
	 *
	 * @param string $statement Statement.
	 * @return string
	 */
	private static function table_of_statement( $statement ) {
		if ( preg_match( '/^(?:INSERT INTO|CREATE TABLE|DROP TABLE IF EXISTS|UPDATE|DELETE FROM)\s+`?([a-zA-Z0-9_\-]+)/i', (string) $statement, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Retire les commentaires de fin de dump.
	 *
	 * @param string $buffer Buffer.
	 * @return string
	 */
	private static function strip_trailing_comments( $buffer ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $buffer );
		$out   = array();
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$t = ltrim( $line );
				if ( 0 === strpos( $t, '--' ) || '#' === substr( $t, 0, 1 ) || '' === $t ) {
					continue;
				}
				$out[] = $line;
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * Connexion à une base cible (migration clone) — creds fournis
	 * déchiffrés, jamais journalisés.
	 *
	 * @param array $creds {host,user,pass,name}.
	 * @return wpdb|string Instance ou code d'erreur.
	 */
	public static function connect_target( array $creds ) {
		$host = isset( $creds['host'] ) ? (string) $creds['host'] : '';
		$user = isset( $creds['user'] ) ? (string) $creds['user'] : '';
		$pass = isset( $creds['pass'] ) ? (string) $creds['pass'] : '';
		$name = isset( $creds['name'] ) ? (string) $creds['name'] : '';

		if ( '' === $host || '' === $user || '' === $name ) {
			return 'IMP-208';
		}

		$db = new wpdb( $user, $pass, $name, $host );
		if ( $db->last_error ) {
			return 'IMP-209';
		}
		return $db;
	}
}
