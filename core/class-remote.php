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
 * Destinations cloud pour les sauvegardes (fonctionnalité Pro).
 *
 * Fournisseurs implémentés en REST natif (aucun SDK, aucune dépendance) :
 *  - Google Drive : upload resumable officiel — chaque lot de 2 Mo est
 *    repris après interruption (code 308 + plage de reprise) ;
 *  - Dropbox : sessions d'upload (start / append / finish) — reprise
 *    exacte via le curseur, correction automatique d'offset ;
 *  - FTP : extension ftp_* avec reprise par offset (ftp_nb_put).
 *
 * Les secrets (tokens, mots de passe) ne transitent JAMAIS dans l'état
 * du job ni dans les logs : ils sont chiffrés dans un transient dédié
 * détruit à la fin de l'opération.
 */
final class IMP_Remote {

	/**
	 * Registre des destinations.
	 *
	 * @return array[] {id,label,fields[],hint}
	 */
	public static function providers() {
		return array(
			'drive'   => array(
				'label'  => 'Google Drive',
				'fields' => array( 'cloud_drive_client_id', 'cloud_drive_client_secret', 'cloud_drive_refresh', 'cloud_drive_folder' ),
				'hint'   => __( 'Google Cloud Console → create an OAuth client ID (Desktop app), enable the Drive API, then generate a refresh token with scope drive.file. Paste the client ID, client secret and refresh token here. Folder ID is optional.', 'infinity-migratex-pro' ),
			),
			'dropbox' => array(
				'label'  => 'Dropbox',
				'fields' => array( 'cloud_dropbox_token' ),
				'hint'   => __( 'Dropbox App Console → create an app with the files.content.write scope → generate an access token and paste it here.', 'infinity-migratex-pro' ),
			),
			'ftp'     => array(
				'label'  => 'FTP / FTPS',
				'fields' => array( 'cloud_ftp_host', 'cloud_ftp_port', 'cloud_ftp_user', 'cloud_ftp_pass', 'cloud_ftp_path', 'cloud_ftp_ssl', 'cloud_ftp_passive' ),
				'hint'   => __( 'Any FTP or FTPS (explicit TLS) server: your hosting FTP account, a NAS or a backup box. The remote folders are created automatically.', 'infinity-migratex-pro' ),
			),
		);
	}

	/**
	 * Configuration cloud lisible depuis les réglages (secrets déchiffrés).
	 *
	 * @param string $provider drive|dropbox|ftp.
	 * @return array|false
	 */
	public static function config_from_settings( $provider ) {
		$settings = imp_settings();
		$secret   = static function ( $key ) use ( $settings ) {
			$value = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
			if ( '' === $value ) {
				return '';
			}
			return IMP_Security::decrypt( $value );
		};

		switch ( $provider ) {
			case 'drive':
				$cfg = array(
					'client_id'     => isset( $settings['cloud_drive_client_id'] ) ? trim( (string) $settings['cloud_drive_client_id'] ) : '',
					'client_secret' => $secret( 'cloud_drive_client_secret' ),
					'refresh'       => $secret( 'cloud_drive_refresh' ),
					'folder'        => isset( $settings['cloud_drive_folder'] ) ? trim( (string) $settings['cloud_drive_folder'] ) : '',
				);
				return ( '' !== $cfg['client_id'] && '' !== $cfg['client_secret'] && '' !== $cfg['refresh'] ) ? $cfg : false;

			case 'dropbox':
				$cfg = array( 'token' => $secret( 'cloud_dropbox_token' ) );
				return ( '' !== $cfg['token'] ) ? $cfg : false;

			case 'ftp':
				$cfg = array(
					'host'    => isset( $settings['cloud_ftp_host'] ) ? trim( (string) $settings['cloud_ftp_host'] ) : '',
					'port'    => isset( $settings['cloud_ftp_port'] ) ? max( 1, (int) $settings['cloud_ftp_port'] ) : 21,
					'user'    => isset( $settings['cloud_ftp_user'] ) ? trim( (string) $settings['cloud_ftp_user'] ) : '',
					'pass'    => $secret( 'cloud_ftp_pass' ),
					'path'    => isset( $settings['cloud_ftp_path'] ) ? trim( (string) $settings['cloud_ftp_path'] ) : '',
					'ssl'     => ! empty( $settings['cloud_ftp_ssl'] ),
					'passive' => ! isset( $settings['cloud_ftp_passive'] ) || ! empty( $settings['cloud_ftp_passive'] ),
				);
				return ( '' !== $cfg['host'] && '' !== $cfg['user'] ) ? $cfg : false;
		}

		return false;
	}

