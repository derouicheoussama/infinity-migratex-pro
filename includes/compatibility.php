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

/**
 * Compatibilité environnement : vérifications réelles + état système
 * réutilisé par le dashboard (System Health) et le mode diagnostic.
 */
final class IMP_Compatibility {

	/**
	 * PHP >= 7.4 ?
	 *
	 * @return bool
	 */
	public static function php_ok() {
		return version_compare( PHP_VERSION, '7.4', '>=' );
	}

	/**
	 * WordPress >= 5.8 ?
	 *
	 * @return bool
	 */
	public static function wp_ok() {
		global $wp_version;
		return version_compare( $wp_version, '5.8', '>=' );
	}

	/**
	 * Liste des modules PHP utiles et leur présence.
	 *
	 * @return array<string,bool>
	 */
	public static function php_modules() {
		return array(
			'zlib'    => extension_loaded( 'zlib' ),
			'zip'     => extension_loaded( 'zip' ),
			'openssl' => extension_loaded( 'openssl' ),
			'mbstring' => extension_loaded( 'mbstring' ),
			'curl'    => extension_loaded( 'curl' ),
			'json'    => extension_loaded( 'json' ),
		);
	}

	/**
	 * Limites PHP réelles.
	 *
	 * @return array{memory_limit:string,memory_bytes:int,upload_max:string,post_max:string,max_execution:int,max_input:int,disable_functions:string}
	 */
	public static function php_limits() {
		$memory = ini_get( 'memory_limit' );
		return array(
			'memory_limit'     => $memory ? $memory : 'unknown',
			'memory_bytes'     => self::to_bytes( $memory ),
			'upload_max'       => ini_get( 'upload_max_filesize' ) ?: 'unknown',
			'post_max'         => ini_get( 'post_max_size' ) ?: 'unknown',
			'max_execution'    => (int) ini_get( 'max_execution_time' ),
			'max_input'        => (int) ini_get( 'max_input_vars' ),
			'disable_functions' => (string) ini_get( 'disable_functions' ),
		);
	}

