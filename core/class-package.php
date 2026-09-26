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
 * Packages de migration .infinitymigrate : création, inventaire,
 * analyse avant import (sans extraction), suppression, expiration.
 *
 * Format : archive ZIP contenant manifest.json, files.zip (facultatif)
 * et database.sql.gz (facultatif). Aucun secret dans le manifest.
 */
final class IMP_Package {

	/**
	 * Dossier packages.
	 *
	 * @return string
	 */
	public static function dir() {
		return IMP_Plugin::storage_dir() . 'packages/';
	}

	/**
	 * Liste réelle des packages présents.
	 *
	 * @return array[] {file,name,size,modified,manifest,verified}
	 */
	public static function all() {
		if ( null !== self::$pkg_cache ) {
			return self::$pkg_cache;
		}
		$cached = get_transient( 'imp_packages_list' );
		if ( is_array( $cached ) ) {
			self::$pkg_cache = $cached;
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
				if ( ! is_file( $path ) ) {
					continue;
				}
				if ( ! preg_match( '/\.(infinitymigrate|zip)$/i', $entry ) ) {
					continue;
				}

				// Lecture du manifest embarqué (sans extraction).
				$manifest = null;
				if ( class_exists( 'ZipArchive' ) ) {
					$zip = new ZipArchive();
					if ( true === $zip->open( $path ) ) {
						$raw = $zip->getFromName( 'manifest.json' );
						if ( false !== $raw ) {
							$decoded = json_decode( (string) $raw, true );
							if ( is_array( $decoded ) ) {
								$manifest = $decoded;
							}
						}
						$zip->close();
					}
				}

				$out[] = array(
					'file'     => $entry,
					'path'     => $path,
					'name'     => isset( $manifest['name'] ) ? (string) $manifest['name'] : pathinfo( $entry, PATHINFO_FILENAME ),
					'size'     => (int) @filesize( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
					'modified' => (int) @filemtime( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
					'manifest' => $manifest,
				);
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['modified'] <=> $a['modified'];
			}
		);

		// Chaque entrée ouvre un zip pour lire son manifest — coûteux sur
		// une grosse bibliothèque : caché 60 s comme la liste des backups.
		set_transient( 'imp_packages_list', $out, 60 );
		self::$pkg_cache = $out;
		return $out;
	}

	/** @var array|null Cache statique de la liste (durée de la requête). */
	private static $pkg_cache = null;

	/**
	 * Invalide le cache de la liste des packages (création, suppression,
	 * purge de rétention).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$pkg_cache = null;
		delete_transient( 'imp_packages_list' );
	}

	/**
	 * Assemble un package .infinitymigrate à partir d'un backup existant
	 * (manifest + files.zip + database.sql.gz regroupés dans une archive).
	 *
	 * @param string $backup_dir Dossier du backup.
	 * @param string $name       Nom affiché.
	 * @return array{ok:bool,error?:string,file?:string,size?:int}
	 */
	public static function create_from_backup( $backup_dir, $name = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'IMP-205' );
		}

		$backup_dir = trailingslashit( imp_normalize_path( (string) $backup_dir ) );
		$manifest   = $backup_dir . 'manifest.json';
		if ( ! is_readable( $manifest ) ) {
			return array( 'ok' => false, 'error' => 'IMP-211' );
		}

