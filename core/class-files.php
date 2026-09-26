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
 * Moteur fichiers : indexation par lots, copie reprise-où-on-s'était-arrêté,
 * sélection de composants, exclusions. Tout est redémarrable : l'état
 * (dernier chemin traité) est conservé entre deux requêtes.
 */
final class IMP_Files {

	/**
	 * Un chemin relatif appartient-il aux composants sélectionnés ?
	 *
	 * Composants : core, plugins, themes, uploads, wpcontent (mu-plugins,
	 * dropins…), mu-plugins. Fonction pure (testée unitairement).
	 *
	 * @param string   $rel        Chemin relatif (slashes /, sans / initial).
	 * @param string[] $components Composants actifs.
	 * @return bool
	 */
	public static function should_include( $rel, array $components ) {
		$rel = ltrim( (string) $rel, '/' );
		if ( '' === $rel ) {
			return false;
		}

		$zones = array(
			'wp-admin/'    => 'core',
			'wp-includes/' => 'core',
			'wp-content/plugins/' => 'plugins',
			'wp-content/themes/'  => 'themes',
			'wp-content/uploads/' => 'uploads',
			'wp-content/mu-plugins/' => 'mu-plugins',
		);

		$in_zone = '';
		foreach ( $zones as $prefix => $zone ) {
			if ( 0 === strpos( $rel, $prefix ) ) {
				$in_zone = $zone;
				break;
			}
		}

		if ( '' !== $in_zone ) {
			return in_array( $in_zone, $components, true );
		}

		// wp-content hors plugins/themes/uploads/mu-plugins → "wpcontent".
		if ( 0 === strpos( $rel, 'wp-content/' ) ) {
			return in_array( 'wpcontent', $components, true );
		}

		// Fichiers racine : core si sélectionné (wp-config.php exclu par défaut
		// en amont, jamais inclu dans un package).
		return in_array( 'core', $components, true );
	}

	/**
	 * Indexe les fichiers à traiter (une ligne JSON par fichier) dans un
	 * fichier de liste — reprend à partir de $state['last_path'].
	 *
	 * @param array $args   {root, components, exclusions, list_file, skip_config(bool), max_file_bytes}.
	 * @param array $state  État {last_path, files, bytes, skipped}.
	 * @param float $budget Secondes allouées.
	 * @return array{done:bool,files:int,bytes:int,skipped:int,last_path:string}
	 */
	public static function index( array $args, array &$state, $budget ) {
		$root       = imp_normalize_path( $args['root'] );
		$list_file  = (string) $args['list_file'];
		$exclusions = isset( $args['exclusions'] ) ? (array) $args['exclusions'] : array();
		$max_bytes  = isset( $args['max_file_bytes'] ) ? (int) $args['max_file_bytes'] : 0;
		$skip_config = ! empty( $args['skip_config'] );

		$state = wp_parse_args(
			$state,
			array(
				'last_path' => '',
				'files'     => 0,
				'bytes'     => 0,
				'skipped'   => 0,
			)
		);

		$done     = false;
		$started  = microtime( true );
		$found    = ( '' === $state['last_path'] ); // rien à sauter tant qu'on n'a pas repéré le point de reprise.
		$skipping = ( ! $found );

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			$handle = fopen( $list_file, 'ab' ); // append + reprise.
			if ( ! is_resource( $handle ) ) {
				return array( 'done' => false, 'error' => 'IMP-201', 'files' => $state['files'], 'bytes' => $state['bytes'], 'skipped' => $state['skipped'], 'last_path' => $state['last_path'] );
			}

			try {
				fwrite( $handle, '' ); // no-op : verrou d'existence.
				$buffered = 0;

				foreach ( $iterator as $fileinfo ) {
					/** @var SplFileInfo $fileinfo */

					// Reprise : sauter jusqu'à retrouver le dernier chemin traité.
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

					// wp-config.php : jamais indexé (sauf demande explicite du clone local).
					if ( 'wp-config.php' === $rel && $skip_config ) {
						continue;
					}

					if ( ! self::should_include( $rel, $args['components'] ) ) {
						continue;
					}
					if ( imp_match_exclusion( $rel, $exclusions ) ) {
						$state['skipped']++;
						continue;
					}
					if ( $max_bytes > 0 && $size > $max_bytes ) {
						$state['skipped']++;
						continue;
					}

					fwrite( $handle, wp_json_encode( array( 'p' => $rel, 's' => $size ) ) . "\n" );
					$state['files']++;
					$state['bytes'] += $size;
					$state['last_path'] = $abs;

					// Checkpoint périodique pour éviter de tout refaire si timeout.
					if ( ++$buffered >= 500 ) {
						if ( microtime( true ) - $started > $budget * 0.8 ) {
							break;
						}
						$buffered = 0;
					}
				}

				// Fin naturelle du parcours ?
				if ( ! $skipping && microtime( true ) - $started <= $budget ) {
					// L'itérateur est épuisé sans avoir coupé au budget.
					$done = true;
				}
				if ( $skipping ) {
					// Point de reprise introuvable (fichier supprimé) : on repart du début
					// au prochain passage, la liste est reconstruite proprement.
					$state['last_path'] = '';
				}
			} finally {
				fclose( $handle );
			}
		} catch ( Exception $e ) {
			return array(
				'done'      => false,
				'error'     => 'IMP-202',
				'files'     => $state['files'],
				'bytes'     => $state['bytes'],
				'skipped'   => $state['skipped'],
				'last_path' => $state['last_path'],
			);
		}

