<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity MigrateX Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * GitHub   : https://github.com/derouicheoussama
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — watermark source exécutable.
 *
 * WATERMARK SOURCE — sert deux preuves en runtime :
 *   IMP_Watermark::proof()  → identité + empreintes SHA-256 de tous les
 *                             fichiers signés (diagnostics, support).
 *   IMP_Watermark::verify() → VRAI si chaque fichier signé contient la
 *                             marque ∞ INFINITY CODER (détection de
 *                             redistribution dépouillée des signatures).
 *
 * La liste SIGNATURED est générée par tools/inject-watermarks.php et
 * tenue à jour à chaque ajout de fichier PHP.
 */

defined( 'ABSPATH' ) || exit;

final class IMP_Watermark {

	const MARK = '∞ INFINITY CODER';
	const AUTHOR = 'Derouiche Oussama';

	/**
	 * Fichiers de code signés (relatifs à IMP_DIR) — générés par l'outil.
	 *
	 * @return string[]
	 */
	public static function files() {
		return array(
			'admin/class-about.php',
			'admin/class-admin.php',
			'admin/class-backup.php',
			'admin/class-checkout.php',
			'admin/class-dashboard.php',
			'admin/class-database.php',
			'admin/class-import.php',
			'admin/class-integrations.php',
			'admin/class-logs.php',
			'admin/class-migration.php',
			'admin/class-packages.php',
			'admin/class-plugins-page.php',
			'admin/class-restore.php',
			'admin/class-scanner.php',
			'admin/class-security.php',
			'admin/class-settings.php',
			'admin/class-tools.php',
			'admin/class-urlreplace.php',
			'admin/class-woocommerce.php',
			'core/class-backup-engine.php',
			'core/class-crypto.php',
			'core/class-database.php',
			'core/class-files.php',
			'core/class-hardening.php',
			'core/class-integrity.php',
			'core/class-job.php',
			'core/class-migrator.php',
			'core/class-package.php',
			'core/class-remote.php',
			'core/class-scanner.php',
			'core/class-security.php',
			'core/class-sheets.php',
			'core/class-url-replacer.php',
			'includes/class-updater.php',
			'includes/class-upgrade-guard.php',
			'includes/compatibility.php',
			'includes/helpers.php',
			'includes/license.php',
			'includes/logger.php',
			'integrations/class-elementor.php',
			'integrations/class-woocommerce.php',
			'integrations/class-wordpress.php',
			'infinity-migratex-pro.php',
			'uninstall.php',
		);
	}

	/**
	 * Preuve d'identité : nombre de fichiers signés, empreintes,
	 * auteur, version, horodatage.
	 *
	 * @return array
	 */
	public static function proof() {
		$hashes = array();
		foreach ( self::files() as $rel ) {
			$path = IMP_DIR . $rel;
			if ( is_readable( $path ) ) {
				$hashes[ $rel ] = hash_file( 'sha256', $path );
			}
		}
		return array(
			'product' => 'Infinity MigrateX Pro',
			'author'  => self::AUTHOR,
			'studio'  => self::MARK,
			'version' => IMP_VERSION,
			'signed'  => count( $hashes ),
			'sha256'  => $hashes,
			'time'    => gmdate( 'c' ),
		);
	}

	/**
	 * Chaque fichier signé contient-il la marque ? (anti-dépouillement)
	 *
	 * @return array{ok:bool,checked:int,stripped:array}
	 */
	public static function verify() {
		$checked  = 0;
		$stripped = array();
		foreach ( self::files() as $rel ) {
			$path = IMP_DIR . $rel;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$head = (string) file_get_contents( $path, false, null, 0, 4096 );
			$checked++;
			if ( false === strpos( $head, self::MARK ) && false === strpos( $head, 'INFINITY CODER' ) ) {
				$stripped[] = $rel;
			}
		}
		return array(
			'ok'       => empty( $stripped ),
			'checked'  => $checked,
			'stripped' => $stripped,
		);
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
