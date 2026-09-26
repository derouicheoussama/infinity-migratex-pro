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
 * Journal des opérations : chaque migration, backup, restore, scan…
 * N'enregistre JAMAIS de mots de passe, clés ou secrets.
 */
final class IMP_Logger {

	const TYPE_BACKUP    = 'backup';
	const TYPE_RESTORE   = 'restore';
	const TYPE_MIGRATION = 'migration';
	const TYPE_SCAN      = 'scan';
	const TYPE_REPLACE   = 'replace';
	const TYPE_PACKAGE   = 'package';
	const TYPE_DATABASE  = 'database';
	const TYPE_SYSTEM    = 'system';

	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_CANCELED  = 'canceled';

	/**
	 * Nom complet de la table (préfixe inclus).
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'imp_logs';
	}

	/**
	 * Crée la table si absente (dbDelta).
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created DATETIME NOT NULL,
			action VARCHAR(191) NOT NULL DEFAULT '',
			type VARCHAR(40) NOT NULL DEFAULT 'system',
			status VARCHAR(20) NOT NULL DEFAULT 'running',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			duration DOUBLE UNSIGNED NOT NULL DEFAULT 0,
			files_processed BIGINT UNSIGNED NOT NULL DEFAULT 0,
			db_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
			size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NULL,
			details LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY status (status),
			KEY created (created)
		) {$collate};";

		dbDelta( $sql );
		update_option( 'imp_db_version', IMP_DB_VERSION, false );
	}

	/**
	 * Met à jour le schéma si la version change.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'imp_db_version' ) !== IMP_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Démarre une entrée de journal et renvoie son id.
	 *
	 * @param string $action  Libellé (ex: "Migration #…", "Backup manuel").
	 * @param string $type    Type (constantes TYPE_*).
	 * @param array  $details Détails JSON (jamais de secrets).
	 * @return int
	 */
	public static function start( $action, $type = self::TYPE_SYSTEM, array $details = array() ) {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'created'  => current_time( 'mysql' ),
				'action'   => substr( sanitize_text_field( (string) $action ), 0, 191 ),
				'type'     => substr( sanitize_key( (string) $type ), 0, 40 ),
				'status'   => self::STATUS_RUNNING,
				'user_id'  => get_current_user_id(),
				'message'  => '',
				'details'  => wp_json_encode( self::scrub( $details ) ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Met à jour une entrée.
	 *
	 * @param int   $id    Identifiant.
	 * @param array $data  Champs => valeurs.
	 * @return void
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		if ( $id < 1 ) {
			return;
		}
		$allowed = array( 'action', 'type', 'status', 'duration', 'files_processed', 'db_rows', 'size_bytes', 'message', 'details' );
		$clean   = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed, true ) ) {
				if ( 'details' === $key ) {
					$clean[ $key ] = wp_json_encode( self::scrub( (array) $value ) );
				} else {
					$clean[ $key ] = $value;
				}
			}
		}
		if ( empty( $clean ) ) {
			return;
		}
		$wpdb->update( self::table(), $clean, array( 'id' => (int) $id ) );
	}

	/**
	 * Clôture une entrée.
	 *
	 * @param int    $id       Identifiant.
	 * @param string $status   Statut final.
	 * @param string $message  Message de fin.
	 * @param array  $stats    {duration,files_processed,db_rows,size_bytes,details}.
	 * @return void
	 */
	public static function finish( $id, $status, $message = '', array $stats = array() ) {
		delete_transient( 'imp_dash_stats' ); // Les compteurs dashboard changent.
		$data = array( 'status' => $status );
		if ( '' !== $message ) {
			$data['message'] = sanitize_text_field( $message );
		}
		foreach ( array( 'duration', 'files_processed', 'db_rows', 'size_bytes' ) as $field ) {
			if ( isset( $stats[ $field ] ) ) {
				$data[ $field ] = (int) $stats[ $field ];
			}
		}
		if ( isset( $stats['details'] ) && is_array( $stats['details'] ) ) {
			$data['details'] = $stats['details'];
		}
		self::update( $id, $data );
	}

	/**
	 * Retire toute valeur ressemblant à un secret avant journalisation.
	 *
	 * @param array $details Détails.
	 * @return array
	 */
	public static function scrub( array $details ) {
		$blacklist = '/pass|pwd|secret|token|key|credential|auth|cookie/i';
		$clean     = array();
		foreach ( $details as $k => $v ) {
			if ( is_string( $k ) && preg_match( $blacklist, $k ) ) {
				$clean[ $k ] = '[redacted]';
				continue;
			}
			if ( is_array( $v ) ) {
				$clean[ $k ] = self::scrub( $v );
			} elseif ( is_scalar( $v ) || null === $v ) {
				$clean[ $k ] = $v;
			} else {
				$clean[ $k ] = '[non-scalar]';
			}
		}
		return $clean;
	}

	/**
	 * Requête filtrée.
	 *
	 * @param array $args {type,status,search,limit,offset,order}.
	 * @return array[] Lignes.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = array();
		$params = array();

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = sanitize_key( (string) $args['type'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(action LIKE %s OR message LIKE %s)';
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$table}";
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$order = ( isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';
		$sql  .= " ORDER BY id {$order} LIMIT %d OFFSET %d";

		$limit   = max( 1, min( 200, (int) ( isset( $args['limit'] ) ? $args['limit'] : 50 ) ) );
		$offset  = max( 0, (int) ( isset( $args['offset'] ) ? $args['offset'] : 0 ) );
		$params[] = $limit;
		$params[] = $offset;

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Compte les entrées selon filtres.
	 *
	 * @param array $args Filtres {type,status,search}.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = array();
		$params = array();
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = sanitize_key( (string) $args['type'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(action LIKE %s OR message LIKE %s)';
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT COUNT(*) FROM {$table}";
		if ( $where ) {
			$sql = $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ), $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Statistiques pour le dashboard (compteurs réels).
	 *
	 * @return array{migrations:int,backups:int,restores:int,packages:int,scans:int,last_migration:?array,last_backup:?array}
	 */
	public static function dashboard_stats() {
		// PERFORMANCE : 7 requêtes SQL (5 COUNT + 2 dernières entrées) à
		// chaque vue dashboard — cachées 60 s, invalidées dans finish().
		$cached = get_transient( 'imp_dash_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = self::table();

		$stats = array(
			'migrations' => 0,
			'backups'    => 0,
			'restores'   => 0,
			'packages'   => 0,
			'scans'      => 0,
		);
		foreach ( array_keys( $stats ) as $type ) {
			$stats[ $type ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s AND status = 'completed'", $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$stats['last_migration'] = $wpdb->get_row( "SELECT * FROM {$table} WHERE type = 'migration' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$stats['last_backup']    = $wpdb->get_row( "SELECT * FROM {$table} WHERE type = 'backup' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		set_transient( 'imp_dash_stats', $stats, 60 );
		return $stats;
	}

	/**
	 * Activité réelle récente (dashboard).
	 *
	 * @param int $limit Nombre.
	 * @return array[]
	 */
	public static function recent( $limit = 8 ) {
		return self::query( array( 'limit' => $limit ) );
	}

	/**
	 * Supprime une entrée.
	 *
	 * @param int $id Identifiant.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Supprime tout.
	 *
	 * @return void
	 */
	public static function clear() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Rétention : supprime les entrées plus vieilles que N jours
	 * (les entrées "running" orphelines sont purgées au passage).
	 *
	 * @param int $days Jours de rétention.
	 * @return void
	 */
	public static function cleanup( $days ) {
		global $wpdb;
		$table = self::table();
		$days  = max( 1, (int) $days );
		$limit = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		// Orphelins "running" depuis plus de 24 h → failed.
		$stale = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'failed', message = CONCAT(IFNULL(message,''), ' [stale]') WHERE status = 'running' AND created < %s", $stale ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created < %s", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
