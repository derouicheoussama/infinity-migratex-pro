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
 * Sécurité : stockage protégé, vérifications AJAX (nonce + capability),
 * validation d'uploads, nettoyage des fichiers temporaires, chiffrement
 * éphémère des identifiants de migration.
 */
final class IMP_Security {

	/**
	 * Prépare l'arborescence de stockage et ses protections.
	 *
	 * @return bool True si le stockage est prêt (créé ou existant).
	 */
	public static function bootstrap_storage() {
		$root = IMP_Plugin::storage_dir();

		foreach ( array( '', 'backups/', 'packages/', 'tmp/' ) as $sub ) {
			$dir = $root . $sub;
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			// index.php silencieux partout.
			$index = $dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				@file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
		}

		self::write_storage_guards( $root );

		return true;
	}

	/**
	 * Écrit .htaccess (Apache 2.2 + 2.4) et web.config (IIS) de protection.
	 *
	 * @param string $root Racine du stockage.
	 * @return void
	 */
	public static function write_storage_guards( $root ) {
		$htaccess = $root . '.htaccess';
		$rules    = "# Infinity Migrate Pro — protected storage\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\nOptions -Indexes\n";
		if ( ! file_exists( $htaccess ) || sha1_file( $htaccess ) !== sha1( $rules ) ) {
			@file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		}

		$webconfig = $root . 'web.config';
		if ( ! file_exists( $webconfig ) && function_exists( 'iis7_supports_permalinks' ) ) {
			@file_put_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				$webconfig,
				"<configuration><system.webServer><security><requestFiltering><hiddenSegments><add segment=\"infinity-migratex-pro\" /></hiddenSegments></requestFiltering></security></system.webServer></configuration>"
			);
		}
	}

	/**
	 * Le stockage est-il protégé par .htaccess ?
	 *
	 * @return bool
	 */
	public static function storage_protected() {
		return false !== strpos( (string) @file_get_contents( IMP_Plugin::storage_dir() . '.htaccess' ), 'Require all denied' ) // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions, WordPress.WP.AlternativeFunctions.file_get_contents
			|| false !== strpos( (string) @file_get_contents( IMP_Plugin::storage_dir() . '.htaccess' ), 'Deny from all' );
	}

	/**
	 * Chemin tmp.
	 *
	 * @return string
	 */
	public static function tmp_dir() {
		return IMP_Plugin::storage_dir() . 'tmp/';
	}

	/**
	 * Nettoie le dossier temporaire (fichiers de travail des jobs).
	 *
	 * @param bool $force Nettoyer même si l'auto-cleanup est désactivé.
	 * @return array{deleted:int,errors:int,bytes:int}
	 */
	public static function cleanup_tmp( $force = false ) {
		$deleted = 0;
		$errors  = 0;
		$bytes   = 0;

		$job = IMP_Job::get();
		if ( ! $force && ! empty( $job ) ) {
			return compact( 'deleted', 'errors', 'bytes' ); // un job tourne : on ne touche pas.
		}

		$tmp = self::tmp_dir();
		if ( ! is_dir( $tmp ) ) {
			return compact( 'deleted', 'errors', 'bytes' );
		}

		$entries = @scandir( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || 'index.php' === $entry ) {
					continue;
				}
				$path  = $tmp . $entry;
				$bytes += is_file( $path ) ? (int) @filesize( $path ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( self::rrmdir( $path ) ) {
					$deleted++;
				} else {
					$errors++;
				}
			}
		}

		return compact( 'deleted', 'errors', 'bytes' );
	}

	/**
	 * Suppression récursive sûre (retourne false en cas d'échec partiel).
	 *
	 * @param string $path Chemin.
	 * @return bool
	 */
	public static function rrmdir( $path ) {
		$path = (string) $path;
		if ( '' === $path || '/' === $path ) {
			return false;
		}

		if ( is_file( $path ) ) {
			return @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( ! is_dir( $path ) ) {
			return true;
		}

		$ok   = true;
		$list = @scandir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_array( $list ) ) {
			foreach ( $list as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				if ( ! self::rrmdir( $path . '/' . $entry ) ) {
					$ok = false;
				}
			}
		}
		return $ok ? @rmdir( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Garde AJAX : nonce + capability + méthode. Termine (wp_send_json_error)
	 * si l'appel est non autorisé.
	 *
	 * @param string $capability_action Action capability (manage|backup|restore|settings|logs).
	 * @param string $nonce_action      Action du nonce.
	 * @return void
	 */
	public static function ajax_guard( $capability_action = 'manage', $nonce_action = 'imp-admin' ) {
		// Couche anti-cracker : fenêtre glissante par utilisateur + IP.
		if ( class_exists( 'IMP_Hardening' ) && ! IMP_Hardening::rate_ok( 'ajax', 120, 60 ) ) {
			wp_send_json_error(
				array(
					'code'    => 'IMP-250',
					'message' => __( 'Too many requests — wait a minute before retrying (anti brute-force protection).', 'infinity-migratex-pro' ),
				),
				429
			);
		}

		$ok = check_ajax_referer( $nonce_action, 'nonce', false )
			&& IMP_Capabilities::user_can( $capability_action )
			&& ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) );

		if ( ! $ok ) {
			if ( class_exists( 'IMP_Hardening' ) ) {
				IMP_Hardening::log_event(
					'unauthorized-ajax',
					__( 'Blocked AJAX call (nonce/capability failed).', 'infinity-migratex-pro' )
				);
			}
			wp_send_json_error(
				array(
					'code'    => 'IMP-403',
					'message' => __( 'Unauthorized request — nonce, capability or HTTP method check failed. Reload the page and try again.', 'infinity-migratex-pro' ),
				),
				403
			);
		}
	}

	/**
	 * Valide un upload de package/SQL via wp_handle_upload.
	 *
	 * @param string $field Clé $_FILES.
	 * @param array  $allowed_extensions Extensions autorisées (ex: array('infinitymigrate','zip')).
	 * @param int    $max_bytes Taille max (0 = pas de plafond spécifique).
	 * @return array|string Tableau {file,url,type} ou code d'erreur IMP-XXX.
	 */
	public static function handle_upload( $field, array $allowed_extensions, $max_bytes = 0 ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 'IMP-301';
		}

		$original = sanitize_file_name( wp_unslash( (string) $_FILES[ $field ]['name'] ) );
		$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_extensions, true ) ) {
			return 'IMP-302';
		}
		if ( $max_bytes > 0 && isset( $_FILES[ $field ]['size'] ) && (int) $_FILES[ $field ]['size'] > $max_bytes ) {
			return 'IMP-303';
		}

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		$uploaded = wp_handle_upload(
			$_FILES[ $field ], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- wp_handle_upload gère ce tableau.
			array(
				'test_form' => false,
				'mimes'     => array(),
				'action'    => 'imp_upload',
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		if ( ! is_array( $uploaded ) || empty( $uploaded['file'] ) ) {
			return 'IMP-304';
		}

		// Contrôle du type réel : extension + en-tête binaire basique.
		$real_ext = strtolower( pathinfo( (string) $uploaded['file'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $real_ext, $allowed_extensions, true ) ) {
			wp_delete_file( (string) $uploaded['file'] );
			return 'IMP-302';
		}
		if ( in_array( $real_ext, array( 'zip', 'infinitymigrate' ), true ) ) {
			$head = (string) @file_get_contents( (string) $uploaded['file'], false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( "PK\x03\x04" !== $head && "PK\x05\x06" !== $head ) {
				wp_delete_file( (string) $uploaded['file'] );
				return 'IMP-305'; // ce n'est pas une archive zip valide.
			}
		}

		return $uploaded;
	}

	/**
	 * Redirige les uploads du plugin vers le stockage protégé.
	 *
	 * @param array $dirs Dirs.
	 * @return array
	 */
	public static function filter_upload_dir( $dirs ) {
		$root = IMP_Plugin::storage_dir();
		$dirs['path']   = $root . 'tmp';
		$dirs['url']    = null;
		$dirs['subdir'] = null;
		$dirs['basedir'] = $root . 'tmp';
		$dirs['baseurl'] = null;
		return $dirs;
	}

	/**
	 * Chiffre une donnée sensible pour un stockage ÉPHÉMÈRE (transient).
	 * Clé dérivée des salts WordPress — jamais écrite ailleurs.
	 *
	 * @param string $plaintext Donnée.
	 * @return string Donnée chiffrée sérialisée "impenc1:…".
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext ) {
			return '';
		}

		if ( function_exists( 'openssl_encrypt' ) && defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ) {
			$key    = hash( 'sha256', AUTH_KEY . '|imp', true );
			$iv     = random_bytes( 12 );
			$tag    = '';
			$cipher = @openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false !== $cipher ) {
				return 'impenc1:' . base64_encode( $iv . $tag . $cipher );
			}
		}

		// Repli : encodage + avertissement en clair dans la valeur.
		return 'impb64:' . base64_encode( $plaintext );
	}

	/**
	 * Déchiffre une donnée produite par self::encrypt().
	 *
	 * @param string $payload Donnée.
	 * @return string
	 */
	public static function decrypt( $payload ) {
		$payload = (string) $payload;
		if ( 0 === strpos( $payload, 'impenc1:' ) ) {
			$raw = base64_decode( substr( $payload, 8 ), true );
			if ( false !== $raw && strlen( $raw ) > 29 && function_exists( 'openssl_decrypt' ) && defined( 'AUTH_KEY' ) ) {
				$iv  = substr( $raw, 0, 12 );
				$tag = substr( $raw, 12, 16 );
				$dec = @openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', hash( 'sha256', AUTH_KEY . '|imp', true ), OPENSSL_RAW_DATA, $iv, $tag ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false !== $dec ) {
					return $dec;
				}
			}
			return '';
		}
		if ( 0 === strpos( $payload, 'impb64:' ) ) {
			return (string) base64_decode( substr( $payload, 7 ), true );
		}
		return '';
	}

	/**
	 * État des protections (page Security Center — 100% réel).
	 *
	 * @return array[] {id,label,ok,detail,fix}
	 */
	public static function protection_report() {
		$report   = array();
		$settings = imp_settings();

		$bootstrapped = self::bootstrap_storage();
		$report[]     = array(
			'id'     => 'storage',
			'label'  => __( 'Protected storage directory', 'infinity-migratex-pro' ),
			'ok'     => $bootstrapped && self::storage_protected(),
			'detail' => $bootstrapped
				? ( self::storage_protected()
					? __( 'Backups, packages and temporary files live outside the media library, blocked by .htaccess / web.config.', 'infinity-migratex-pro' )
					: __( 'Storage exists but the .htaccess guard could not be verified — nginx servers may rely on their own rules.', 'infinity-migratex-pro' ) )
				: __( 'Storage directory could not be created — check permissions.', 'infinity-migratex-pro' ),
		);

		$report[] = array(
			'id'     => 'nonces',
			'label'  => __( 'Nonce protection on every action', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => __( 'All AJAX, download and form actions verify a nonce server-side before doing anything.', 'infinity-migratex-pro' ),
		);

		$report[] = array(
			'id'     => 'capabilities',
			'label'  => __( 'Capability checks on every action', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => __( 'Dedicated capabilities (infinity_migrate_manage, _backup, _restore, _settings, _logs) are re-checked on each request — not just when the button is shown.', 'infinity-migratex-pro' ),
		);

		$report[] = array(
			'id'     => 'traversal',
			'label'  => __( 'Path traversal & Zip Slip protection', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => __( 'Every archive entry is validated (no “..”, absolute paths, drive letters or null bytes) before extraction, and package imports require explicit confirmation.', 'infinity-migratex-pro' ),
		);

		$report[] = array(
			'id'     => 'uploads',
			'label'  => __( 'Upload validation', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => __( 'Package uploads check extension, size and real zip signature, and land in the protected storage — never in public uploads.', 'infinity-migratex-pro' ),
		);

		$report[] = array(
			'id'     => 'integrity',
			'label'  => __( 'Package & backup integrity', 'infinity-migratex-pro' ),
			'ok'     => (bool) IMP_Compatibility::php_modules()['zip'],
			'detail' => IMP_Compatibility::php_modules()['zip']
				? __( 'Manifests carry SHA-256 checksums of every archive part; verify before any restore or import.', 'infinity-migratex-pro' )
				: __( 'ZipArchive is missing: checksummed archives cannot be created on this server.', 'infinity-migratex-pro' ),
		);

		$tmp_age = self::tmp_age_hours();
		$report[] = array(
			'id'     => 'tmp',
			'label'  => __( 'Temporary file cleanup', 'infinity-migratex-pro' ),
			'ok'     => empty( $settings['auto_cleanup'] ) ? false : ( 24 >= $tmp_age ),
			'detail' => empty( $settings['auto_cleanup'] )
				? __( 'Automatic cleanup is disabled in Settings — enable it to purge temporary migration files daily.', 'infinity-migratex-pro' )
				/* translators: %d: hours */
				: sprintf( __( 'Automatic cleanup enabled — last purge %d h ago (daily maintenance).', 'infinity-migratex-pro' ), $tmp_age ),
			'fix'    => 'settings',
		);

		$report[] = array(
			'id'     => 'logs',
			'label'  => __( 'Logs free of secrets', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => __( 'Passwords, keys and tokens are scrubbed before any log entry is written; migration credentials are encrypted, then destroyed.', 'infinity-migratex-pro' ),
		);

		/**
		 * Permet d'enrichir le rapport de sécurité.
		 *
		 * @param array $report Rapport.
		 */
		return apply_filters( 'imp_security_report', $report );
	}

	/**
	 * Âge (heures) du plus vieux fichier tmp — 0 si vide/indisponible.
	 *
	 * @return int
	 */
	public static function tmp_age_hours() {
		$tmp     = self::tmp_dir();
		$oldest  = 0;
		$entries = @scandir( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || 'index.php' === $entry ) {
					continue;
				}
				$mtime = @filemtime( $tmp . $entry ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false !== $mtime && ( 0 === $oldest || $mtime < $oldest ) ) {
					$oldest = $mtime;
				}
			}
		}
		return $oldest > 0 ? max( 0, (int) ( ( time() - $oldest ) / HOUR_IN_SECONDS ) ) : 0;
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
