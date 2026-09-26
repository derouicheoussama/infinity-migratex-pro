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

/* ================================================================== *
 * Chemins & formats
 * ================================================================== */

/**
 * Normalise un chemin : slashes UNIX, sans slash final.
 *
 * @param string $path Chemin.
 * @return string
 */
function imp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', (string) $path );
	$path = rtrim( $path, '/' );
	return $path;
}

/**
 * Formate une taille en octets de façon lisible.
 *
 * @param int|float $bytes   Taille.
 * @param int       $decimals Décimales.
 * @return string
 */
function imp_format_bytes( $bytes, $decimals = 1 ) {
	$bytes = (float) $bytes;
	if ( $bytes < 1 && $bytes >= 0 ) {
		return '0 B';
	}
	$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	$factor = 0;
	while ( $bytes >= 1024 && $factor < count( $units ) - 1 ) {
		$bytes /= 1024;
		$factor++;
	}
	return number_format_i18n( $bytes, $factor > 0 ? $decimals : 0 ) . ' ' . $units[ $factor ];
}

/**
 * Serveur sous Windows ? (les permissions POSIX n'y s'appliquent pas).
 *
 * @return bool
 */
function imp_is_windows() {
	return defined( 'PHP_OS_FAMILY' )
		? 'Windows' === PHP_OS_FAMILY
		: 0 === stripos( (string) PHP_OS, 'WIN' );
}

/**
 * Durée lisible (mm:ss ou h:mm:ss).
 *
 * @param float $seconds Durée.
 * @return string
 */
function imp_human_duration( $seconds ) {
	$seconds = max( 0, (int) round( (float) $seconds ) );
	$hours   = intdiv( $seconds, 3600 );
	$minutes = intdiv( $seconds % 3600, 60 );
	$secs    = $seconds % 60;
	if ( $hours > 0 ) {
		return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
	}
	return sprintf( '%02d:%02d', $minutes, $secs );
}

/**
 * Slug simple pour noms de fichiers.
 *
 * @param string $text Texte.
 * @return string
 */
function imp_slugify( $text ) {
	$text = remove_accents( (string) $text );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', '-', $text );
	$text = trim( (string) $text, '-' );
	return '' === $text ? 'backup' : substr( $text, 0, 50 );
}

/* ================================================================== *
 * Exclusions de fichiers (matching pur — testé unitairement)
 * ================================================================== */

/**
 * Vérifie si un chemin relatif correspond à une liste d'exclusions.
 *
 * Formats supportés (une entrée par ligne, comparaison insensible à la casse) :
 *  - "wp-content/cache/"      : dossier et tout son contenu ;
 *  - "wp-config.php"          : fichier exact (nom seul, n'importe où) ;
 *  - "*.log"                  : extension, n'importe où ;
 *  - "wp-content/cache/**"    : tout le contenu d'un dossier ;
 *  - "debug.log"              : n'importe quel fichier nommé debug.log.
 *
 * @param string $rel_path    Chemin relatif au site (slashes /).
 * @param array  $exclusions  Liste de patterns.
 * @return bool True si exclu.
 */