		$state['done'] = $done;

		return array(
			'done'      => $done,
			'files'     => $state['files'],
			'bytes'     => $state['bytes'],
			'skipped'   => $state['skipped'],
			'last_path' => $state['last_path'],
		);
	}

	/**
	 * Compte les entrées d'un fichier de liste (progression exacte).
	 *
	 * @param string $list_file Fichier de liste.
	 * @return array{files:int,bytes:int}
	 */
	public static function list_totals( $list_file ) {
		$files = 0;
		$bytes = 0;
		if ( is_readable( $list_file ) ) {
			$handle = fopen( $list_file, 'rb' );
			if ( is_resource( $handle ) ) {
				while ( false !== ( $line = fgets( $handle ) ) ) {
					$entry = json_decode( (string) $line, true );
					if ( is_array( $entry ) && isset( $entry['p'] ) ) {
						$files++;
						$bytes += isset( $entry['s'] ) ? (int) $entry['s'] : 0;
					}
				}
				fclose( $handle );
			}
		}
		return array( 'files' => $files, 'bytes' => $bytes );
	}

	/**
	 * Copie les fichiers listés vers une destination — par chunks, avec
	 * reprise au sein même d'un gros fichier (offset). Option : hasher
	 * chaque fichier copié (intégrité post-migration).
	 *
	 * @param array $args    {list_file, source_root, dest_root, hashes_file(?)}.
	 * @param array $state   {line, offset, files_done, bytes_done, total_files, total_bytes, hashes}.
	 * @param float $budget  Secondes allouées.
	 * @return array{done:bool,error?:string,files_done:int,bytes_done:int,total_files:int,total_bytes:int,current:string}
	 */
	public static function copy_chunk( array $args, array &$state, $budget ) {
		$list_file   = (string) $args['list_file'];
		$source_root = imp_normalize_path( $args['source_root'] );
		$dest_root   = imp_normalize_path( $args['dest_root'] );
		$hashes_file = isset( $args['hashes_file'] ) ? (string) $args['hashes_file'] : '';

		$state = wp_parse_args(
			$state,
			array(
				'line'        => 0,
				'offset'      => 0,
				'files_done'  => 0,
				'bytes_done'  => 0,
				'total_files' => -1,
				'total_bytes' => -1,
				'hashes'      => 0,
			)
		);

		if ( $state['total_files'] < 0 ) {
			$totals             = self::list_totals( $list_file );
			$state['total_files'] = $totals['files'];
			$state['total_bytes'] = $totals['bytes'];
		}

		$started = microtime( true );
		$current = '';

		$handle = fopen( $list_file, 'rb' );
		if ( ! is_resource( $handle ) ) {
			return array( 'done' => false, 'error' => 'IMP-203', 'files_done' => $state['files_done'], 'bytes_done' => $state['bytes_done'], 'total_files' => $state['total_files'], 'total_bytes' => $state['total_bytes'], 'current' => $current );
		}

		$hash_out = null;
		if ( '' !== $hashes_file ) {
			$hash_out = fopen( $hashes_file, 'ab' );
		}

		try {
			// Se positionner sur la ligne courante.
			$line_no = 0;
			while ( $line_no < $state['line'] && false !== fgets( $handle ) ) {
				$line_no++;
			}

			while ( microtime( true ) - $started <= $budget ) {
				$pos  = ftell( $handle );
				$line = fgets( $handle );
				if ( false === $line ) {
					$state['done'] = true; // liste épuisée.
					break;
				}

				$entry = json_decode( (string) $line, true );
				if ( ! is_array( $entry ) || empty( $entry['p'] ) ) {
					$state['line']++;
					continue;
				}

				$rel    = (string) $entry['p'];
				$size   = isset( $entry['s'] ) ? (int) $entry['s'] : -1;
				$src    = $source_root . '/' . $rel;
				$dst    = $dest_root . '/' . $rel;
				$current = $rel;

				if ( ! is_file( $src ) ) {
					// Source disparue depuis l'indexation : ignorée, comptée.
					$state['line']++;
					$state['files_done']++;
					continue;
				}

				$dst_dir = dirname( $dst );
				if ( ! is_dir( $dst_dir ) && ! wp_mkdir_p( $dst_dir ) ) {
					return array( 'done' => false, 'error' => 'IMP-204', 'files_done' => $state['files_done'], 'bytes_done' => $state['bytes_done'], 'total_files' => $state['total_files'], 'total_bytes' => $state['total_bytes'], 'current' => $current );
				}

				// Reprise intra-fichier.
				if ( $state['offset'] > 0 ) {
					$src_handle = @fopen( $src, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( ! is_resource( $src_handle ) ) {
						$state['line']++;
						$state['files_done']++;
						$state['offset'] = 0;
						continue;
					}
					fseek( $src_handle, $state['offset'] );
					$ok = self::stream_copy_from( $src_handle, $dst, $state, $hash_out, $rel, true );
					fclose( $src_handle );
				} else {
					$src_handle = @fopen( $src, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( ! is_resource( $src_handle ) ) {
						$state['line']++;
						$state['files_done']++;
						continue;
					}
					$state['hash_ctx'] = hash_init( 'sha256' );
					$ok = self::stream_copy_from( $src_handle, $dst, $state, $hash_out, $rel, false );
					fclose( $src_handle );
				}

				if ( ! $ok ) {
					// Timeout en plein fichier : on garde offset/line pour reprendre.
					return array( 'done' => false, 'files_done' => $state['files_done'], 'bytes_done' => $state['bytes_done'], 'total_files' => $state['total_files'], 'total_bytes' => $state['total_bytes'], 'current' => $current );
				}

				// Fichier terminé.
				$state['line']++;
				$state['files_done']++;
				$state['offset'] = 0;
			}
		} finally {
			fclose( $handle );
			if ( is_resource( $hash_out ) ) {
				fclose( $hash_out );
			}
		}

		$done = ! empty( $state['done'] );

		return array(
			'done'        => $done,
			'files_done'  => $state['files_done'],
			'bytes_done'  => $state['bytes_done'],
			'total_files' => $state['total_files'],
			'total_bytes' => $state['total_bytes'],
			'current'     => $current,
		);
	}

	/**
	 * Copie un flux source vers destination, par blocs de 1 Mo, en mettant
	 * à jour l'état (offset, hash) — retourne false si le budget est atteint.
	 *
	 * @param resource     $src      Flux source positionné.
	 * @param string       $dst      Destination.
	 * @param array        $state    État (offset, bytes_done, hash_ctx…).
	 * @param resource|null $hash_out Flux de sortie des hashs (ou null).
	 * @param string       $rel      Chemin relatif (log).
	 * @param bool         $resume   Reprise : append.
	 * @return bool True si le fichier est complet.
	 */
	private static function stream_copy_from( $src, $dst, array &$state, $hash_out, $rel, $resume ) {
		$mode      = $resume ? 'ab' : 'wb';
		$dstHandle = @fopen( $dst, $mode . ( WP_DEBUG ? '' : '' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_resource( $dstHandle ) ) {
			// Destination non inscriptible : on saute ce fichier (log appelant).
			return true;
		}

		$budget_until = microtime( true ) + 2.5; // max 2,5 s par fichier par step.

		try {
			while ( ! feof( $src ) ) {
				$chunk = fread( $src, 1024 * 1024 );
				if ( false === $chunk || '' === $chunk ) {
					break;
				}
				fwrite( $dstHandle, $chunk );

				$state['offset'] += strlen( $chunk );
				$state['bytes_done'] += strlen( $chunk );
				if ( isset( $state['hash_ctx'] ) && $state['hash_ctx'] instanceof HashContext ) {
					hash_update( $state['hash_ctx'], $chunk );
				}

				if ( microtime( true ) > $budget_until ) {
					return false; // budget fichier écoulé — reprise au prochain step.
				}
			}
		} finally {
			fclose( $dstHandle );
		}

		if ( isset( $state['hash_ctx'] ) && $state['hash_ctx'] instanceof HashContext && is_resource( $hash_out ) ) {
			fwrite( $hash_out, $rel . ' ' . hash_final( $state['hash_ctx'], false ) . "\n" );
			$state['hashes']++;
		}
		unset( $state['hash_ctx'] );

		return true;
	}

	/**
	 * Ajoute des fichiers listés à une archive zip — par lots. Chaque step
	 * rouvre le zip, ajoute N fichiers, referme (écriture effective).
	 *
	 * @param array $args   {list_file, source_root, zip_file, chunk}.
	 * @param array $state  {line, files_done, bytes_done, total_files, total_bytes, failed}.
	 * @param float $budget Secondes allouées.
	 * @return array{done:bool,error?:string,files_done:int,bytes_done:int,total_files:int,total_bytes:int,current:string,failed:int}
	 */
	public static function zip_chunk( array $args, array &$state, $budget ) {
		$list_file   = (string) $args['list_file'];
		$source_root = imp_normalize_path( $args['source_root'] );
		$zip_file    = (string) $args['zip_file'];
		$chunk       = max( 10, (int) ( isset( $args['chunk'] ) ? $args['chunk'] : 100 ) );

		$state = wp_parse_args(
			$state,
			array(
				'line'        => 0,
				'files_done'  => 0,
				'bytes_done'  => 0,
				'total_files' => -1,
				'total_bytes' => -1,
				'failed'      => 0,
			)
		);

		if ( $state['total_files'] < 0 ) {
			$totals               = self::list_totals( $list_file );
			$state['total_files'] = $totals['files'];
			$state['total_bytes'] = $totals['bytes'];
		}

		if ( $state['files_done'] >= $state['total_files'] && $state['total_files'] >= 0 ) {
			return array(
				'done'        => true,
				'files_done'  => $state['files_done'],
				'bytes_done'  => $state['bytes_done'],
				'total_files' => $state['total_files'],
				'total_bytes' => $state['total_bytes'],
				'current'     => '',
				'failed'      => $state['failed'],
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'done' => false, 'error' => 'IMP-205', 'files_done' => 0, 'bytes_done' => 0, 'total_files' => $state['total_files'], 'total_bytes' => $state['total_bytes'], 'current' => '', 'failed' => 0 );
		}

		$started = microtime( true );
		$current = '';
		$added   = 0;

		$handle = fopen( $list_file, 'rb' );
		if ( ! is_resource( $handle ) ) {
			return array( 'done' => false, 'error' => 'IMP-203', 'files_done' => $state['files_done'], 'bytes_done' => $state['bytes_done'], 'total_files' => $state['total_files'], 'total_bytes' => $state['total_bytes'], 'current' => '', 'failed' => $state['failed'] );
		}

		try {
			$line_no = 0;
			while ( $line_no < $state['line'] && false !== fgets( $handle ) ) {
				$line_no++;
			}

			while ( $added < $chunk && microtime( true ) - $started <= $budget ) {
				$line = fgets( $handle );
				if ( false === $line ) {
					$state['done'] = true;
					break;
				}

				$entry = json_decode( (string) $line, true );
				if ( ! is_array( $entry ) || empty( $entry['p'] ) ) {
					$state['line']++;
					continue;
				}

				$rel  = (string) $entry['p'];
				$size = isset( $entry['s'] ) ? (int) $entry['s'] : 0;
				$src  = $source_root . '/' . $rel;
				$current = $rel;
				$state['line']++;

				if ( ! is_file( $src ) ) {
					$state['files_done']++;
					$state['failed']++;
					continue;
				}

				// Ouverture paresseuse du zip : une seule par step.
				if ( ! isset( $zip ) ) {
					$zip = new ZipArchive();
					$flags = file_exists( $zip_file ) ? null : ZipArchive::CREATE;
					if ( true !== $zip->open( $zip_file, $flags ) ) {
						return array( 'done' => false, 'error' => 'IMP-206', 'files_done' => $state['files_done'], 'bytes_done' => $state['bytes_done'], 'total_files' => $state['total_files'], 'total_bytes' => $state['total_bytes'], 'current' => $current, 'failed' => $state['failed'] );
					}
				}

				if ( $zip->addFile( $src, 'files/' . $rel ) ) {
					$state['files_done']++;
					$state['bytes_done'] += $size;
				} else {
					$state['failed']++;
				}
				$added++;
			}
		} finally {
			if ( isset( $zip ) && $zip instanceof ZipArchive ) {
				$zip->close();
				unset( $zip );
			}
			fclose( $handle );
		}

		$done = ( ! empty( $state['done'] ) ) || ( $state['total_files'] >= 0 && $state['files_done'] >= $state['total_files'] );

		return array(
			'done'        => $done,
			'files_done'  => $state['files_done'],
			'bytes_done'  => $state['bytes_done'],
			'total_files' => $state['total_files'],
			'total_bytes' => $state['total_bytes'],
			'current'     => $current,
			'failed'      => $state['failed'],
		);
	}
}
