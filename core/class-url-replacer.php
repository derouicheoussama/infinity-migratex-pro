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
 * Remplacement d'URLs / chaînes compatible données sérialisées PHP et JSON.
 * Jamais de str_replace aveugle : chaque valeur passe par un décodage
 * (unserialize/json) quand c'est nécessaire, les longueurs sont
 * recalculées par resérialisation. Traitement par chunks + reprise.
 */
final class IMP_URL_Replacer {

	/**
	 * Exécute (ou simule) le remplacement — par lots.
	 *
	 * @param array $args   {from:array,to:array,tables?:array,skip_tables?:array,dry_run:bool,json:bool,wpdb?:wpdb,chunk_rows?:int}.
	 * @param array $state  État persistant.
	 * @param float $budget Secondes.
	 * @return array{done:bool,error?:string,tables_scanned:int,rows_scanned:int,rows_changed:int,values_changed:int,errors:int,no_pk:array,current:string}
	 */
	public static function run( array $args, array &$state, $budget ) {
		global $wpdb;
		$db = ! empty( $args['wpdb'] ) ? $args['wpdb'] : $wpdb;
		/** @var wpdb $db */

		$from = isset( $args['from'] ) ? array_values( (array) $args['from'] ) : array();
		$to   = isset( $args['to'] ) ? array_values( (array) $args['to'] ) : array();
		if ( empty( $from ) || count( $from ) !== count( $to ) ) {
			return array( 'done' => false, 'error' => 'IMP-210', 'tables_scanned' => 0, 'rows_scanned' => 0, 'rows_changed' => 0, 'values_changed' => 0, 'errors' => 0, 'no_pk' => array(), 'current' => '' );
		}

		$dry_run  = ! empty( $args['dry_run'] );
		$json_too = ! isset( $args['json'] ) || $args['json'];
		$chunk    = max( 10, (int) ( isset( $args['chunk_rows'] ) ? $args['chunk_rows'] : imp_settings()['chunk_rows'] ) );

		$state = wp_parse_args(
			$state,
			array(
				'table_index'    => 0,
				'tables'         => array(),
				'tables_scanned' => 0,
				'rows_scanned'   => 0,
				'rows_changed'   => 0,
				'values_changed' => 0,
				'errors'         => 0,
				'no_pk'          => array(),
			)
		);

		// Plan des tables (une seule fois) : tables texte avec clé primaire.
		if ( empty( $state['tables'] ) ) {
			$skip = isset( $args['skip_tables'] ) ? (array) $args['skip_tables'] : array();
			$skip = array_merge( $skip, IMP_Database::default_replace_skip_tables() );

			$tables = ! empty( $args['tables'] ) ? (array) $args['tables'] : IMP_Database::site_tables();

			$plan = array();
			foreach ( $tables as $table ) {
				if ( in_array( $table, $skip, true ) ) {
					continue;
				}
				if ( $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
					continue;
				}

				$pk = $db->get_row( $db->prepare( 'SHOW INDEX FROM `' . $table . '` WHERE Key_name = %s', 'PRIMARY' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( ! $pk || empty( $pk['Column_name'] ) ) {
					$state['no_pk'][] = $table; // sans clé : remplacement non sûr, on signale.
					continue;
				}

				$columns = array();
				$raw     = $db->get_results( $db->prepare( 'SHOW FULL COLUMNS FROM `' . $table . '`' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( is_array( $raw ) ) {
					foreach ( $raw as $column ) {
						$type = strtolower( (string) $column['Type'] );
						if ( preg_match( '/^(varchar|char|tinytext|mediumtext|longtext|text)\b/', $type ) && ! empty( $column['Field'] ) ) {
							$columns[] = (string) $column['Field'];
						}
					}
				}

				if ( empty( $columns ) ) {
					continue; // aucune colonne texte : rien à remplacer.
				}

				$plan[] = array(
					'name'      => $table,
					'pk'        => (string) $pk['Column_name'],
					'columns'   => $columns,
					'last_pk'   => null,
					'rows'      => 0,
					'done'      => false,
				);
			}

			$state['tables'] = $plan;
		}

		$started = microtime( true );
		$current = '';

		while ( microtime( true ) - $started <= $budget ) {
			if ( $state['table_index'] >= count( $state['tables'] ) ) {
				$state['done'] = true;
				break;
			}

			$table   = &$state['tables'][ $state['table_index'] ];
			$current = $table['name'];

			if ( null === $table['last_pk'] ) {
				$table['rows'] = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM `' . $table['name'] . '`' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			if ( null !== $table['last_pk'] ) {
				$sql = $db->prepare( 'SELECT * FROM `' . $table['name'] . '` WHERE `' . $table['pk'] . '` > %s ORDER BY `' . $table['pk'] . '` ASC LIMIT %d', (string) $table['last_pk'], $chunk ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$sql = $db->prepare( 'SELECT * FROM `' . $table['name'] . '` ORDER BY `' . $table['pk'] . '` ASC LIMIT %d', $chunk ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			$rows = $db->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( ! is_array( $rows ) || empty( $rows ) ) {
				$table['done'] = true;
				$state['table_index']++;
				$state['tables_scanned']++;
				continue;
			}

			foreach ( $rows as $row ) {
				$state['rows_scanned']++;

				$changes   = array();
				$changed_n = 0;
				foreach ( $table['columns'] as $column ) {
					if ( ! array_key_exists( $column, $row ) || ! is_string( $row[ $column ] ) ) {
						continue;
					}
					$result = imp_replace_column_value( $row[ $column ], $from, $to, $json_too );
					if ( $result['changed'] ) {
						$changes[ $column ] = $result['value'];
						$changed_n++;
					} elseif ( 'serialized-corrupt' === $result['mode'] ) {
						$state['errors']++; // donnée sérialisée illisible : jamais touchée.
					}
				}

				if ( ! empty( $changes ) && ! $dry_run ) {
					$set   = array();
					$params = array();
					foreach ( $changes as $column => $value ) {
						$set[]   = '`' . $column . '` = %s';
						$params[] = $value;
					}
					$params[] = $row[ $table['pk'] ] ;

					$update = 'UPDATE `' . $table['name'] . '` SET ' . implode( ', ', $set ) . ' WHERE `' . $table['pk'] . '` = %s';
					$query  = $db->prepare( $update, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$ok     = false !== $query ? $db->query( $query ) : false; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( false === $ok ) {
						$state['errors']++;
					} else {
						$state['rows_changed']++;
					}
				} elseif ( ! empty( $changes ) && $dry_run ) {
					$state['rows_changed']++;
				}
				$state['values_changed'] += $changed_n;
			}

			$last_row   = $rows[ count( $rows ) - 1 ];
			$table['last_pk'] = (string) $last_row[ $table['pk'] ];
			$table['rows']   += 0;

			if ( count( $rows ) < $chunk ) {
				$table['done'] = true;
				$state['table_index']++;
				$state['tables_scanned']++;
			}
		}
		unset( $table );

		return array(
			'done'           => ! empty( $state['done'] ),
			'tables_scanned' => $state['tables_scanned'],
			'rows_scanned'   => $state['rows_scanned'],
			'rows_changed'   => $state['rows_changed'],
			'values_changed' => $state['values_changed'],
			'errors'         => $state['errors'],
			'no_pk'          => $state['no_pk'],
			'current'        => $current,
		);
	}

	/**
	 * Prévisualisation rapide : premières correspondances réelles (dry-run
	 * léger sur les tables clés options/postmeta).
	 *
	 * @param array $from Recherches.
	 * @param array $to   Remplacements.
	 * @return array{rows:int,matches:int,tables:int}
	 */
	public static function quick_preview( array $from, array $to ) {
		global $wpdb;

		$state  = array();
		$args   = array(
			'from'     => $from,
			'to'       => $to,
			'dry_run'  => true,
			'tables'   => array( $wpdb->options, $wpdb->postmeta ),
			'json'     => true,
		);
		$result = self::run( $args, $state, 8 );

		return array(
			'rows'    => (int) $result['rows_scanned'],
			'matches' => (int) $result['values_changed'],
			'tables'  => (int) $result['tables_scanned'],
		);
	}
}