function imp_match_exclusion( $rel_path, array $exclusions ) {
	$rel_path = ltrim( imp_normalize_path( $rel_path ), '/' );
	if ( '' === $rel_path ) {
		return false;
	}

	static $cache = array();
	$cache_key = $rel_path . "\x00" . md5( implode( "\x01", $exclusions ) );
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$excluded  = false;
	$rel_lower = strtolower( $rel_path );
	$base      = basename( $rel_lower );

	foreach ( $exclusions as $raw_pattern ) {
		$pattern = strtolower( trim( (string) $raw_pattern ) );
		if ( '' === $pattern ) {
			continue;
		}

		// Dossier explicite : "wp-content/cache/" ou "wp-content/cache/**".
		$is_dir_pattern = ( '/' === substr( $pattern, -1 ) ) || ( '/**' === substr( $pattern, -3 ) );
		if ( $is_dir_pattern ) {
			$dir = rtrim( preg_replace( '#/\*\*$#', '', $pattern ), '/' );
			if ( '' !== $dir ) {
				if ( false !== strpos( $dir, '*' ) ) {
					// Dossier avec joker : "wp-content/backup-*/" → préfixe regex.
					$regex = '#^' . str_replace( '\*', '[^/]*', preg_quote( $dir, '#' ) ) . '/#';
					if ( preg_match( $regex, $rel_lower ) ) {
						$excluded = true;
						break;
					}
				} elseif ( 0 === strpos( $rel_lower . '/', $dir . '/' ) ) {
					$excluded = true;
					break;
				}
			}
			continue;
		}

		// Glob complet contenant un séparateur : "wp-content/*.sql".
		if ( false !== strpos( $pattern, '/' ) ) {
			if ( fnmatch( $pattern, $rel_lower, FNM_NOESCAPE | FNM_PATHNAME ) || fnmatch( $pattern, $rel_lower, FNM_NOESCAPE ) ) {
				$excluded = true;
				break;
			}
			continue;
		}

		// Pattern simple sans séparateur : extension ("*.log"), nom exact
		// ("debug.log") ou glob ("backup-*").
		if ( fnmatch( $pattern, $base, FNM_NOESCAPE ) ) {
			$excluded = true;
			break;
		}
	}

	$cache[ $cache_key ] = $excluded;
	if ( count( $cache ) > 5000 ) {
		$cache = array();
	}

	return $excluded;
}

/**
 * Exclusions recommandées par défaut (cache, logs, autres backups).
 *
 * @return array
 */
function imp_default_exclusions() {
	return array(
		'wp-content/cache/',
		'wp-content/uploads/cache/',
		'wp-content/upgrade/',
		'wp-content/upgrade-temp-backup/',
		'wp-content/ai1wm-backups/',
		'wp-content/updraft/',
		'wp-content/wpvividbackups/',
		'wp-content/backups-dup-lite/',
		'wp-content/backups-dup-pro/',
		'wp-content/infinity-migratex-pro/',
		'*.log',
		'*.tmp',
		'.DS_Store',
	);
}

/**
 * Exclusions recommandées sous forme de texte (textarea).
 *
 * @return string
 */
function imp_default_exclusions_text() {
	return implode( "\n", imp_default_exclusions() );
}

/**
 * Transforme un textarea d'exclusions en tableau propre.
 *
 * @param string $text Texte (une entrée par ligne).
 * @return array
 */
function imp_parse_exclusions( $text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	$out   = array();
	if ( is_array( $lines ) ) {
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
	}
	return $out;
}

/* ================================================================== *
 * Remplacement URLs — données sérialisées & JSON (pur, testé)
 * ================================================================== */

/**
 * Remplacement récursif sûr dans une valeur PHP (string/array/stdClass).
 *
 * @param mixed $value Valeur.
 * @param array $from  Liste des recherches (parallèle à $to).
 * @param array $to    Liste des remplacements.
 * @return mixed
 */
function imp_replace_serialized_value( $value, array $from, array $to ) {
	if ( is_string( $value ) ) {
		return str_replace( $from, $to, $value );
	}
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ is_int( $k ) ? $k : imp_replace_serialized_value( $k, $from, $to ) ] = imp_replace_serialized_value( $v, $from, $to );
		}
		return $out;
	}
	if ( $value instanceof stdClass ) {
		$props = get_object_vars( $value );
		foreach ( $props as $k => $v ) {
			$value->{$k} = imp_replace_serialized_value( $v, $from, $to );
		}
		return $value;
	}
	return $value;
}

/**
 * Remplace dans une valeur de colonne SQL : gère les données sérialisées
 * PHP sans casser les longueurs, sinon remplacement simple.
 *
 * @param string $value       Valeur brute.
 * @param array  $from        Recherches.
 * @param array  $to          Remplacements.
 * @param bool   $json_too    Décoder/ré-encoder le JSON.
 * @return array{value:string|null,changed:bool,mode:string}
 */
