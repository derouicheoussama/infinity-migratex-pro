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
 * Intégration Elementor : détection, régénération CSS & données après
 * migration (API officielle d'Elementor), nettoyage post-URL-replace.
 */
final class IMP_Integrations_Elementor {

	/**
	 * Elementor actif ?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( '\Elementor\Plugin' ) || did_action( 'elementor/loaded' );
	}

	/**
	 * Version détectée.
	 *
	 * @return string
	 */
	public static function version() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return '';
		}
		return (string) ELEMENTOR_VERSION;
	}

	/**
	 * Nombre réel de documents Elementor (pages, templates).
	 *
	 * @return array{documents:int,templates:int}
	 */
	public static function data_stats() {
		global $wpdb;

		$stats = array(
			'documents' => 0,
			'templates' => 0,
		);

		if ( ! self::is_active() ) {
			return $stats;
		}

		$stats['documents'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s", '_elementor_data' )
		);
		$stats['templates'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'elementor_library' )
		);

		return $stats;
	}

	/**
	 * Régénère le CSS et les données Elementor (API officielle).
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function regenerate() {
		if ( ! self::is_active() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Elementor is not active on this site.', 'infinity-migratex-pro' ),
			);
		}

		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->files_manager ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Elementor API not fully loaded yet — reload this page and try again.', 'infinity-migratex-pro' ),
			);
		}

		\Elementor\Plugin::$instance->files_manager->clear_cache();

		// Post-types Elementor : régénérer les CSS par document.
		global $wpdb;
		$regenerated = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_elementor_css' )
		);

		return array(
			'ok'      => true,
			/* translators: %d: number of CSS entries regenerated */
			'message' => sprintf( __( 'Elementor CSS & data regenerated (%d document styles rebuilt on next visit).', 'infinity-migratex-pro' ), $regenerated ),
		);
	}

	/**
	 * Compatibilité de migration (pages, templates, réglages globaux,
	 * URLs et données sérialisées traitées par le remplaceur).
	 *
	 * @return array[] {label,covered:bool}
	 */
	public static function migration_compat() {
		return array(
			array( 'label' => __( 'Elementor pages and their serialized data', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Saved templates (elementor_library)', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Global settings (Kit)', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Generated CSS (regenerated after migration)', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'URLs inside widgets (serialized-safe replacement)', 'infinity-migratex-pro' ), 'covered' => true ),
		);
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