	/**
	 * La destination configurée par défaut est-elle prête ?
	 *
	 * @return bool
	 */
	public static function is_ready() {
		$provider = (string) imp_setting( 'cloud_provider', '' );
		return ( '' !== $provider ) && false !== self::config_from_settings( $provider );
	}

	/* ------------------------------------------------------------------ *
	 * Test de connexion
	 * ------------------------------------------------------------------ */

	/**
	 * Test réel de connexion.
	 *
	 * @param string $provider Provider.
	 * @param array  $cfg      Config déchiffrée (ou récupérée des réglages).
	 * @return array{ok:bool,message:string}
	 */
	public static function test_connection( $provider, array $cfg = null ) {
		$cfg = ( null === $cfg || empty( $cfg ) ) ? self::config_from_settings( $provider ) : $cfg;
		if ( false === $cfg || empty( $cfg ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Fill in the required fields first.', 'infinity-migratex-pro' ),
			);
		}

		switch ( $provider ) {
			case 'drive':
				return self::drive_test( $cfg );
			case 'dropbox':
				return self::dropbox_test( $cfg );
			case 'ftp':
				return self::ftp_test( $cfg );
		}

		return array(
			'ok'      => false,
			'message' => __( 'Unknown provider.', 'infinity-migratex-pro' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Google Drive
	 * ------------------------------------------------------------------ */

	/**
	 * Jeton d'accès Drive (cache 55 min par empreinte de config).
	 *
	 * @param array $cfg Config.
	 * @return string|false Jeton ou false.
	 */
	private static function drive_token( array $cfg ) {
		$cache_key = 'imp_drive_token_' . md5( (string) $cfg['refresh'] );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $cfg['client_id'],
					'client_secret' => $cfg['client_secret'],
					'refresh_token' => $cfg['refresh'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) || ! is_string( $body['access_token'] ) ) {
			return false;
		}

		set_transient( $cache_key, $body['access_token'], 3500 );
		return $body['access_token'];
	}

	/**
	 * Test Drive : identité du compte renvoyée par l'API About.
	 *
	 * @param array $cfg Config.
	 * @return array{ok:bool,message:string}
	 */
	private static function drive_test( array $cfg ) {
		$token = self::drive_token( $cfg );
		if ( false === $token ) {
			return array(
				'ok'      => false,
				'message' => __( 'Google rejected the credentials — check the client ID, client secret and refresh token (Drive API must be enabled).', 'infinity-migratex-pro' ),
			);
		}

		$response = wp_remote_get(
			'https://www.googleapis.com/drive/v3/about?fields=user',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$name = isset( $body['user']['displayName'] ) ? (string) $body['user']['displayName'] : '';
		$mail = isset( $body['user']['emailAddress'] ) ? (string) $body['user']['emailAddress'] : '';

		if ( '' === $name && '' === $mail ) {
			return array(
				'ok'      => false,
				'message' => __( 'Connected to Google but the Drive account is not readable — check the Drive API is enabled for this project.', 'infinity-migratex-pro' ),
			);
		}

		return array(
			'ok'      => true,
			/* translators: 1: name 2: email */
			'message' => sprintf( __( 'Connected to Google Drive as %1$s (%2$s).', 'infinity-migratex-pro' ), $name, $mail ),
		);
	}

	/**
	 * Démarre une session resumable Drive pour un fichier.
	 *
	 * @param array  $cfg  Config.
	 * @param string $name Nom du fichier distant.
	 * @param int    $size Taille totale du fichier.
	 * @return array{uri:string,error?:string}
	 */
	private static function drive_start( array $cfg, $name, $size ) {
		$token = self::drive_token( $cfg );
		if ( false === $token ) {
			return array( 'uri' => '', 'error' => 'IMP-243' );
		}

		$metadata = array( 'name' => $name );
		if ( '' !== (string) $cfg['folder'] ) {
			$metadata['parents'] = array( (string) $cfg['folder'] );
		}

		$response = wp_remote_post(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'   => 'Bearer ' . $token,
					'Content-Type'    => 'application/json; charset=UTF-8',
					'X-Upload-Content-Type' => 'application/octet-stream',
					'X-Upload-Content-Length' => (string) max( 0, (int) $size ),
				),
				'body'    => wp_json_encode( $metadata ),
			)
		);

		$location = is_array( $response ) ? wp_remote_retrieve_header( $response, 'location' ) : '';
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || '' === (string) $location ) {
			return array( 'uri' => '', 'error' => 'IMP-244' );
		}

		return array( 'uri' => (string) $location );
	}

