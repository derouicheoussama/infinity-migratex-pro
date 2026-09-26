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
 * Intégration WordPress core : caches connus, réécriture d'URLs,
 * rapport système pour les diagnostics.
 */
final class IMP_Integrations_WordPress {

	/**
	 * Plugins de cache connus et actifs (détection réelle).
	 *
	 * @return array<string>
	 */
	public static function active_cache_plugins() {
		$known = array(
			'wp-super-cache/wp-cache.php'      => 'WP Super Cache',
			'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
			'wp-rocket/wp-rocket.php'          => 'WP Rocket',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
			'autoptimize/autoptimize.php'      => 'Autoptimize',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'sg-cachepress/sg-cachepress.php'  => 'SiteGround Optimizer',
			'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird',
			'comet-cache/comet-cache.php'      => 'Comet Cache',
			'cache-enabler/cache-enabler.php'  => 'Cache Enabler',
			'zen-cache/zen-cache.php'          => 'Zen Cache',
			'breeze/breeze.php'                => 'Breeze',
		);

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = array();
		foreach ( $known as $plugin => $label ) {
			if ( is_plugin_active( $plugin ) ) {
				$active[] = $label;
			}
		}

		// Optimisation native de certains hébergeurs.
		if ( defined( 'WP_CACHE' ) && WP_CACHE && empty( $active ) ) {
			$active[] = 'WP_CACHE (advanced-cache.php)';
		}

		return $active;
	}

	/**
	 * Vide les caches des systèmes détectés (fonctions officielles de
	 * chacun quand elles existent — jamais en aveugle).
	 *
	 * @return array<string> Actions réellement effectuées.
	 */
	public static function clear_known_caches() {
		$done = array();

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$done[] = 'WP Super Cache cleared';
		}
		if ( defined( 'W3TC' ) && function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$done[] = 'W3 Total Cache flushed';
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			if ( function_exists( 'rocket_clean_minify' ) ) {
				rocket_clean_minify();
			}
			$done[] = 'WP Rocket cache cleared';
		}
		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
			LiteSpeed\Purge::purge_all();
			$done[] = 'LiteSpeed Cache purged';
		}
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
			$done[] = 'Autoptimize cache cleared';
		}
		if ( class_exists( 'WpFastestCache' ) && method_exists( 'WpFastestCache', 'deleteCache' ) ) {
			$wpfc = new WpFastestCache();
			$wpfc->deleteCache( true );
			$done[] = 'WP Fastest Cache deleted';
		}
		if ( class_exists( 'SG_CPress_Core' ) ) {
			// SiteGround : purge via son API publique si présente.
			if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
				sg_cachepress_purge_everything();
				$done[] = 'SiteGround Optimizer purged';
			}
		}
		if ( function_exists( 'hummingbird_flush_cache' ) ) {
			hummingbird_flush_cache();
			$done[] = 'Hummingbird cache flushed';
		}

		// Cache interne WordPress (transients objet) : uniquement les nôtres.
		delete_transient( 'imp_site_stats' );
		delete_transient( 'imp_core_checksums' );

		return $done;
	}

	/**
	 * Flush des règles de réécriture (après migration/restore).
	 *
	 * @return void
	 */
	public static function flush_rewrites() {
		global $wp_rewrite;
		if ( is_object( $wp_rewrite ) ) {
			$wp_rewrite->flush_rules( true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- usage local contrôlé.
		}
		update_option( 'imp_rewrites_flushed_at', time(), false );
	}

	/**
	 * Rapport système complet (page Tools + export diagnostics). Réel.
	 *
	 * @return array<string,mixed>
	 */
	public static function system_report() {
		global $wpdb, $wp_version;

		$stats  = IMP_Site_Stats::get();
		$limits = IMP_Compatibility::php_limits();
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		return array(
			'generated'     => current_time( 'mysql' ),
			'plugin'        => array(
				'version'  => IMP_VERSION,
				'storage'  => IMP_Plugin::storage_dir(),
				'settings' => imp_scrub_settings_for_report(),
			),
			'site'          => array(
				'wordpress'    => $wp_version,
				'multisite'    => is_multisite(),
				'locale'       => get_locale(),
				'theme'        => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $parent ? ' (child of ' . $parent->get( 'Name' ) . ')' : '' ),
				'site_url'     => site_url(),
				'home_url'     => home_url(),
				'abspath'      => ABSPATH,
				'debug'        => WP_DEBUG,
				'files'        => $stats['files'],
				'size'         => $stats['bytes'],
			),
			'server'        => array(
				'php'          => PHP_VERSION,
				'sapi'         => php_sapi_name(),
				'software'     => IMP_Compatibility::server_software(),
				'os'           => PHP_OS_FAMILY,
				'memory'       => $limits['memory_limit'],
				'upload_max'   => $limits['upload_max'],
				'post_max'     => $limits['post_max'],
				'max_execution' => $limits['max_execution'],
				'modules'      => IMP_Compatibility::php_modules(),
			),
			'database'      => array(
				'server'  => IMP_Compatibility::db_version(),
				'prefix'  => $wpdb->prefix,
				'tables'  => $stats['db_tables'],
				'rows'    => $stats['db_rows'],
				'size'    => $stats['db_bytes'],
			),
			'cron'          => array(
				'maintenance_scheduled' => false !== wp_next_scheduled( 'imp_cron_maintenance' ),
				'backup_scheduled'      => false !== wp_next_scheduled( 'imp_cron_backup' ),
			),
			'cache_plugins' => self::active_cache_plugins(),
			'integrations'  => array(
				'woocommerce' => IMP_Integrations_WooCommerce::is_active() ? IMP_Integrations_WooCommerce::version() : null,
				'elementor'   => IMP_Integrations_Elementor::is_active() ? IMP_Integrations_Elementor::version() : null,
			),
			'active_plugins_count' => count( (array) get_option( 'active_plugins', array() ) ),
		);
	}
}

/**
 * Retire toute valeur sensible des réglages avant export de diagnostic.
 *
 * @return array
 */
function imp_scrub_settings_for_report() {
	$settings = imp_settings();
	unset(
		$settings['default_exclusions'],
		$settings['backup_location']
	);
	return $settings;
}
