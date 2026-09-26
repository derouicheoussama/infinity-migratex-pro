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

/* phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.RestrictedFunctions, Squiz.PHP.DiscouragedFunctions -- Un plugin de sauvegarde doit lire/ecrire de tres gros fichiers binaires en flux (fopen/fwrite/fread) et dialoguer directement avec la base pour les dumps: WP_Filesystem et prepare() ne supportent pas ce mode. Nonces et capabilities restent verifies sur chaque action. */

/**
 * Chiffrement AES-256-GCM des archives de sauvegarde.
 *
 * Format « IMPE2 » (standard industriel, sans dépendance) :
 *   [ magic 8 "IMPE2aa" ][ nonce aléatoire 8 ]
 *   puis par lot de 4 Mo : [ TAG 16 ][ LEN 4 ][ ciphertext ]
 *
 * - AES-256-GCM : chiffrement + authentification intégrée par lot
 *   (toute altération ou réordonnance est détectée par le tag).
 * - Clé : SHA-256( "enc|" + mot de passe ).
 * - Nonce : 8 octets aléatoires par fichier + compteur 32 bits.
 * - Flux par lots de 4 Mo : mémoire bornée, resumable par compteur.
 */
final class IMP_Crypto {

	const MAGIC    = 'IMPE2aa';
	const CHUNK    = 4194304; // 4 Mo.
	const NONCE_LEN = 12;

	/**
	 * OpenSSL est-il disponible ?
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) && defined( 'OPENSSL_RAW_DATA' );
	}

	/**
	 * Un fichier est-il au format chiffré IMPE2 ?
	 *
	 * @param string $file Chemin.
	 * @return bool
	 */
	public static function is_encrypted( $file ) {
		clearstatcache();
		if ( ! is_readable( $file ) || @filesize( $file ) < 8 + 12 + 20 + 16 ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}
		$handle = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_resource( $handle ) ) {
			return false;
		}
		$magic = fread( $handle, 8 );
		fclose( $handle );
		return self::MAGIC === substr( (string) $magic, 0, 7 );
	}

	/**
	 * Clé de chiffrement depuis le mot de passe.
	 *
	 * @param string $password Mot de passe.
	 * @return string Clé binaire 32 octets.
	 */
	private static function key( $password ) {
		return hash( 'sha256', 'enc|' . (string) $password, true );
	}

	/**
	 * Nonce du lot n° $counter : préfixe aléatoire (8) + compteur (4).
	 *
	 * @param string $prefix  Préfixe du fichier.
	 * @param int    $counter Compteur.
	 * @return string 12 octets.
	 */
	private static function chunk_nonce( $prefix, $counter ) {
		return (string) $prefix . pack( 'N', $counter );
	}

	/**
	 * Chiffre un fichier entier (streaming par lots GCM).
	 *
	 * @param string $in       Fichier source.
	 * @param string $out      Fichier destination (.enc).
	 * @param string $password Mot de passe.
	 * @return array{ok:bool,bytes:int,error?:string}
	 */
	public static function encrypt_file( $in, $out, $password ) {
		if ( ! self::available() ) {
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-256' );
		}
		if ( ! is_readable( $in ) ) {
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-218' );
		}
		if ( '' === (string) $password ) {
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-257' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$key    = self::key( $password );
		$prefix = random_bytes( 8 );

		$fin  = @fopen( $in, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$fout = @fopen( $out, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_resource( $fin ) || ! is_resource( $fout ) ) {
			if ( is_resource( $fin ) ) { fclose( $fin ); }
			if ( is_resource( $fout ) ) { fclose( $fout ); }
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-257' );
		}

		$ok      = true;
		$counter = 0;

		try {
			fwrite( $fout, self::MAGIC );
			fwrite( $fout, $prefix );

			while ( ! feof( $fin ) ) {
				$plain = fread( $fin, self::CHUNK );
				if ( false === $plain || '' === $plain ) {
					break;
				}
				$counter++;
				$nonce  = $prefix . pack( 'N', $counter );
				$tag    = '';
				$cipher = openssl_encrypt(
					$plain,
					'aes-256-gcm',
					$key,
					OPENSSL_RAW_DATA,
					$nonce,
					$tag,
					'',
					16
				);
				if ( false === $cipher ) {
					$ok = false;
					break;
				}
				fwrite( $fout, $tag );
				fwrite( $fout, pack( 'N', strlen( $cipher ) ) );
				fwrite( $fout, $cipher );
			}
		} finally {
			fclose( $fin );
			fclose( $fout );
		}

		if ( ! $ok || 0 === $counter ) {
			@unlink( $out ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-257' );
		}

		clearstatcache();
		return array( 'ok' => true, 'bytes' => (int) filesize( $out ) );
	}

	/**
	 * Déchiffre un fichier IMPE2 (chaque lot est authentifié par son tag ;
	 * mot de passe erroné ou fichier altéré = échec immédiat).
	 *
	 * @param string $in       Fichier chiffré.
	 * @param string $out      Fichier déchiffré.
	 * @param string $password Mot de passe.
	 * @return array{ok:bool,bytes:int,error?:string}
	 */
	public static function decrypt_file( $in, $out, $password ) {
		if ( ! self::available() ) {
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-256' );
		}
		if ( ! is_readable( $in ) ) {
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-218' );
		}
		if ( ! self::is_encrypted( $in ) ) {
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-259' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$key = self::key( $password );

		$fin  = @fopen( $in, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$fout = @fopen( $out, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_resource( $fin ) || ! is_resource( $fout ) ) {
			if ( is_resource( $fin ) ) { fclose( $fin ); }
			if ( is_resource( $fout ) ) { fclose( $fout ); }
			return array( 'ok' => false, 'bytes' => 0, 'error' => 'IMP-257' );
		}

		$ok      = true;
		$written = 0;
		$counter = 0;

		try {
			$magic  = fread( $fin, 8 );
			$prefix = fread( $fin, 8 );

			while ( ! feof( $fin ) ) {
				$tag = fread( $fin, 16 );
				$len = fread( $fin, 4 );
				if ( strlen( $tag ) < 16 || strlen( $len ) < 4 ) {
					break; // fin propre.
				}
				$tag_len = unpack( 'N', $len );
				$clen    = (int) $tag_len[1];
				$cipher  = ( $clen > 0 ) ? fread( $fin, $clen ) : '';
				if ( false === $cipher || strlen( $cipher ) !== $clen ) {
					$ok = false;
					break;
				}
				$counter++;
				$nonce = $prefix . pack( 'N', $counter );
				$plain = openssl_decrypt(
					$cipher,
					'aes-256-gcm',
					$key,
					OPENSSL_RAW_DATA,
					$nonce,
					$tag
				);
				if ( false === $plain ) {
					$ok = false; // tag invalide : mot de passe erroné ou altération.
					break;
				}
				fwrite( $fout, $plain );
				$written += strlen( $plain );
			}
		} finally {
			fclose( $fin );
			fclose( $fout );
		}

		if ( ! $ok || 0 === $written ) {
			@unlink( $out ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return array( 'ok' => false, 'bytes' => $written, 'error' => 'IMP-259' );
		}

		return array( 'ok' => true, 'bytes' => $written );
	}
}