	/**
	 * Convertit une valeur ini (128M, 2G…) en octets.
	 *
	 * @param string|int|null $value Valeur.
	 * @return int
	 */
	public static function to_bytes( $value ) {
		if ( null === $value || '' === $value || -1 === (int) $value && ( is_int( $value ) || '-1' === $value ) ) {
			return -1; // illimité
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		$unit = strtolower( substr( $value, -1 ) );
		$num  = (int) $value;
		switch ( $unit ) {
			case 'g':
				$num *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$num *= 1024 * 1024;
				break;
			case 'k':
				$num *= 1024;
				break;
		}
		return $num;
	}

	/**
	 * Version MySQL/MariaDB réelle.
	 *
	 * @return string
	 */
	public static function db_version() {
		global $wpdb;
		$version = $wpdb->db_version();
		$info    = '';
		if ( method_exists( $wpdb, 'db_server_info' ) ) {
			$info = (string) $wpdb->db_server_info();
		}
		if ( '' !== $info && false !== stripos( $info, 'mariadb' ) ) {
			return 'MariaDB ' . $version;
		}
		return 'MySQL ' . $version;
	}

	/**
	 * Serveur web déduit.
	 *
	 * @return string
	 */
	public static function server_software() {
		$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		return '' !== $server ? $server : 'unknown';
	}

	/**
	 * Vérifications de santé (dashboard + diagnostic). 100% réelles.
	 *
	 * @return array[] Chaque item : {id,label,status:pass|warn|error,value,hint}
	 */
	public static function health_checks() {
		$checks   = array();
		$limits   = self::php_limits();
		$modules  = self::php_modules();
		$stats    = IMP_Site_Stats::get();
		$disk     = IMP_Site_Stats::disk_free();

		$checks[] = array(
			'id'     => 'php',
			'label'  => __( 'PHP version', 'infinity-migratex-pro' ),
			'status' => self::php_ok() ? 'pass' : 'error',
			'value'  => PHP_VERSION,
			'hint'   => self::php_ok()
				? __( 'Compatible (7.4 or newer).', 'infinity-migratex-pro' )
				: __( 'PHP 7.4 or newer is required.', 'infinity-migratex-pro' ),
		);

		global $wp_version;
		$checks[] = array(
			'id'     => 'wordpress',
			'label'  => __( 'WordPress version', 'infinity-migratex-pro' ),
			'status' => self::wp_ok() ? 'pass' : 'error',
			'value'  => $wp_version,
			'hint'   => self::wp_ok()
				? __( 'Compatible (5.8 or newer).', 'infinity-migratex-pro' )
				: __( 'WordPress 5.8 or newer is required.', 'infinity-migratex-pro' ),
		);

		$mem = $limits['memory_bytes'];
		$checks[] = array(
			'id'     => 'memory',
			'label'  => __( 'PHP memory limit', 'infinity-migratex-pro' ),
			'status' => ( -1 === $mem || $mem >= 128 * MB_IN_BYTES ) ? 'pass' : 'warn',
			'value'  => ( -1 === $mem ) ? __( 'Unlimited', 'infinity-migratex-pro' ) : $limits['memory_limit'],
			'hint'   => __( '128M or more recommended for large sites. The plugin works by small chunks to stay within limits.', 'infinity-migratex-pro' ),
		);

		$checks[] = array(
			'id'     => 'max_execution',
			'label'  => __( 'Max execution time', 'infinity-migratex-pro' ),
			'status' => ( 0 === $limits['max_execution'] || $limits['max_execution'] >= 30 ) ? 'pass' : 'warn',
			'value'  => 0 === $limits['max_execution'] ? __( 'Unlimited', 'infinity-migratex-pro' ) : $limits['max_execution'] . 's',
			'hint'   => __( 'Long operations are chunked and resumable, so a low limit does not block migrations.', 'infinity-migratex-pro' ),
		);

		$upload = self::to_bytes( $limits['upload_max'] );
		$checks[] = array(
			'id'     => 'upload',
			'label'  => __( 'Upload max size', 'infinity-migratex-pro' ),
			'status' => ( -1 === $upload || $upload >= 8 * MB_IN_BYTES ) ? 'pass' : 'warn',
			'value'  => $limits['upload_max'],
			'hint'   => __( 'Limits the size of packages you can upload for import. Use “Import from server storage” for very large packages.', 'infinity-migratex-pro' ),
		);

		$checks[] = array(
			'id'     => 'zip',
			'label'  => __( 'ZipArchive extension', 'infinity-migratex-pro' ),
			'status' => $modules['zip'] ? 'pass' : 'error',
			'value'  => $modules['zip'] ? __( 'Available', 'infinity-migratex-pro' ) : __( 'Missing', 'infinity-migratex-pro' ),
			'hint'   => __( 'Required to create packages and backups archives. Ask your host to enable the PHP zip extension.', 'infinity-migratex-pro' ),
		);

		$checks[] = array(
			'id'     => 'zlib',
			'label'  => __( 'Zlib extension (gzip)', 'infinity-migratex-pro' ),
			'status' => $modules['zlib'] ? 'pass' : 'warn',
			'value'  => $modules['zlib'] ? __( 'Available', 'infinity-migratex-pro' ) : __( 'Missing', 'infinity-migratex-pro' ),
			'hint'   => __( 'Used to compress database dumps. Without it, dumps are stored uncompressed.', 'infinity-migratex-pro' ),
		);

		$checks[] = array(
			'id'     => 'database',
			'label'  => __( 'Database', 'infinity-migratex-pro' ),
			'status' => $stats['db_tables'] > 0 ? 'pass' : 'error',
			'value'  => self::db_version(),
			'hint'   => $stats['db_tables'] > 0
				? sprintf( /* translators: 1: table count 2: size */ __( '%1$s tables — %2$s', 'infinity-migratex-pro' ), number_format_i18n( $stats['db_tables'] ), imp_format_bytes( $stats['db_bytes'] ) )
				: __( 'No tables readable — check database credentials.', 'infinity-migratex-pro' ),
		);

		$site_bytes = $stats['bytes'];
		$needed     = $site_bytes * 2; // package + marge restauration.
		$disk_ok    = ( 0 === $disk ) || ( $disk > $needed );
		$checks[]   = array(
			'id'     => 'disk',
			'label'  => __( 'Free disk space', 'infinity-migratex-pro' ),
			'status' => $disk_ok ? 'pass' : 'error',
			'value'  => ( 0 === $disk ) ? __( 'Unknown', 'infinity-migratex-pro' ) : imp_format_bytes( $disk ),
			'hint'   => $disk_ok
				? sprintf( /* translators: %s: site size */ __( 'Site size: %s — enough room for a full backup.', 'infinity-migratex-pro' ), imp_format_bytes( $site_bytes ) )
				: sprintf( /* translators: %s: site size */ __( 'Not enough space: the site weighs %s and backups need roughly twice that.', 'infinity-migratex-pro' ), imp_format_bytes( $site_bytes ) ),
		);

		$storage_writable = wp_is_writable( IMP_Plugin::storage_dir() );
		$checks[]         = array(
			'id'     => 'storage',
			'label'  => __( 'Backup storage writable', 'infinity-migratex-pro' ),
			'status' => $storage_writable ? 'pass' : 'error',
			'value'  => IMP_Plugin::storage_dir(),
			'hint'   => $storage_writable
				? __( 'Backups and packages can be written here.', 'infinity-migratex-pro' )
				: __( 'The storage directory is not writable — check permissions or change the location in Settings.', 'infinity-migratex-pro' ),
		);

		$uploads_writable = wp_is_writable( wp_get_upload_dir()['basedir'] );
		$checks[]         = array(
			'id'     => 'uploads',
			'label'  => __( 'Uploads directory writable', 'infinity-migratex-pro' ),
			'status' => $uploads_writable ? 'pass' : 'error',
			'value'  => wp_get_upload_dir()['basedir'],
			'hint'   => $uploads_writable
				? __( 'Media and uploaded packages OK.', 'infinity-migratex-pro' )
				: __( 'The uploads directory is not writable.', 'infinity-migratex-pro' ),
		);

		$cron_ok = false !== wp_next_scheduled( 'imp_cron_maintenance' ) || doing_cron();
		$checks[] = array(
			'id'     => 'cron',
			'label'  => __( 'WP-Cron', 'infinity-migratex-pro' ),
			'status' => $cron_ok ? 'pass' : 'warn',
			'value'  => $cron_ok ? __( 'Scheduled', 'infinity-migratex-pro' ) : __( 'Not scheduled', 'infinity-migratex-pro' ),
			'hint'   => __( 'Used for scheduled backups and automatic cleanup. Disabled WP-Cron means schedules will not run on time.', 'infinity-migratex-pro' ),
		);

		/**
		 * Permet d'ajouter des vérifications (extensions, hébergement…).
		 *
		 * @param array $checks Vérifications.
		 */
		return apply_filters( 'imp_health_checks', $checks );
	}

	/**
	 * Résumé global (badge du header).
	 *
	 * @param array|null $checks Résultats (recalcul si null).
	 * @return string healthy|warning|error
	 */
	public static function overall_status( $checks = null ) {
		$checks = null === $checks ? self::health_checks() : $checks;
		$status = 'healthy';
		foreach ( $checks as $check ) {
			if ( 'error' === $check['status'] ) {
				return 'error';
			}
			if ( 'warn' === $check['status'] ) {
				$status = 'warning';
			}
		}
		return $status;
	}
}

/**
 * Notice d'administration si l'environnement est incompatible.
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! IMP_Compatibility::php_ok() || ! IMP_Compatibility::wp_ok() ) {
			echo '<div class="notice notice-error"><p><strong>Infinity MigrateX Pro :</strong> ' .
				esc_html__( 'this site runs an incompatible PHP or WordPress version. The plugin stays inactive to avoid errors. PHP 7.4+ and WordPress 5.8+ are required.', 'infinity-migratex-pro' ) .
				'</p></div>';
		}
	}
);
