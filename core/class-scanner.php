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
 * Scanner de sécurité : checksums du core WordPress (API officielle),
 * fichiers PHP dans uploads, motifs potentiellement suspects, fichiers
 * volumineux et permissions dangereuses. Aucun verdict "malware" sans
 * base technique : formulations "potentially suspicious / needs review".
 */
final class IMP_Scanner {

	/**
	 * Motifs signalés (jamais seuls un verdict de malware).
	 *
	 * @return array<string>
	 */
	public static function suspicious_patterns() {
		return array(
			'eval\s*\(\s*base64_decode\s*\(',
			'eval\s*\(\s*gzinflate\s*\(',
			'eval\s*\(\s*str_rot13\s*\(',
			'eval\s*\(\s*gzuncompress\s*\(',
			'base64_decode\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)',
			'gzinflate\s*\(\s*base64_decode',
			'\bsystem\s*\(\s*\$_(POST|GET|REQUEST)',
			'\bshell_exec\s*\(\s*\$_(POST|GET|REQUEST)',
			'\bpassthru\s*\(\s*\$_(POST|GET|REQUEST)',
			'\bproc_open\s*\(\s*\$_(POST|GET|REQUEST)',
			'@\$_\[\]\s*=.*;\s*@eval\s*\(\s*@\$',
			'chr\s*\(\s*\d+\s*\)\s*\.\s*chr\s*\(\s*\d+\s*\).*eval',
			'\$GLOBALS\s*\[\s*[\'"]\\x',
			'goto\s+\w+;\s*\$',
		);
	}

	/**
	 * Extensions exécutables à signaler hors wp-admin/wp-includes.
	 *
	 * @return array<string>
	 */
	public static function risky_extensions() {
		return array( 'php', 'phtml', 'php5', 'php7', 'phar', 'cgi', 'pl', 'py', 'sh', 'exe', 'so', 'dll', 'bat' );
	}

	/* ------------------------------------------------------------------ *
	 * Phases du job "scan"
	 * ------------------------------------------------------------------ */