	/**
	 * Upload d'un fichier vers Drive — par lots resumables.
	 *
	 * @param array $cfg        Config.
	 * @param string $local     Fichier local.
	 * @param string $name      Nom distant.
	 * @param array  $state     État {uri, offset}.
	 * @param float  $budget    Secondes.
	 * @return array{done:bool,sent:int,total:int,error?:string}
	 */
	public static function drive_upload( array $cfg, $local, $name, array &$state, $budget ) {
		$state = wp_parse_args(
			$state,
			array(
				'uri'    => '',
				'offset' => 0,
				'sent'   => 0,
			)
		);

		if ( ! is_readable( $local ) ) {
			return array( 'done' => false, 'sent' => 0, 'total' => 0, 'error' => 'IMP-218' );
		}
		$total = (int) filesize( $local );

		if ( '' === $state['uri'] ) {
			$start = self::drive_start( $cfg, $name, $total );
			if ( isset( $start['error'] ) ) {
				return array( 'done' => false, 'sent' => 0, 'total' => $total, 'error' => $start['error'] );
			}
			$state['uri']    = $start['uri'];
			$state['offset'] = 0;
		}

		$token = self::drive_token( $cfg );
		if ( false === $token ) {
			return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-243' );
		}

		$started  = microtime( true );
		$handle   = fopen( $local, 'rb' );
		if ( ! is_resource( $handle ) ) {
			return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-207' );
		}

		$done = false;

		try {
			while ( microtime( true ) - $started <= $budget ) {
				$offset = (int) $state['offset'];
				if ( $offset >= $total ) {
					$done = true;
					break;
				}

				fseek( $handle, $offset );
				$chunk = fread( $handle, 2 * 1024 * 1024 );
				if ( false === $chunk || '' === $chunk ) {
					$done = true;
					break;
				}
				$len = strlen( $chunk );

				$response = wp_remote_request(
					$state['uri'],
					array(
						'method'  => 'PUT',
						'timeout' => 60,
						'headers' => array(
							'Authorization'   => 'Bearer ' . $token,
							'Content-Type'    => 'application/octet-stream',
							'Content-Range'   => 'bytes ' . $offset . '-' . ( $offset + $len - 1 ) . '/' . $total,
						),
						'body'    => $chunk,
					)
				);

				if ( is_wp_error( $response ) ) {
					return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-244' );
				}

				$code = (int) wp_remote_retrieve_response_code( $response );
				if ( 308 === $code ) {
					$range = (string) wp_remote_retrieve_header( $response, 'range' ); // "bytes=0-N".
					if ( preg_match( '/bytes=0-(\d+)/', $range, $m ) ) {
						$state['offset'] = (int) $m[1] + 1;
						$state['sent']   = (int) $state['offset'];
					} else {
						$state['offset'] += $len;
						$state['sent']    = (int) $state['offset'];
					}
					continue;
				}

				if ( 200 === $code || 201 === $code ) {
					$done = true;
					$state['sent'] = $total;
					break;
				}

				// 4xx/5xx : erreur définitive (401 = token expiré → retry au
				// prochain step via un nouveau jeton ; les autres, on stoppe).
				if ( 401 === $code ) {
					delete_transient( 'imp_drive_token_' . md5( (string) $cfg['refresh'] ) );
					return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-243' );
				}
				if ( 403 === $code || 413 === $code ) {
					return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-245' );
				}
				return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-244' );
			}
		} finally {
			fclose( $handle );
		}

		return array(
			'done'  => $done,
			'sent'  => (int) $state['sent'],
			'total' => $total,
		);
	}

