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
 * Vérifications d'intégrité : checksums des parties d'un backup/package,
 * comparaison source/destination après migration, comptage post-restore.
 */
final class IMP_Integrity {

	/**
	 * Vérifie un backup/package contre son manifest (checksums réels).
	 *
	 * @param string $dir Dossier du backup (contient manifest.json).
	 * @return array{ok:bool,parts:array[],missing:array[],error?:string}
	 */
	public static function verify_backup_dir( $dir ) {
		$dir     = trailingslashit( imp_normalize_path( (string) $dir ) );
		$missing = array();
		$parts   = array();

		$manifest_file = $dir . 'manifest.json';
		if ( ! is_readable( $manifest_file ) ) {
			return array(
				'ok'      => false,
				'parts'   => $parts,
				'missing' => $missing,
				'error'   => 'IMP-211',
			);
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $manifest ) || empty( $manifest['checksums'] ) || ! is_array( $manifest['checksums'] ) ) {
			return array(
				'ok'      => false,
				'parts'   => $parts,
				'missing' => $missing,
				'error'   => 'IMP-212',
			);
		}

		$algo = isset( $manifest['checksum_algo'] ) ? (string) $manifest['checksum_algo'] : 'sha256';

		foreach ( $manifest['checksums'] as $part => $expected ) {
			$path = $dir . (string) $part;
			if ( ! is_file( $path ) ) {
				$missing[] = (string) $part;
				continue;
			}
			$actual   = hash_file( $algo, $path );
			$parts[]  = array(
				'part'     => (string) $part,
				'expected' => (string) $expected,
				'actual'   => false === $actual ? '' : $actual,
				'match'    => ( $actual === (string) $expected ),
			);
		}

		$ok = empty( $missing );
		foreach ( $parts as $part ) {
			if ( ! $part['match'] ) {
				$ok = false;
			}
		}

		return array(
			'ok'      => $ok,
			'parts'   => $parts,
			'missing' => $missing,
		);
	}

	/**
	 * Vérifie une archive package (.infinitymigrate) : lisible, manifest
	 * présent, checksums internes conformes. Ne ZIP PAS extraire ici.
	 *
	 * @param string $zip_file Fichier package.
	 * @return array{ok:bool,error?:string,manifest?:array,files?:int,checksums_ok?:bool}
	 */
	public static function verify_package_file( $zip_file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'IMP-205' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file ) ) {
			return array( 'ok' => false, 'error' => 'IMP-213' );
		}

		$manifest_raw = $zip->getFromName( 'manifest.json' );
		if ( false === $manifest_raw ) {
			$zip->close();
			return array( 'ok' => false, 'error' => 'IMP-212' );
		}

		$manifest = json_decode( (string) $manifest_raw, true );
		if ( ! is_array( $manifest ) ) {
			$zip->close();
			return array( 'ok' => false, 'error' => 'IMP-212' );
		}

		$algo        = isset( $manifest['checksum_algo'] ) ? (string) $manifest['checksum_algo'] : 'sha256';
		$checks      = isset( $manifest['checksums'] ) && is_array( $manifest['checksums'] ) ? $manifest['checksums'] : array();
		$checks_ok   = true;

		foreach ( $checks as $part => $expected ) {
			$stream = $zip->getStream( (string) $part );
			if ( ! is_resource( $stream ) ) {
				$checks_ok = false;
				continue;
			}
			$ctx = hash_init( $algo );
			while ( ! feof( $stream ) ) {
				$data = fread( $stream, 1024 * 1024 );
				if ( false === $data ) {
					break;
				}
				hash_update( $ctx, $data );
			}
			fclose( $stream );
			if ( hash_final( $ctx ) !== (string) $expected ) {
				$checks_ok = false;
			}
		}

		$files = (int) $zip->numFiles;
		$zip->close();

		return array(
			'ok'            => $checks_ok,
			'manifest'      => $manifest,
			'files'         => $files,
			'checksums_ok'  => $checks_ok,
		);
	}

	/**
	 * Après une migration par copie : compare destination au fichier de
	 * hashs généré pendant la copie (échantillon ou intégralité).
	 *
	 * @param string $hashes_file Fichier "rel hash" lignes.
	 * @param string $dest_root   Racine destination.
	 * @param int    $sample      Vérifier 1 fichier sur N (0 = tout).
	 * @return array{checked:int,ok:int,mismatched:int,missing:int}
	 */
	public static function verify_copy_hashes( $hashes_file, $dest_root, $sample = 1 ) {
		$dest_root = imp_normalize_path( (string) $dest_root );
		$checked   = 0;
		$ok        = 0;
		$mismatched = 0;
		$missing    = 0;
		$i          = 0;

		$handle = @fopen( (string) $hashes_file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_resource( $handle ) ) {
			return compact( 'checked', 'ok', 'mismatched', 'missing' );
		}

		while ( false !== ( $line = fgets( $handle ) ) ) {
			$i++;
			if ( $sample > 1 && ( $i % $sample ) !== 0 ) {
				continue;
			}
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$pos = strrpos( $line, ' ' );
			if ( false === $pos ) {
				continue;
			}
			$rel  = substr( $line, 0, $pos );
			$hash = substr( $line, $pos + 1 );

			$dst = $dest_root . '/' . $rel;
			if ( ! is_file( $dst ) ) {
				$missing++;
				$checked++;
				continue;
			}
			$actual = hash_file( 'sha256', $dst );
			$checked++;
			if ( $actual === $hash ) {
				$ok++;
			} else {
				$mismatched++;
			}
		}
		fclose( $handle );

		return compact( 'checked', 'ok', 'mismatched', 'missing' );
	}
}