	/**
	 * Phase checksums core : compare les fichiers du core à la référence
	 * officielle WordPress.org (md5). Reprise par chemin.
	 *
	 * @param array $job     Job.
	 * @param float $budget  Secondes.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_core( array &$job, $budget ) {
		$state = isset( $job['state']['core'] ) ? $job['state']['core'] : array();
		$state = wp_parse_args(
			$state,
			array(
				'checksums' => null,
				'api_ok'    => false,
				'checked'   => 0,
				'modified'  => array(),
				'missing'   => array(),
				'added'     => array(),
			)
		);

		$started = microtime( true );

		// Récupérer les checksums officiels (cache 24 h).
		if ( null === $state['checksums'] ) {
			$state['checksums'] = self::core_checksums();
			$state['api_ok']    = is_array( $state['checksums'] ) && ! empty( $state['checksums'] );
		}

		if ( ! $state['api_ok'] ) {
			$job['state']['core'] = $state;
			return array(
				'done'    => true,
				'message' => __( 'WordPress.org checksum API unreachable — core verification skipped this time.', 'infinity-migratex-pro' ),
			);
		}

		$root     = imp_normalize_path( ABSPATH );
		$done     = true;

		foreach ( $state['checksums'] as $rel => $expected_md5 ) {
			$rel = str_replace( '\\', '/', (string) $rel );

			// Fichiers wp-content ignorés par l'API core.
			if ( 0 === strpos( $rel, 'wp-content/' ) ) {
				unset( $state['checksums'][ $rel ] );
				continue;
			}

			if ( microtime( true ) - $started > $budget ) {
				$done = false;
				break;
			}

			$path = $root . '/' . $rel;
			if ( ! is_file( $path ) ) {
				$state['missing'][] = $rel;
			} else {
				$actual = md5_file( $path );
				$state['checked']++;
				if ( false !== $actual && $actual !== $expected_md5 ) {
					$state['modified'][] = $rel;
				}
			}
			unset( $state['checksums'][ $rel ] ); // progression : on retire au fil de l'eau.
		}

		// Fichiers core ajoutés non prévus ? (racine uniquement, exclusions log).
		if ( $done ) {
			foreach ( array( '.php' ) as $ext ) {
				foreach ( (array) glob( $root . '/*.' . $ext ) as $extra ) {
					$rel = ltrim( str_replace( $root, '', imp_normalize_path( $extra ) ), '/' );
					$known = array( 'index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 'license.php' );
					if ( ! in_array( $rel, $known, true ) ) {
						$state['added'][] = $rel;
					}
				}
			}
		}

		$job['state']['core'] = $state;

		$message = sprintf(
			/* translators: %d: files checked */
			__( 'WordPress core: %d files verified.', 'infinity-migratex-pro' ),
			$state['checked']
		);
		if ( ! empty( $state['modified'] ) ) {
			/* translators: %d: files */
			$message .= ' ' . sprintf( __( '%d modified core files.', 'infinity-migratex-pro' ), count( $state['modified'] ) );
		}
		if ( ! empty( $state['missing'] ) ) {
			/* translators: %d: files */
			$message .= ' ' . sprintf( __( '%d missing core files.', 'infinity-migratex-pro' ), count( $state['missing'] ) );
		}

		return array(
			'done'    => $done,
			'message' => $message,
		);
	}

	/**
	 * Checksums officiels du core (cache 24 h, aucune donnée envoyée
	 * hormis version + locale).
	 *
	 * @return array|null
	 */
	public static function core_checksums() {
		global $wp_version;

		$cache = get_transient( 'imp_core_checksums' );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		$locale = get_locale();
		$url    = add_query_arg(
			array(
				'version' => $wp_version,
				'locale'  => $locale,
			),
			'https://api.wordpress.org/core/checksums/1.0/'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( 'imp_core_checksums', array(), HOUR_IN_SECONDS ); // retry plus tard.
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
			return null;
		}

		// Le set fr renvoie souvent les mêmes clés que en_US : on fusionne.
		$checksums = array();
		if ( isset( $body['checksums']['en_US'] ) && is_array( $body['checksums']['en_US'] ) ) {
			$checksums = $body['checksums']['en_US'];
		}
		if ( 'en_US' !== $locale && isset( $body['checksums'][ $locale ] ) && is_array( $body['checksums'][ $locale ] ) ) {
			$checksums = array_merge( $checksums, $body['checksums'][ $locale ] );
		}

		if ( empty( $checksums ) ) {
			return null;
		}

		set_transient( 'imp_core_checksums', $checksums, DAY_IN_SECONDS );
		return $checksums;
	}

	/**
	 * Phase scan fichiers : reprise par last_path (comme l'indexation).
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_files( array &$job, $budget ) {
		$settings = imp_settings();
		$large_mb = max( 1, (int) $settings['scan_large_file_mb'] );

		$state = isset( $job['state']['files'] ) ? $job['state']['files'] : array();
		$state = wp_parse_args(
			$state,
			array(
				'last_path' => '',
				'scanned'   => 0,
				'bytes'     => 0,
				'findings'  => array(),
			)
		);

		$root    = imp_normalize_path( ABSPATH );
		$started = microtime( true );
		$done    = false;
		$skipping = ( '' !== $state['last_path'] );

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ( $iterator as $fileinfo ) {
				/** @var SplFileInfo $fileinfo */

				if ( $skipping ) {
					if ( $fileinfo->getPathname() === $state['last_path'] ) {
						$skipping = false;
					}
					continue;
				}

				try {
					if ( ! $fileinfo->isFile() || $fileinfo->isLink() ) {
						continue;
					}
					$size = $fileinfo->getSize();
				} catch ( RuntimeException $e ) {
					continue;
				}

				if ( microtime( true ) - $started > $budget ) {
					break;
				}

				$abs = imp_normalize_path( $fileinfo->getPathname() );
				$rel = ltrim( str_replace( $root, '', $abs ), '/' );
				$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );

				$state['scanned']++;
				$state['bytes'] += $size;
				$state['last_path'] = $abs;

				// 1. PHP dans uploads.
				if ( preg_match( '#^wp-content/uploads/.*\.(php\d?|phtml)$#i', $rel ) ) {
					$state['findings'][] = array(
						'level' => 'critical',
						'type'  => 'php-in-uploads',
						'file'  => $rel,
						'size'  => $size,
						/* translators: %s: file path */
						'text'  => sprintf( __( 'Executable PHP file inside uploads: %s — potentially suspicious, needs review.', 'infinity-migratex-pro' ), $rel ),
					);
				}

				// 2. Extension exécutable ailleurs que core/plugins/themes.
				if ( in_array( $ext, array( 'exe', 'so', 'dll', 'bat', 'sh', 'cgi' ), true ) && 0 !== strpos( $rel, 'wp-content/plugins/' ) ) {
					$state['findings'][] = array(
						'level' => 'warning',
						'type'  => 'risky-ext',
						'file'  => $rel,
						'size'  => $size,
						/* translators: %s: file path */
						'text'  => sprintf( __( 'Unexpected executable file: %s — needs review.', 'infinity-migratex-pro' ), $rel ),
					);
				}

				// 3. Fichier volumineux.
				if ( $size > $large_mb * MB_IN_BYTES ) {
					$state['findings'][] = array(
						'level' => 'info',
						'type'  => 'large-file',
						'file'  => $rel,
						'size'  => $size,
						'text'  => sprintf(
							/* translators: 1: file path 2: size */
							__( 'Large file: %1$s (%2$s) — will slow down backups and migrations.', 'infinity-migratex-pro' ),
							$rel,
							imp_format_bytes( $size )
						),
					);
				}

				// 4. Permissions world-writable (systèmes POSIX).
				if ( function_exists( 'fileperms' ) && ! imp_is_windows() ) {
					$perms = @fileperms( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( false !== $perms && 0 === ( $perms & 2 ) ) { // bit écriture « autres » = 0b10.
						$state['findings'][] = array(
							'level' => 'warning',
							'type'  => 'perms',
							'file'  => $rel,
							'size'  => $size,
							/* translators: %s: file path */
							'text'  => sprintf( __( 'World-writable permissions on %s — tighten to 644.', 'infinity-migratex-pro' ), $rel ),
						);
					}
				}

				// 5. Contenu suspect (PHP, taille bornée).
				if ( preg_match( '#^wp-content/(uploads|upgrade)/#', $rel ) && in_array( $ext, array( 'php', 'phtml' ), true ) && $size < 2 * MB_IN_BYTES ) {
					$content = (string) @file_get_contents( $abs, false, null, 0, 512 * 1024 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					foreach ( self::suspicious_patterns() as $pattern ) {
						if ( preg_match( '/' . $pattern . '/i', $content ) ) {
							$state['findings'][] = array(
								'level' => 'critical',
								'type'  => 'pattern',
								'file'  => $rel,
								'size'  => $size,
								/* translators: 1: file path */
								'text'  => sprintf( __( 'Potentially suspicious code pattern in %1$s — needs review before trusting this file.', 'infinity-migratex-pro' ), $rel ),
							);
							break;
						}
					}
				}

				// Budget intégré aussi à l'intérieur de la boucle (gros dossiers).
				if ( microtime( true ) - $started > $budget ) {
					break;
				}
			}

			if ( ! $skipping && microtime( true ) - $started <= $budget ) {
				$done = true;
			}
		} catch ( Exception $e ) {
			return array( 'done' => false, 'error' => 'IMP-202', 'message' => '' );
		}

		$job['state']['files'] = $state;

		return array(
			'done'    => $done,
			'message' => sprintf(
				/* translators: 1: files scanned 2: findings */
				__( '%1$d files scanned — %2$d findings.', 'infinity-migratex-pro' ),
				$state['scanned'],
				count( $state['findings'] )
			),
		);
	}

	/**
	 * Phase finale : compile le rapport réel et le stocke.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string}
	 */
	public static function step_finalize( array &$job ) {
		$core  = isset( $job['state']['core'] ) ? $job['state']['core'] : array();
		$files = isset( $job['state']['files'] ) ? $job['state']['files'] : array();

		$findings = array();
		foreach ( (array) $core['modified'] as $rel ) {
			$findings[] = array(
				'level' => 'critical',
				'type'  => 'core-modified',
				'file'  => $rel,
				/* translators: %s: file path */
				'text'  => sprintf( __( 'WordPress core file modified: %s — restore the core or investigate (checksum mismatch).', 'infinity-migratex-pro' ), $rel ),
			);
		}
		foreach ( (array) $core['missing'] as $rel ) {
			$findings[] = array(
				'level' => 'warning',
				'type'  => 'core-missing',
				'file'  => $rel,
				/* translators: %s: file path */
				'text'  => sprintf( __( 'WordPress core file missing: %s — reinstall the core.', 'infinity-migratex-pro' ), $rel ),
			);
		}
		foreach ( (array) $core['added'] as $rel ) {
			$findings[] = array(
				'level' => 'warning',
				'type'  => 'core-added',
				'file'  => $rel,
				/* translators: %s: file path */
				'text'  => sprintf( __( 'Unexpected PHP file at the site root: %s — needs review.', 'infinity-migratex-pro' ), $rel ),
			);
		}

		$findings = array_merge( $findings, (array) $files['findings'] );

		// Tri par sévérité, plafonné à 500 entrées (rapport lisible).
		$levels = array( 'critical' => 0, 'warning' => 1, 'info' => 2 );
		usort(
			$findings,
			static function ( $a, $b ) use ( $levels ) {
				return $levels[ $a['level'] ] <=> $levels[ $b['level'] ];
			}
		);
		$truncated = count( $findings ) > 500;
		$findings  = array_slice( $findings, 0, 500 );

		$summary = array(
			'critical' => 0,
			'warning'  => 0,
			'info'     => 0,
		);
		foreach ( $findings as $finding ) {
			$summary[ $finding['level'] ]++;
		}

		$report = array(
			'completed'     => true,
			'date'          => current_time( 'mysql' ),
			'duration'      => microtime( true ) - (float) $job['started_at'],
			'files_scanned' => (int) $files['scanned'],
			'bytes_scanned' => (int) $files['bytes'],
			'core_checked'  => (int) $core['checked'],
			'core_api_ok'   => ! empty( $core['api_ok'] ),
			'findings'      => $findings,
			'summary'       => $summary,
			'truncated'     => $truncated,
			'version'       => IMP_VERSION,
		);

		update_option( 'imp_last_scan', $report, false );
		$job['result'] = $report;

		return array(
			'done'    => true,
			'message' => sprintf(
				/* translators: 1: critical 2: warnings 3: info */
				__( 'Scan complete — %1$d critical, %2$d warnings, %3$d informational.', 'infinity-migratex-pro' ),
				$summary['critical'],
				$summary['warning'],
				$summary['info']
			),
		);
	}

	/**
	 * Dernier rapport enregistré (dashboard / security center).
	 *
	 * @return array|null
	 */
	public static function last_report() {
		$report = get_option( 'imp_last_scan' );
		return is_array( $report ) && ! empty( $report['completed'] ) ? $report : null;
	}
}