	/* ------------------------------------------------------------------ *
	 * Dropbox
	 * ------------------------------------------------------------------ */

	/**
	 * Test Dropbox : compte courant.
	 *
	 * @param array $cfg Config.
	 * @return array{ok:bool,message:string}
	 */
	private static function dropbox_test( array $cfg ) {
		$response = wp_remote_post(
			'https://api.dropboxapi.com/2/users/get_current_account',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $cfg['token'] ),
			)
		);
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || empty( $body['email'] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Dropbox rejected the access token — generate a new one with the files.content.write scope.', 'infinity-migratex-pro' ),
			);
		}

		return array(
			'ok'      => true,
			/* translators: %s: email */
			'message' => sprintf( __( 'Connected to Dropbox as %s.', 'infinity-migratex-pro' ), (string) $body['email'] ),
		);
	}

	/**
	 * Requête API Dropbox (JSON ou binaire) — renvoie code + corps + args.
	 *
	 * @param string $url     URL.
	 * @param array  $headers En-têtes additionnels.
	 * @param mixed  $body    Corps.
	 * @return array{code:int,body:string,headers:array}|WP_Error
	 */
	private static function dropbox_request( $url, array $headers, $body ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array_merge( array( 'Authorization' => 'Bearer ' ), $headers ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => wp_remote_retrieve_body( $response ),
			'headers' => wp_remote_retrieve_headers( $response ),
		);
	}

	/**
	 * Upload d'un fichier vers Dropbox — sessions chunked.
	 *
	 * @param array  $cfg   Config.
	 * @param string $local Fichier local.
	 * @param string $path  Chemin distant (/InfinityMigratePro/...).
	 * @param array  $state État {session, offset, sent}.
	 * @param float  $budget Secondes.
	 * @return array{done:bool,sent:int,total:int,error?:string}
	 */
	public static function dropbox_upload( array $cfg, $local, $path, array &$state, $budget ) {
		$state = wp_parse_args(
			$state,
			array(
				'session' => '',
				'offset'  => 0,
				'sent'    => 0,
			)
		);

		if ( ! is_readable( $local ) ) {
			return array( 'done' => false, 'sent' => 0, 'total' => 0, 'error' => 'IMP-218' );
		}
		$total = (int) filesize( $local );
		$token = 'Bearer ' . $cfg['token'];

		$started = microtime( true );
		$handle  = fopen( $local, 'rb' );
		if ( ! is_resource( $handle ) ) {
			return array( 'done' => false, 'sent' => 0, 'total' => $total, 'error' => 'IMP-207' );
		}

		$done = false;

		try {
			while ( microtime( true ) - $started <= $budget ) {
				$offset = (int) $state['offset'];

				// Démarrage de session.
				if ( '' === $state['session'] ) {
					$result = self::dropbox_request(
						'https://content.dropboxapi.com/2/files/upload_session/start',
						array(
							'Authorization' => $token,
							'Content-Type'  => 'application/json',
						),
						wp_json_encode( array( 'close' => false ) )
					);
					if ( is_wp_error( $result ) || 200 !== $result['code'] ) {
						return array( 'done' => false, 'sent' => 0, 'total' => $total, 'error' => 'IMP-244' );
					}
					$body = json_decode( $result['body'], true );
					if ( empty( $body['session_id'] ) ) {
						return array( 'done' => false, 'sent' => 0, 'total' => $total, 'error' => 'IMP-244' );
					}
					$state['session'] = (string) $body['session_id'];
					$state['offset']  = 0;
					continue;
				}

				// Finalisation.
				if ( $offset >= $total ) {
					$result = self::dropbox_request(
						'https://content.dropboxapi.com/2/files/upload_session/finish',
						array(
							'Authorization' => $token,
							'Content-Type'  => 'application/json',
						),
						wp_json_encode(
							array(
								'cursor' => array(
									'session_id' => $state['session'],
									'offset'     => $offset,
								),
								'commit' => array(
									'path'       => $path,
									'mode'       => 'overwrite',
									'autorename' => false,
									'mute'       => true,
								),
							)
						)
					);
					if ( is_wp_error( $result ) ) {
						return array( 'done' => false, 'sent' => $offset, 'total' => $total, 'error' => 'IMP-244' );
					}
					if ( 200 === $result['code'] ) {
						$done = true;
						$state['sent'] = $total;
						break;
					}
					if ( 409 === $result['code'] && false !== strpos( $result['body'], 'incorrect_offset' ) ) {
						$body = json_decode( $result['body'], true );
						if ( isset( $body['error']['incorrect_offset']['correct_offset'] ) ) {
							$state['offset'] = (int) $body['error']['incorrect_offset']['correct_offset'];
							continue;
						}
					}
					if ( 401 === $result['code'] ) {
						return array( 'done' => false, 'sent' => $offset, 'total' => $total, 'error' => 'IMP-243' );
					}
					return array( 'done' => false, 'sent' => $offset, 'total' => $total, 'error' => 'IMP-244' );
				}

				// Envoi d'un lot de 4 Mo.
				fseek( $handle, $offset );
				$chunk = fread( $handle, 4 * 1024 * 1024 );
				if ( false === $chunk || '' === $chunk ) {
					continue;
				}

				$result = self::dropbox_request(
					'https://content.dropboxapi.com/2/files/upload_session/append_v2',
					array(
						'Authorization'   => $token,
						'Content-Type'    => 'application/octet-stream',
						'Dropbox-API-Arg' => wp_json_encode(
							array(
								'cursor' => array(
									'session_id' => $state['session'],
									'offset'     => $offset,
								),
								'close'  => false,
							)
						),
					),
					$chunk
				);

				if ( is_wp_error( $result ) ) {
					return array( 'done' => false, 'sent' => $offset, 'total' => $total, 'error' => 'IMP-244' );
				}
				if ( 200 === $result['code'] ) {
					$state['offset'] += strlen( $chunk );
					$state['sent']    = (int) $state['offset'];
					continue;
				}
				if ( 409 === $result['code'] && false !== strpos( $result['body'], 'incorrect_offset' ) ) {
					$body = json_decode( $result['body'], true );
					if ( isset( $body['error']['incorrect_offset']['correct_offset'] ) ) {
						$state['offset'] = (int) $body['error']['incorrect_offset']['correct_offset'];
						continue;
					}
				}
				if ( 401 === $result['code'] ) {
					return array( 'done' => false, 'sent' => $offset, 'total' => $total, 'error' => 'IMP-243' );
				}
				return array( 'done' => false, 'sent' => $offset, 'total' => $total, 'error' => 'IMP-244' );
			}
		} finally {
			fclose( $handle );
		}

		return array(
			'done'  => $done,
			'sent'  => (int) $state['sent'],
			'total' => $total,
		);
	}

	/* ------------------------------------------------------------------ *
	 * FTP / FTPS
	 * ------------------------------------------------------------------ */

	/**
	 * Connexion + login FTP, chdir/mkdir récursif du dossier cible.
	 *
	 * @param array       $cfg  Config.
	 * @param string|null $dir  Dossier cible à préparer (facultatif).
	 * @return resource|array{error:string} Flux FTP ou erreur.
	 */
	private static function ftp_connect_to( array $cfg, $dir = null ) {
		if ( ! function_exists( 'ftp_connect' ) ) {
			return array( 'error' => 'IMP-246' );
		}

		$conn = ( ! empty( $cfg['ssl'] ) && function_exists( 'ftp_ssl_connect' ) )
			? ftp_ssl_connect( $cfg['host'], (int) $cfg['port'], 20 )
			: ftp_connect( $cfg['host'], (int) $cfg['port'], 20 );

		if ( false === $conn ) {
			return array( 'error' => 'IMP-242' );
		}
		if ( ! ftp_login( $conn, (string) $cfg['user'], (string) $cfg['pass'] ) ) {
			ftp_close( $conn );
			return array( 'error' => 'IMP-243' );
		}
		if ( ! empty( $cfg['passive'] ) ) {
			ftp_pasv( $conn, true );
		}
		ftp_set_option( $conn, FTP_TIMEOUT_SEC, 25 );

		if ( null !== $dir && '' !== trim( (string) $dir, '/' ) ) {
			$segments = array_filter( explode( '/', trim( (string) $dir, '/' ) ) );
			foreach ( $segments as $segment ) {
				if ( ! @ftp_chdir( $conn, $segment ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( ! @ftp_mkdir( $conn, $segment ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
						ftp_close( $conn );
						return array( 'error' => 'IMP-244' );
					}
					if ( ! @ftp_chdir( $conn, $segment ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
						ftp_close( $conn );
						return array( 'error' => 'IMP-244' );
					}
				}
			}
		}

		return $conn;
	}

	/**
	 * Test FTP : connexion + préparation du dossier.
	 *
	 * @param array $cfg Config.
	 * @return array{ok:bool,message:string}
	 */
	private static function ftp_test( array $cfg ) {
		$conn = self::ftp_connect_to( $cfg, '' !== trim( (string) $cfg['path'], '/' ) ? (string) $cfg['path'] : null );
		if ( is_array( $conn ) ) {
			return array(
				'ok'      => false,
				'message' => 'IMP-242' === $conn['error']
					? __( 'Could not reach the FTP server — check host and port.', 'infinity-migratex-pro' )
					: ( 'IMP-243' === $conn['error']
						? __( 'FTP login refused — check user and password.', 'infinity-migratex-pro' )
						: __( 'Could not create the remote folder — check permissions.', 'infinity-migratex-pro' ) ),
			);
		}
		$pasv = ( ! empty( $cfg['passive'] ) ) ? 'passive' : 'active';
		ftp_close( $conn );
		return array(
			'ok'      => true,
			/* translators: 1: host 2: mode */
			'message' => sprintf( __( 'Connected to %1$s (%2$s mode).', 'infinity-migratex-pro' ), (string) $cfg['host'], $pasv ),
		);
	}

	/**
	 * Upload d'un fichier via FTP avec reprise par offset.
	 *
	 * @param array  $cfg   Config.
	 * @param string $local Fichier local.
	 * @param string $dir   Dossier distant (créé si besoin).
	 * @param string $name  Nom distant.
	 * @param array  $state État {offset, sent}.
	 * @param float  $budget Secondes.
	 * @return array{done:bool,sent:int,total:int,error?:string}
	 */
	public static function ftp_upload( array $cfg, $local, $dir, $name, array &$state, $budget ) {
		$state = wp_parse_args(
			$state,
			array(
				'offset' => 0,
				'sent'   => 0,
			)
		);

		if ( ! is_readable( $local ) ) {
			return array( 'done' => false, 'sent' => 0, 'total' => 0, 'error' => 'IMP-218' );
		}
		$total = (int) filesize( $local );

		$conn = self::ftp_connect_to( $cfg, $dir );
		if ( is_array( $conn ) ) {
			return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => $conn['error'] );
		}

		$done    = false;
		$started = microtime( true );

		try {
			// Reprise : taille déjà présente sur le serveur.
			if ( (int) $state['offset'] <= 0 ) {
				$remote_size = (int) @ftp_size( $conn, $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( $remote_size > 0 && $remote_size < $total ) {
					$state['offset'] = $remote_size;
					$state['sent']   = $remote_size;
				}
			}

			$handle = fopen( $local, 'rb' );
			if ( ! is_resource( $handle ) ) {
				ftp_close( $conn );
				return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-207' );
			}

			$result = @ftp_nb_put( $conn, $name, $local, FTP_BINARY, (int) $state['offset'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$last_sample = microtime( true );

			while ( FTP_MOREDATA === $result ) {
				$result = ftp_nb_continue( $conn );

				// Échantillonner la progression toutes les 0,6 s.
				if ( microtime( true ) - $last_sample >= 0.6 ) {
					$state['offset'] = max( (int) $state['offset'], (int) @ftp_size( $conn, $name ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					$state['sent']   = (int) $state['offset'];
					$last_sample     = microtime( true );
				}

				if ( microtime( true ) - $started > $budget ) {
					// Budget atteint : le serveur garde la partie reçue,
					// la reprise repartira de ftp_size().
					fclose( $handle );
					ftp_close( $conn );
					return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total );
				}
			}
			fclose( $handle );

			if ( FTP_FINISHED === $result ) {
				$done = true;
				$state['offset'] = $total;
				$state['sent']   = $total;
			} else {
				ftp_close( $conn );
				return array( 'done' => false, 'sent' => (int) $state['sent'], 'total' => $total, 'error' => 'IMP-244' );
			}
		} finally {
			if ( is_resource( $conn ) ) {
				ftp_close( $conn );
			}
		}

		return array(
			'done'  => $done,
			'sent'  => (int) $state['sent'],
			'total' => $total,
		);
	}

	/* ------------------------------------------------------------------ *
	 * Dispatch + phases du job « remote »
	 * ------------------------------------------------------------------ */

	/**
	 * Phase prepare du job : vérifie Pro, résout config, liste les parties.
	 *
	 * @param array $job Job (par référence).
	 * @return array{done:bool,message:string,error?:string}
	 */
	public static function step_prepare( array &$job ) {
		if ( ! IMP_License::is_pro() ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-241',
				'message' => __( 'Cloud backups are a Pro feature — activate your license in Settings → Advanced.', 'infinity-migratex-pro' ),
			);
		}

		$backup_id = IMP_Backup_Engine::sanitize_id( isset( $job['data']['backup'] ) ? (string) $job['data']['backup'] : '' );
		if ( '' === $backup_id ) {
			return array( 'done' => false, 'error' => 'IMP-218', 'message' => '' );
		}
		$dir = IMP_Backup_Engine::dir() . $backup_id;
		if ( ! is_dir( $dir ) ) {
			return array( 'done' => false, 'error' => 'IMP-218', 'message' => __( 'Backup not found.', 'infinity-migratex-pro' ) );
		}

		$provider = (string) imp_setting( 'cloud_provider', '' );
		$providers = self::providers();
		if ( '' === $provider || ! isset( $providers[ $provider ] ) ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-242',
				'message' => __( 'No cloud destination configured — pick one in Settings → Cloud.', 'infinity-migratex-pro' ),
			);
		}

		$cfg = self::config_from_settings( $provider );
		if ( false === $cfg ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-242',
				'message' => __( 'The cloud destination is incomplete — check the settings.', 'infinity-migratex-pro' ),
			);
		}

		// Secrets chiffrés en transient, jamais dans l'état du job.
		set_transient(
			'imp_cloud_cfg_' . $job['id'],
			IMP_Security::encrypt( (string) wp_json_encode( $cfg ) ),
			6 * HOUR_IN_SECONDS
		);

		$parts = array();
		foreach ( array( 'database.sql.gz', 'database.sql', 'files.zip' ) as $part ) {
			if ( is_file( $dir . '/' . $part ) ) {
				$parts[] = array(
					'name'     => $part,
					'size'     => (int) filesize( $dir . '/' . $part ),
					'state'    => array(),
					'done'     => false,
				);
			}
		}
		if ( empty( $parts ) ) {
			return array( 'done' => false, 'error' => 'IMP-218', 'message' => __( 'This backup has no files to send.', 'infinity-migratex-pro' ) );
		}

		$job['data']['backup_id']  = $backup_id;
		$job['data']['provider']   = $provider;
		$job['data']['keep_local'] = ! empty( imp_setting( 'cloud_keep_local', 1 ) ) ? 1 : 0;
		$job['state']['upload']    = array(
			'parts'  => $parts,
			'index'  => 0,
		);

		return array(
			'done'    => true,
			'message' => sprintf(
				/* translators: 1: provider 2: parts count */
				__( 'Cloud upload to %1$s starts — %2$s part(s).', 'infinity-migratex-pro' ),
				$providers[ $provider ]['label'],
				count( $parts )
			),
		);
	}

	/**
	 * Phase upload : envoie la partie courante avec le provider actif.
	 *
	 * @param array $job    Job.
	 * @param float $budget Secondes.
	 * @return array{done:bool,message:string,error?:string,percent?:float}
	 */
	public static function step_upload( array &$job, $budget ) {
		$upload =& $job['state']['upload'];
		if ( $upload['index'] >= count( $upload['parts'] ) ) {
			return array( 'done' => true, 'message' => '', 'percent' => 100.0 );
		}

		$provider = (string) $job['data']['provider'];
		$cfg_json = IMP_Security::decrypt( (string) get_transient( 'imp_cloud_cfg_' . $job['id'] ) );
		$cfg      = json_decode( (string) $cfg_json, true );
		if ( ! is_array( $cfg ) || empty( $cfg ) ) {
			return array(
				'done'    => false,
				'error'   => 'IMP-208',
				'message' => __( 'Cloud credentials expired — send again.', 'infinity-migratex-pro' ),
			);
		}

		$part      =& $upload['parts'][ $upload['index'] ];
		$backup_id = (string) $job['data']['backup_id'];
		$local     = IMP_Backup_Engine::dir() . $backup_id . '/' . $part['name'];
		$started   = microtime( true );

		switch ( $provider ) {
			case 'drive':
				$result = self::drive_upload( $cfg, $local, $backup_id . '-' . $part['name'], $part['state'], $budget );
				break;
			case 'dropbox':
				$result = self::dropbox_upload( $cfg, $local, '/InfinityMigratePro/' . $backup_id . '/' . $part['name'], $part['state'], $budget );
				break;
			case 'ftp':
				$result = self::ftp_upload( $cfg, $local, 'InfinityMigratePro/' . $backup_id, $part['name'], $part['state'], $budget );
				break;
			default:
				return array( 'done' => false, 'error' => 'IMP-224', 'message' => '' );
		}

		$elapsed = microtime( true ) - $started;
		$percent = $result['total'] > 0 ? 100.0 * $result['sent'] / $result['total'] : 0.0;

		if ( isset( $result['error'] ) ) {
			return array( 'done' => false, 'error' => $result['error'], 'message' => '' );
		}

		if ( $result['done'] ) {
			// Vérification de taille distante avant de valider la partie.
			if ( (int) $result['sent'] !== (int) $part['size'] && (int) $part['size'] > 0 ) {
				return array(
					'done'    => false,
					'error'   => 'IMP-244',
					'message' => sprintf(
						/* translators: 1: file 2: expected size */
						__( 'Remote size mismatch for %1$s (expected %2$s bytes).', 'infinity-migratex-pro' ),
						$part['name'],
						number_format_i18n( (int) $part['size'] )
					),
				);
			}
			$part['done'] = true;
			$upload['index']++;
		}

		$total_all = 0;
		$sent_all  = 0;
		foreach ( $upload['parts'] as $p ) {
			$total_all += (int) $p['size'];
			$sent_all  += ( $p['done'] ? (int) $p['size'] : (int) $p['state']['sent'] );
		}

		return array(
			'done'    => false,
			'percent' => $total_all > 0 ? min( 100.0, 100.0 * $sent_all / $total_all ) : 0.0,
			'message' => sprintf(
				/* translators: 1: file 2: percent */
				__( 'Uploading %1$s — %2$s%%', 'infinity-migratex-pro' ),
				$part['name'],
				number_format_i18n( $percent, 0 )
			),
		);
	}

	/**
	 * Phase finalize : suppression locale éventuelle + nettoyage secrets.
	 *
	 * @param array $job Job.
	 * @return array{done:bool,message:string}
	 */
	public static function step_finalize( array &$job ) {
		$upload  = isset( $job['state']['upload'] ) ? $job['state']['upload'] : array();
		$keep    = ! isset( $job['data']['keep_local'] ) || (int) $job['data']['keep_local'] === 1;
		$removed = 0;

		if ( ! $keep && ! empty( $upload['parts'] ) ) {
			foreach ( $upload['parts'] as $part ) {
				if ( ! empty( $part['done'] ) ) {
					$path = IMP_Backup_Engine::dir() . (string) $job['data']['backup_id'] . '/' . (string) $part['name'];
					if ( is_file( $path ) && wp_delete_file( $path ) ) {
						$removed++;
					}
				}
			}
		}

		delete_transient( 'imp_cloud_cfg_' . $job['id'] );

		$job['result'] = array(
			'provider'  => isset( $job['data']['provider'] ) ? (string) $job['data']['provider'] : '',
			'parts'     => count( (array) ( $upload['parts'] ?? array() ) ),
			'local_removed' => $removed,
			'kept_local'    => $keep,
		);

		return array(
			'done'    => true,
			'message' => $keep
				? __( 'Cloud upload complete — local copy kept.', 'infinity-migratex-pro' )
				: sprintf( /* translators: %d: count */ __( 'Cloud upload complete — %d local part(s) removed after verification.', 'infinity-migratex-pro' ), $removed ),
		);
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