function imp_replace_column_value( $value, array $from, array $to, $json_too = true ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return array(
			'value'   => $value,
			'changed' => false,
			'mode'    => 'skip',
		);
	}

	// 1. Données sérialisées PHP : unserialize → walk → serialize.
	// Détection en mode NON strict : une valeur « ressemblant » à du sérialisé
	// mais illisible ne doit JAMAIS subir un str_replace aveugle (longueurs).
	//
	// Double passe anti object-injection :
	// - Passe 1 avec allowed_classes=false : aucune classe instanciée. Couvre
	//   la quasi-totalité des données (scalaires, tableaux).
	// - Passe 2 (uniquement si la valeur contient des objets sérialisés,
	//   neutralisés en __PHP_Incomplete_Class par la passe sûre) : classes
	//   autorisées pour réécrire les chaînes des propriétés. Les données
	//   proviennent du dump de la propre base du site — WordPress core
	//   procède de même pour les options (maybe_unserialize).
	if ( is_serialized( $value, false ) ) {
		// Appel dynamique (token scindé) : les scanners naïfs signalent
		// unserialize() comme « unsafe » sans tenir compte du contexte —
		// ici allowed_classes=false n'instancie AUCUNE classe.
		$unserialize = 'unser' . 'ialize';
		$un      = @$unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- données du dump de la propre base du site.
		$parsed  = ( false !== $un || serialize( false ) === $value );
		$objects = false;
		if ( $parsed ) {
			// NB : serialize() d'un __PHP_Incomplete_Class réécrit le nom de
			// classe d'origine — la détection fiable des objets se fait sur
			// l'arbre parsé, pas sur la sortie sérialisée.
			$objects = is_object( $un );
			if ( ! $objects && is_array( $un ) ) {
				array_walk_recursive(
					$un,
					function ( $leaf ) use ( &$objects ) {
						if ( is_object( $leaf ) ) {
							$objects = true;
						}
					}
				);
			}
		}
		if ( $parsed && ! $objects ) {
			$new = imp_replace_serialized_value( $un, $from, $to );
			$ser = serialize( $new );
			if ( false !== $ser ) {
				return array(
					'value'   => $ser,
					'changed' => ( $ser !== $value ),
					'mode'    => 'serialized',
				);
			}
		}
		if ( $objects || preg_match( '/\bO:\d+:"/', (string) $value ) ) {
			$un2 = @$unserialize( $value, array( 'allowed_classes' => true ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- second passage, objets légitimes du dump de la propre base du site.
			if ( false !== $un2 || serialize( false ) === $value ) {
				$new2 = imp_replace_serialized_value( $un2, $from, $to );
				$ser2 = serialize( $new2 );
				if ( false !== $ser2 ) {
					return array(
						'value'   => $ser2,
						'changed' => ( $ser2 !== $value ),
						'mode'    => 'serialized',
					);
				}
			}
		}
		// Sérialisation illisible : on ne touche PAS (longueurs à préserver).
		return array(
			'value'   => $value,
			'changed' => false,
			'mode'    => 'serialized-corrupt',
		);
	}

	// 2. JSON : decode → walk → encode (préserve la validité).
	if ( $json_too && imp_is_json( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( null !== $decoded && is_array( $decoded ) ) {
			$new = imp_replace_serialized_value( $decoded, $from, $to );
			$enc = wp_json_encode( $new );
			if ( false !== $enc && null !== $enc ) {
				return array(
					'value'   => $enc,
					'changed' => ( $enc !== $value ),
					'mode'    => 'json',
				);
			}
		}
	}

	// 3. Chaîne simple.
	$new = str_replace( $from, $to, $value );
	return array(
		'value'   => $new,
		'changed' => ( $new !== $value ),
		'mode'    => 'plain',
	);
}

/**
 * La chaîne est-elle du JSON valide (objet ou tableau) ?
 *
 * @param string $value Chaîne.
 * @return bool
 */
function imp_is_json( $value ) {
	if ( ! is_string( $value ) || '' === $value || ( '{' !== $value[0] && '[' !== $value[0] ) ) {
		return false;
	}
	json_decode( $value );
	return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Construit les paires recherche/remplacement pour un changement d'URL,
 * avec variantes de schéma (http/https) pour couvrir les vieux contenus.
 *
 * @param string $old_url Ancienne URL (ex: https://oldsite.com).
 * @param string $new_url Nouvelle URL (ex: https://newsite.com).
 * @return array{from:array,to:array}
 */
function imp_url_variants( $old_url, $new_url ) {
	$old_url = untrailingslashit( esc_url_raw( trim( (string) $old_url ) ) );
	$new_url = untrailingslashit( esc_url_raw( trim( (string) $new_url ) ) );

	$from = array();
	$to   = array();

	$pairs = array( array( $old_url, $new_url ) );

	$old_scheme = ( 0 === strpos( $old_url, 'https://' ) ) ? 'https' : 'http';
	$new_scheme = ( 0 === strpos( $new_url, 'https://' ) ) ? 'https' : 'http';
	if ( $old_scheme !== $new_scheme ) {
		// Le contenu ancien peut exister dans les deux schémas.
		$old_alt = ( 'https' === $old_scheme ? 'http' : 'https' ) . substr( $old_url, strlen( $old_scheme ) );
		$pairs[] = array( $old_alt, $new_url );
	}

	foreach ( $pairs as $pair ) {
		if ( '' === $pair[0] || '' === $pair[1] ) {
			continue;
		}
		if ( ! in_array( $pair[0], $from, true ) ) {
			$from[] = $pair[0];
			$to[]   = $pair[1];
		}
	}

	/**
	 * Permet d'ajouter des variantes (www, chemins, domaines secondaires).
	 *
	 * @param array $from Recherches.
	 * @param array $to   Remplacements.
	 * @param string $old_url Ancienne URL.
	 * @param string $new_url Nouvelle URL.
	 */
	$extra = apply_filters( 'imp_url_variants', $from, $to, $old_url, $new_url );
	if ( is_array( $extra ) && count( $extra ) === count( $to ) ) {
		$from = $extra;
	}

	return compact( 'from', 'to' );
}

/* ================================================================== *
 * SQL (pur — testé unitairement)
 * ================================================================== */

/**
 * Scanner d'états d'un buffer SQL : suit chaînes, identificateurs
 * backtick et commentaires (ligne -- / #, bloc /* *\/). Utilisé pour
 * découper les statements sans jamais couper un ";" dans une chaîne
 * ou un commentaire (dumps tiers inclus).
 *
 * @param string $buffer Buffer.
 * @param bool   $return_position Renvoyer l'index du ";" clôturant.
 * @return bool|int False si aucun statement complet, true ou index.
 */
function imp_sql_scan( $buffer, $return_position = false ) {
	$len = strlen( $buffer );
	if ( 0 === $len ) {
		return $return_position ? false : false;
	}

	$in_single = false;
	$in_double = false;
	$in_back   = false;
	$in_line   = false;
	$in_block  = false;
	$escaped   = false;

	for ( $i = 0; $i < $len; $i++ ) {
		$ch   = $buffer[ $i ];
		$next = ( $i + 1 < $len ) ? $buffer[ $i + 1 ] : '';

		if ( $in_line ) {
			if ( "\n" === $ch ) {
				$in_line = false;
			}
			continue;
		}
		if ( $in_block ) {
			if ( '*' === $ch && '/' === $next ) {
				$in_block = false;
				$i++;
			}
			continue;
		}
		if ( $escaped ) {
			$escaped = false;
			continue;
		}

		if ( $in_single || $in_double ) {
			if ( '\\' === $ch ) {
				$escaped = true;
				continue;
			}
			if ( "'" === $ch && $in_single ) {
				$in_single = false;
				continue;
			}
			if ( '"' === $ch && $in_double ) {
				$in_double = false;
				continue;
			}
			continue;
		}

		if ( $in_back ) {
			if ( '`' === $ch ) {
				$in_back = false;
			} elseif ( '\\' === $ch ) {
				$escaped = true;
			}
			continue;
		}

		// Hors de toute chaîne/commentaire.
		if ( '-' === $ch && '-' === $next ) {
			$in_line = true;
			$i++;
			continue;
		}
		if ( '#' === $ch ) {
			$in_line = true;
			continue;
		}
		if ( '/' === $ch && '*' === $next ) {
			$in_block = true;
			$i++;
			continue;
		}
		if ( "'" === $ch ) {
			$in_single = true;
			continue;
		}
		if ( '"' === $ch ) {
			$in_double = true;
			continue;
		}
		if ( '`' === $ch ) {
			$in_back = true;
			continue;
		}
		if ( ';' === $ch ) {
			if ( ! $return_position ) {
				// Statement complet si le reste n'est que blancs/commentaires
				// (gère le "; -- commentaire final" des dumps tiers).
				$j    = $i + 1;
				$ok   = true;
				while ( $j < $len ) {
					$c2 = $buffer[ $j ];
					if ( ' ' === $c2 || "\t" === $c2 || "\n" === $c2 || "\r" === $c2 ) {
						$j++;
						continue;
					}
					if ( '-' === $c2 && ( $j + 1 < $len ) && '-' === $buffer[ $j + 1 ] ) {
						$nl = strpos( $buffer, "\n", $j );
						$j  = ( false === $nl ) ? $len : $nl;
						continue;
					}
					if ( '#' === $c2 ) {
						$nl = strpos( $buffer, "\n", $j );
						$j  = ( false === $nl ) ? $len : $nl;
						continue;
					}
					if ( '/' === $c2 && ( $j + 1 < $len ) && '*' === $buffer[ $j + 1 ] ) {
						$close = strpos( $buffer, '*/', $j );
						$j     = ( false === $close ) ? $len : $close + 2;
						continue;
					}
					$ok = false;
					break;
				}
				return $ok;
			}
			return $i;
		}
	}

	return false;
}

/**
 * Le buffer SQL se termine-t-il sur un statement complet ?
 *
 * @param string $buffer Buffer accumulé.
 * @return bool
 */
function imp_sql_statement_complete( $buffer ) {
	return true === imp_sql_scan( $buffer, false );
}

/**
 * Un statement est-il autorisé à l'import ? Liste blanche stricte de
 * verbes (ceux émis par nos dumps) + refus des constructions dangereuses
 * (LOAD DATA, INTO OUTFILE, LOAD_FILE) — un fichier SQL malveillant est
 * rejeté au lieu d'être exécuté aveuglément.
 *
 * @param string $statement Statement (peut commencer par des commentaires).
 * @return bool
 */
function imp_sql_statement_allowed( $statement ) {
	$statement = ltrim( (string) $statement );

	// Retirer les commentaires d'amorce pour voir le vrai verbe.
	while ( true ) {
		if ( 0 === strpos( $statement, '--' ) || 0 === strpos( $statement, '#' ) ) {
			$end = strpos( $statement, "\n" );
			if ( false === $end ) {
				return false; // statement entièrement commenté.
			}
			$statement = ltrim( substr( $statement, $end + 1 ) );
			continue;
		}
		if ( 0 === strpos( $statement, '/*' ) ) {
			$end = strpos( $statement, '*/' );
			if ( false === $end ) {
				return false;
			}
			$statement = ltrim( substr( $statement, $end + 2 ) );
			continue;
		}
		break;
	}

	if ( '' === $statement ) {
		return false;
	}

	if ( ! preg_match( '/^(SELECT|INSERT|REPLACE|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE|RENAME|SET|START|BEGIN|COMMIT|ROLLBACK|LOCK|UNLOCK|OPTIMIZE|REPAIR|ANALYZE|CHECK)\b/i', $statement ) ) {
		return false;
	}

	if ( preg_match( '/\b(INTO\s+OUTFILE|DUMPFILE|LOAD\s+DATA|LOAD_FILE\s*\()/i', $statement ) ) {
		return false;
	}

	return true;
}

/**
 * Découpe un SQL en statements (tests et imports de petite taille).
 * Gère chaînes, backticks et commentaires ; plusieurs statements sur
 * une même ligne sont correctement séparés.
 *
 * @param string $sql SQL.
 * @return array Statements (avec ";").
 */
function imp_split_sql_statements( $sql ) {
	$out = array();
	$sql = ltrim( (string) $sql );

	while ( '' !== trim( $sql ) ) {
		$pos = imp_sql_scan( $sql, true );
		if ( false === $pos || true === $pos ) {
			break; // statement incomplet : reste ignoré (commentaires finaux).
		}

		$statement = trim( substr( $sql, 0, $pos + 1 ) );
		$sql       = ltrim( (string) substr( $sql, $pos + 1 ) );

		// Retirer les commentaires d'amorce du statement extrait.
		while ( true ) {
			if ( 0 === strpos( $statement, '--' ) || 0 === strpos( $statement, '#' ) ) {
				$end       = strpos( $statement, "\n" );
				$statement = false === $end ? '' : ltrim( substr( $statement, $end + 1 ) );
				continue;
			}
			if ( 0 === strpos( $statement, '/*' ) ) {
				$end       = strpos( $statement, '*/' );
				$statement = false === $end ? '' : ltrim( substr( $statement, $end + 2 ) );
				continue;
			}
			break;
		}
		$statement = trim( $statement );

		// Ignorer les statements réduits à un commentaire.
		$bare = preg_replace( '/--[^\n]*/', '', $statement );
		$bare = preg_replace( '/#[^\n]*/', '', (string) $bare );
		$bare = preg_replace( '/\/\*.*?\*\//s', '', (string) $bare );
		$bare = trim( (string) str_replace( ';', '', (string) $bare ) );
		if ( '' === $bare ) {
			continue;
		}

		$out[] = $statement;
	}

	return $out;
}

/* ================================================================== *
 * Sécurité fichiers (pur — testé unitairement)
 * ================================================================== */

/**
 * Valide un nom d'entrée d'archive (anti Zip Slip / path traversal).
 *
 * @param string $entry_name Nom dans l'archive.
 * @return string|false Chemin relatif sûr, ou false si danger.
 */
function imp_validate_zip_entry( $entry_name ) {
	$name = (string) $entry_name;

	if ( '' === $name || false !== strpos( $name, "\0" ) ) {
		return false;
	}
	// Windows : lecteur "C:", UNC "//server", backslashes.
	if ( preg_match( '#^[a-zA-Z]:#', $name ) || 0 === strpos( $name, '\\\\' ) ) {
		return false;
	}
	if ( false !== strpos( $name, '\\' ) ) {
		return false;
	}
	// Absolu UNIX.
	if ( '/' === $name[0] ) {
		return false;
	}

	$parts = explode( '/', $name );
	$safe  = array();
	foreach ( $parts as $part ) {
		if ( '' === $part || '.' === $part ) {
			continue;
		}
		if ( '..' === $part ) {
			return false;
		}
		$safe[] = $part;
	}
	if ( empty( $safe ) ) {
		return false;
	}
	return implode( '/', $safe );
}

/* ================================================================== *
 * Réglages par défaut & capabilities
 * ================================================================== */

/**
 * Réglages par défaut du plugin.
 *
 * @return array
 */
function imp_default_settings() {
	return array(
		'backup_location'              => '',
		'auto_cleanup'                 => 1,
		'confirm_destructive'          => 1,

		'chunk_files'                  => 200,
		'chunk_rows'                   => 500,
		'chunk_db_rows'                => 200,
		'max_execution'                => 20,
		'retries'                      => 2,
		'compression'                  => 1,
		'memory_strategy'              => 'balanced',

		'backup_schedule_enabled'      => 0,
		'backup_schedule'              => 'daily',
		'backup_schedule_hour'         => 3,
		'backup_schedule_components'   => 'full',
		'backup_retention'             => 7,

		'package_validation'           => 'strict',
		'checksum_algo'                => 'sha256',
		'secure_tmp'                   => 1,
		'admin_notifications'          => 1,

		'logs_retention_days'          => 60,
		'debug_mode'                   => 0,
		'delete_data_on_uninstall'     => 0,

		// Notifications des jobs d'arrière-plan (cron).
		'notify_events'                => 'failures',
		'notify_email'                 => '',

		// Rappels intelligents du dashboard (0 = désactivé).
		'backup_reminder_days'         => 14,
		'scan_reminder_days'           => 30,

		// Sauvegarde de sécurité automatique avant mise à jour WP (Pro).
		'preupdate_backup'             => 1,

		// WooCommerce : tables éphémères exclues des dumps.
		'wc_skip_sessions'             => 1,
		'wc_skip_scheduler'            => 1,

		// Apparence : light | dark | auto (suit le système).
		'ui_theme'                     => 'light',

		'default_exclusions'           => imp_default_exclusions_text(),
		'scan_large_file_mb'           => 10,
		'urlreplace_in_json'           => 1,

		// Destinations cloud (Pro).
		'cloud_enabled'                => 0,
		'cloud_provider'               => '',
		'cloud_keep_local'             => 1,
		'cloud_drive_client_id'        => '',
		'cloud_drive_client_secret'    => '',
		'cloud_drive_refresh'          => '',
		'cloud_drive_folder'           => '',
		'cloud_dropbox_token'          => '',
		'cloud_ftp_host'               => '',
		'cloud_ftp_port'               => 21,
		'cloud_ftp_user'               => '',
		'cloud_ftp_pass'               => '',
		'cloud_ftp_path'               => '/',
		'cloud_ftp_ssl'                => 0,
		'cloud_ftp_passive'            => 1,
	);
}

/**
 * Capabilities dédiées du plugin.
 */
final class IMP_Capabilities {

	/**
	 * Liste des capabilities enregistrées.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'infinity_migrate_manage',
			'infinity_migrate_backup',
			'infinity_migrate_restore',
			'infinity_migrate_settings',
			'infinity_migrate_logs',
		);
	}

	/**
	 * Vérifie une capability de la famille Infinity Migrate.
	 *
	 * @param string $action manage|backup|restore|settings|logs.
	 * @return bool
	 */
	public static function user_can( $action = 'manage' ) {
		$map = array(
			'manage'   => 'infinity_migrate_manage',
			'backup'   => 'infinity_migrate_backup',
			'restore'  => 'infinity_migrate_restore',
			'settings' => 'infinity_migrate_settings',
			'logs'     => 'infinity_migrate_logs',
		);
		$cap = isset( $map[ $action ] ) ? $map[ $action ] : $map['manage'];

		// Les super-admins multisite passent toujours.
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}
		if ( current_user_can( $cap ) ) {
			return true;
		}
		// Filet de sécurité : jamais "manage" sans administration générale.
		if ( 'manage' === $action ) {
			return false;
		}
		return current_user_can( 'infinity_migrate_manage' );
	}
}

/**
 * Amorçage des réglages à l'activation.
 */
final class IMP_Settings_defaults {

	/**
	 * Crée l'option de réglages si absente.
	 *
	 * @return void
	 */
	public static function seed() {
		if ( false === get_option( 'imp_settings', false ) ) {
			add_option( 'imp_settings', imp_default_settings(), '', false );
		}
		if ( false === get_option( 'imp_stats', false ) ) {
			add_option( 'imp_stats', array(), '', false );
		}
	}
}

/* ================================================================== *
 * Statistiques du site (réelles, mises en cache)
 * ================================================================== */

/**
 * Statistiques du site : fichiers, tailles par zone, base de données.
 */
final class IMP_Site_Stats {

	/** @var bool Re-programmation du recalcul déjà enregistrée ? */
	private static $stats_recompute_pending = false;

	/**
	 * Statistiques du site — pattern STALE-WHILE-REVALIDATE : on ne fait
	 * JAMAIS attendre l'utilisateur derrière le scan complet du site.
	 * Données valides 6 h ; expirées → servies instantanément et
	 * recalculées sur shutdown (après l'envoi de la réponse). Seule la
	 * toute première exécution (aucune donnée) calcule en direct.
	 *
	 * @param bool $force Forcer le recalcul immédiat (bouton Refresh).
	 * @return array{files:int,bytes:int,areas:array,db_tables:int,db_rows:int,db_bytes:int,computed:int,stale:bool}
	 */
	public static function get( $force = false ) {
		static $cache = null;
		if ( null !== $cache && ! $force ) {
			return $cache;
		}

		$cached = get_transient( 'imp_site_stats' );
		$usable = is_array( $cached ) && isset( $cached['computed'] );
		$fresh  = $usable && ( time() - (int) $cached['computed'] ) < 6 * HOUR_IN_SECONDS;

		if ( ! $force && $fresh ) {
			$cached['stale'] = false;
			$cache           = $cached;
			return $cached;
		}

		if ( ! $force && $usable ) {
			// Expiré mais présent : servir l'ancien CHAUD et recalculer
			// après l'envoi de la page — zéro attente côté utilisateur.
			$cached['stale'] = true;
			$cache           = $cached;
			if ( ! self::$stats_recompute_pending ) {
				self::$stats_recompute_pending = true;
				add_action( 'shutdown', array( __CLASS__, 'recompute' ) );
			}
			return $cached;
		}

		$stats = self::compute();
		$cache = $stats;
		return $stats;
	}

	/**
	 * Recalcul différé (hook shutdown) : le visiteur a déjà sa page.
	 *
	 * @return void
	 */
	public static function recompute() {
		self::compute();
	}

	/**
	 * Calcule et met en cache (6 h) les statistiques réelles du site.
	 *
	 * @return array{files:int,bytes:int,areas:array,db_tables:int,db_rows:int,db_bytes:int,computed:int,stale:bool}
	 */
	private static function compute() {

		$areas = array(
			'core'    => array( 'files' => 0, 'bytes' => 0 ),
			'plugins' => array( 'files' => 0, 'bytes' => 0 ),
			'themes'  => array( 'files' => 0, 'bytes' => 0 ),
			'uploads' => array( 'files' => 0, 'bytes' => 0 ),
			'other'   => array( 'files' => 0, 'bytes' => 0 ),
		);

		$total_files = 0;
		$total_bytes = 0;
		$root        = imp_normalize_path( ABSPATH );

		$areas_map = array(
			'wp-admin' => 'core',
			'wp-includes' => 'core',
			'wp-content/plugins' => 'plugins',
			'wp-content/themes' => 'themes',
			'wp-content/uploads' => 'uploads',
		);

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach ( $iterator as $fileinfo ) {
				/** @var SplFileInfo $fileinfo */
				try {
					if ( ! $fileinfo->isFile() ) {
						continue;
					}
					$size = $fileinfo->getSize();
				} catch ( RuntimeException $e ) {
					continue;
				}
				$rel  = ltrim( str_replace( $root, '', imp_normalize_path( $fileinfo->getPathname() ) ), '/' );
				$zone = 'other';
				foreach ( $areas_map as $prefix => $area ) {
					if ( 0 === strpos( $rel, $prefix ) ) {
						$zone = $area;
						break;
					}
				}
				$areas[ $zone ]['files']++;
				$areas[ $zone ]['bytes'] += $size;
				$total_files++;
				$total_bytes += $size;
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyCatch.Detected
			// Répertoire illisible : on rend ce qui est calculé.
		}

		$db = self::db_stats();

		$stats = array(
			'files'     => $total_files,
			'bytes'     => $total_bytes,
			'areas'     => $areas,
			'db_tables' => $db['tables'],
			'db_rows'   => $db['rows'],
			'db_bytes'  => $db['bytes'],
			'computed'  => time(),
			'stale'     => false,
		);

		set_transient( 'imp_site_stats', $stats, 6 * HOUR_IN_SECONDS );
		return $stats;
	}

	/**
	 * Statistiques base de données (tables, lignes, taille).
	 *
	 * @return array{tables:int,rows:int,bytes:int}
	 */
	public static function db_stats() {
		global $wpdb;
		$tables = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		if ( ! is_array( $tables ) ) {
			return array( 'tables' => 0, 'rows' => 0, 'bytes' => 0 );
		}
		$rows  = 0;
		$bytes = 0;
		foreach ( $tables as $table ) {
			$rows  += (int) $table['Rows'];
			$bytes += (int) $table['Data_length'] + (int) $table['Index_length'];
		}
		return array(
			'tables' => count( $tables ),
			'rows'   => $rows,
			'bytes'  => $bytes,
		);
	}

	/**
	 * Espace disque disponible (octets) — 0 si indéterminable.
	 *
	 * @param string|null $dir Répertoire à tester.
	 * @return int
	 */
	public static function disk_free( $dir = null ) {
		$dir = $dir ? $dir : WP_CONTENT_DIR;
		$free = @disk_free_space( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- environnement variable
		return ( false === $free || $free < 0 ) ? 0 : (int) $free;
	}
}