		$slug = imp_slugify( '' !== $name ? $name : 'package' );
		$file = self::dir() . 'package-' . gmdate( 'Ymd-His' ) . '-' . $slug . '.infinitymigrate';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return array( 'ok' => false, 'error' => 'IMP-206' );
		}

		$zip->addFile( $manifest, 'manifest.json' );
		foreach ( array( 'files.zip', 'database.sql', 'database.sql.gz' ) as $part ) {
			if ( is_file( $backup_dir . $part ) ) {
				$zip->addFile( $backup_dir . $part, $part );
			}
		}
		$zip->close();

		if ( ! is_file( $file ) ) {
			return array( 'ok' => false, 'error' => 'IMP-206' );
		}

		// Le manifest interne référence des tailles : réécrire avec le nom.
		$data = json_decode( (string) file_get_contents( $manifest ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $data ) ) {
			$data['name'] = sanitize_text_field( (string) $name );
			$zip = new ZipArchive();
			if ( true === $zip->open( $file ) ) {
				$zip->addFromString( 'manifest.json', wp_json_encode( $data ) );
				$zip->close();
			}
		}

		IMP_Logger::finish(
			IMP_Logger::start( __( 'Package created', 'infinity-migratex-pro' ), IMP_Logger::TYPE_PACKAGE, array( 'file' => basename( $file ) ) ),
			IMP_Logger::STATUS_COMPLETED,
			'',
			array( 'size_bytes' => (int) @filesize( $file ) ) // phpcs:ignore WordPress.PHP.NoSilencedErrors
		);

		self::flush_cache();

		return array(
			'ok'   => true,
			'file' => $file,
			'size' => (int) @filesize( $file ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
		);
	}

	/**
	 * Analyse un package AVANT tout import : entrées valides, taille,
	 * contenu du manifest, alertes (PHP en racine, entrées suspectes).
	 * Aucune extraction ici.
	 *
	 * @param string $zip_file Package.
	 * @return array{ok:bool,error?:string,manifest?:array,alerts:array[],stats:array}
	 */
	public static function scan( $zip_file ) {
		$alerts = array();
		$stats  = array(
			'entries'      => 0,
			'files_part'   => 0,
			'has_db'       => false,
			'total_uncompressed' => 0,
			'invalid'      => 0,
		);

		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'IMP-205', 'alerts' => $alerts, 'stats' => $stats );
		}

		$size = (int) @filesize( (string) $zip_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size < 22 ) {
			return array( 'ok' => false, 'error' => 'IMP-213', 'alerts' => $alerts, 'stats' => $stats );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( (string) $zip_file ) ) {
			return array( 'ok' => false, 'error' => 'IMP-213', 'alerts' => $alerts, 'stats' => $stats );
		}

		$settings     = imp_settings();
		$max_uncompressed = 4 * 1024 * 1024 * 1024; // garde-fou 4 Go.
		$suspicious_php = false;
		$has_files_zip  = false;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->statIndex( $i );
			if ( ! is_array( $entry ) || ! isset( $entry['name'] ) ) {
				continue;
			}
			$name = (string) $entry['name'];
			$stats['entries']++;
			$stats['total_uncompressed'] += isset( $entry['size'] ) ? (int) $entry['size'] : 0;

			// Garde anti-traversal sur CHAQUE entrée.
			if ( false === imp_validate_zip_entry( $name ) ) {
				$stats['invalid']++;
				$alerts[] = array(
					'level' => 'critical',
					/* translators: %s: archive entry */
					'text'  => sprintf( __( 'Blocked unsafe entry: “%s” (path traversal attempt).', 'infinity-migratex-pro' ), $name ),
				);
				continue;
			}

			if ( 'files.zip' === $name ) {
				$has_files_zip = true;
			}
			if ( preg_match( '#^database\.sql(\.gz)?$#', $name ) ) {
				$stats['has_db'] = true;
			}
		}

		// Analyse réelle du zip imbriqué files.zip (sans l'extraire sur le site).
		if ( $has_files_zip ) {
			$tmp_zip = IMP_Security::tmp_dir() . 'scan-' . wp_generate_password( 10, false, false ) . '.zip';
			$stream  = $zip->getStream( 'files.zip' );
			if ( is_resource( $stream ) ) {
				$out = @fopen( $tmp_zip, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( is_resource( $out ) ) {
					while ( ! feof( $stream ) ) {
						$data = fread( $stream, 1024 * 1024 );
						if ( false === $data ) {
							break;
						}
						fwrite( $out, $data );
					}
					fclose( $out );

					$inner = new ZipArchive();
					if ( true === $inner->open( $tmp_zip ) ) {
						for ( $j = 0; $j < $inner->numFiles; $j++ ) {
							$i_entry = $inner->statIndex( $j );
							if ( ! is_array( $i_entry ) || ! isset( $i_entry['name'] ) ) {
								continue;
							}
							$i_name = (string) $i_entry['name'];
							$stats['total_uncompressed'] += isset( $i_entry['size'] ) ? (int) $i_entry['size'] : 0;

							// Les entrées internes sont préfixées "files/".
							$rel = ( 0 === strpos( $i_name, 'files/' ) ) ? substr( $i_name, 6 ) : $i_name;
							if ( false === imp_validate_zip_entry( $rel ) ) {
								$stats['invalid']++;
								$alerts[] = array(
									'level' => 'critical',
									/* translators: %s: archive entry */
									'text'  => sprintf( __( 'Blocked unsafe file inside the package: “%s” (path traversal attempt).', 'infinity-migratex-pro' ), $rel ),
								);
								continue;
							}
							$stats['files_part']++;

							// PHP dans uploads : alerte classique.
							if ( preg_match( '#^wp-content/uploads/.*\.php\d?$#i', $rel ) || preg_match( '#^wp-content/uploads/.*\.phtml$#i', $rel ) ) {
								$suspicious_php = true;
							}
						}
						$inner->close();
					} else {
						$alerts[] = array(
							'level' => 'critical',
							'text'  => __( 'The files part of this package is not a valid zip archive.', 'infinity-migratex-pro' ),
						);
					}
					@wp_delete_file( $tmp_zip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				fclose( $stream );
			}
		}
		$zip->close();

		if ( $stats['invalid'] > 0 && 'strict' === $settings['package_validation'] ) {
			return array( 'ok' => false, 'error' => 'IMP-214', 'alerts' => $alerts, 'stats' => $stats );
		}

		if ( $stats['total_uncompressed'] > $max_uncompressed ) {
			return array( 'ok' => false, 'error' => 'IMP-215', 'alerts' => $alerts, 'stats' => $stats );
		}

		if ( $suspicious_php ) {
			$alerts[] = array(
				'level' => 'warning',
				'text'  => __( 'The package contains PHP files inside wp-content/uploads — potentially suspicious, needs review before import.', 'infinity-migratex-pro' ),
			);
		}

		if ( ! $stats['has_db'] && 0 === $stats['files_part'] ) {
			return array( 'ok' => false, 'error' => 'IMP-216', 'alerts' => $alerts, 'stats' => $stats );
		}

		// Vérification checksums + manifest.
		$verify = IMP_Integrity::verify_package_file( (string) $zip_file );
		if ( ! $verify['ok'] ) {
			if ( isset( $verify['error'] ) ) {
				return array( 'ok' => false, 'error' => $verify['error'], 'alerts' => $alerts, 'stats' => $stats );
			}
			$alerts[] = array(
				'level' => 'critical',
				'text'  => __( 'Internal checksums do not match — this package is corrupted or has been altered. Import refused.', 'infinity-migratex-pro' ),
			);
			return array( 'ok' => false, 'error' => 'IMP-217', 'alerts' => $alerts, 'stats' => $stats );
		}

		return array(
			'ok'       => true,
			'manifest' => isset( $verify['manifest'] ) ? $verify['manifest'] : null,
			'alerts'   => $alerts,
			'stats'    => $stats,
		);
	}

	/**
	 * Supprime un package (nom de fichier uniquement, aucun traversal).
	 *
	 * @param string $filename Nom dans packages/.
	 * @return bool
	 */
	public static function delete( $filename ) {
		$filename = basename( sanitize_file_name( (string) $filename ) );
		if ( '' === $filename || ! preg_match( '/^[\w.\-]+$/', $filename ) ) {
			return false;
		}
		$path = self::dir() . $filename;
		if ( ! is_file( $path ) ) {
			return false;
		}
		$deleted = wp_delete_file( $path );
		self::flush_cache();
		return $deleted;
	}

	/**
	 * Supprime les packages expirés (réglage rétention jours, 0 = jamais).
	 *
	 * @return int Nombre supprimé.
	 */
	public static function delete_expired() {
		$days = (int) imp_setting( 'package_retention_days', 0 );
		if ( $days < 1 ) {
			return 0;
		}
		$limit = time() - $days * DAY_IN_SECONDS;
		$count = 0;
		foreach ( self::all() as $package ) {
			if ( $package['modified'] < $limit ) {
				if ( self::delete( $package['file'] ) ) {
					$count++;
				}
			}
		}
		return $count;
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
