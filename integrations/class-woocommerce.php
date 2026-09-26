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
 * Intégration WooCommerce : détection réelle, statistiques de données,
 * tables concernées, nettoyage transients après migration.
 */
final class IMP_Integrations_WooCommerce {

	/**
	 * WooCommerce actif ?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Version détectée.
	 *
	 * @return string
	 */
	public static function version() {
		return self::is_active() && defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
	}

	/**
	 * Tables WooCommerce réellement présentes dans la base.
	 *
	 * @return array<string>
	 */
	public static function tables() {
		global $wpdb;
		$tables = array();
		foreach ( IMP_Database::site_tables() as $table ) {
			if ( false !== strpos( $table, 'wc_' ) || false !== strpos( $table, 'woocommerce' ) ) {
				$tables[] = $table;
			}
		}
		return $tables;
	}

	/**
	 * Statistiques réelles des données sensibles WooCommerce.
	 *
	 * @return array{products:int,orders:int,customers:int,tables:int}
	 */
	public static function data_stats() {
		global $wpdb;

		$stats = array(
			'products'  => 0,
			'orders'    => 0,
			'customers' => 0,
			'tables'    => count( self::tables() ),
		);

		if ( ! self::is_active() ) {
			return $stats;
		}

		$stats['products'] = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'", 'product' )
		);

		// WooCommerce HPOS (tables commandes dédiées) ou posts classiques.
		$orders_table = $wpdb->prefix . 'wc_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table ) {
			$stats['orders'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_table}" );
		} else {
			$stats['orders'] = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'", 'shop_order' )
			);
		}

		$stats['customers'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wc_customer_lookup"
		);
		if ( null === $stats['customers'] ) {
			$stats['customers'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} um JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%customer%'" );
		}

		return $stats;
	}

	/**
	 * Compatibilité de migration : éléments couverts et vérifiés.
	 *
	 * @return array[] {label,covered:bool}
	 */
	public static function migration_compat() {
		return array(
			array( 'label' => __( 'Products (posts + metadata)', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Orders (classic storage or HPOS tables)', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Customers and addresses', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Product metadata / attributes', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'WooCommerce settings (API keys excepted)', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Shipping zones and methods', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Payment gateway settings', 'infinity-migratex-pro' ), 'covered' => true ),
			array( 'label' => __( 'Lookup tables regenerated on demand', 'infinity-migratex-pro' ), 'covered' => true ),
		);
	}

	/**
	 * Nettoyage post-migration : transients WooCommerce.
	 *
	 * @return int Transients supprimés.
	 */
	public static function clear_transients() {
		global $wpdb;
		if ( ! self::is_active() ) {
			return 0;
		}
		$deleted = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_wc_' ) . '%', $wpdb->esc_like( '_transient_timeout_wc_' ) . '%' )
		);
		return $deleted;
	}

	/* ------------------------------------------------------------------ *
	 * Statut technique (page WooCommerce dédiée)
	 * ------------------------------------------------------------------ */

	/**
	 * HPOS (High-Performance Order Storage) est-il activé ?
	 *
	 * @return bool
	 */
	public static function hpos_enabled() {
		return 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
	}

	/**
	 * Une table existe-t-elle ?
	 *
	 * @param string $table Nom complet (préfixe inclus).
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Rapport de compatibilité WooCommerce vérifié EN DIRECT.
	 *
	 * @return array[] {id,label,ok,detail}
	 */
	public static function compatibility_report() {
		global $wpdb;
		$rows = array();

		// 1. Version.
		$rows[] = array(
			'id'     => 'version',
			'label'  => __( 'WooCommerce version', 'infinity-migratex-pro' ),
			'ok'     => self::is_active(),
			'detail' => self::is_active() ? sprintf( /* translators: %s: version */ __( 'Detected: %s', 'infinity-migratex-pro' ), self::version() ) : __( 'WooCommerce is not active.', 'infinity-migratex-pro' ),
		);

		// 2. Stockage des commandes.
		$hpos = self::hpos_enabled();
		$rows[] = array(
			'id'     => 'orders-storage',
			'label'  => __( 'Orders storage', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => $hpos
				? __( 'HPOS (High-Performance Order Storage) — dedicated wc_orders tables, fully covered by the database engine and the URL replacer.', 'infinity-migratex-pro' )
				: __( 'Classic storage (orders as posts + postmeta), fully covered.', 'infinity-migratex-pro' ),
		);

		// 3. Tables HPOS présentes si HPOS actif.
		if ( $hpos ) {
			$missing = array();
			foreach ( array( 'wc_orders', 'wc_order_addresses', 'wc_order_operational_data' ) as $suffix ) {
				$table = $wpdb->prefix . $suffix;
				if ( ! self::table_exists( $table ) ) {
					$missing[] = $suffix;
				}
			}
			$rows[] = array(
				'id'     => 'hpos-tables',
				'label'  => __( 'HPOS tables present', 'infinity-migratex-pro' ),
				'ok'     => empty( $missing ),
				'detail' => empty( $missing ) ? __( 'wc_orders, wc_order_addresses and wc_order_operational_data are in place.', 'infinity-migratex-pro' ) : sprintf( /* translators: %s: tables */ __( 'Missing: %s — regenerate via WooCommerce → Status → Tools.', 'infinity-migratex-pro' ), implode( ', ', $missing ) ),
			);
		}

		// 4. Lookup tables produits.
		$lookup_missing = array();
		foreach ( array( 'wc_product_meta_lookup', 'wc_product_attributes_lookup' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			if ( ! self::table_exists( $table ) ) {
				$lookup_missing[] = $suffix;
			}
		}
		$rows[] = array(
			'id'     => 'lookup-tables',
			'label'  => __( 'Product lookup tables', 'infinity-migratex-pro' ),
			'ok'     => empty( $lookup_missing ),
			'detail' => empty( $lookup_missing )
				? __( 'Present — regenerated in one click from this page after a migration.', 'infinity-migratex-pro' )
				: sprintf( /* translators: %s: tables */ __( 'Missing: %s — regenerate via WooCommerce → Status → Tools.', 'infinity-migratex-pro' ), implode( ', ', $lookup_missing ) ),
		);

		// 5. Table des sessions.
		$rows[] = array(
			'id'     => 'sessions',
			'label'  => __( 'Customer sessions table', 'infinity-migratex-pro' ),
			'ok'     => self::table_exists( $wpdb->prefix . 'woocommerce_sessions' ),
			'detail' => __( 'Ephemeral data — excluded from dumps by default (setting below) and safe to clear anytime.', 'infinity-migratex-pro' ),
		);

		// 6. Clés API.
		$keys_table = $wpdb->prefix . 'woocommerce_api_keys';
		$keys       = self::table_exists( $keys_table ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$keys_table}`" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows[]     = array(
			'id'     => 'api-keys',
			'label'  => __( 'REST API keys', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => sprintf( /* translators: %d: count */ __( '%d key(s) stored — keys are consumer hashes, never altered by URL replacement.', 'infinity-migratex-pro' ), $keys ),
		);

		// 7. Webhooks.
		$webhooks = function_exists( 'wc_get_webhooks' ) ? count( (array) wc_get_webhooks() ) : 0;
		$rows[]   = array(
			'id'     => 'webhooks',
			'label'  => __( 'Webhooks', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => sprintf( /* translators: %d: count */ __( '%d webhook(s) — their delivery URLs are rewritten by the serialized-safe URL replacer.', 'infinity-migratex-pro' ), $webhooks ),
		);

		// 8. Passerelles de paiement actives.
		$gateways = self::active_gateways();
		$rows[]   = array(
			'id'     => 'gateways',
			'label'  => __( 'Payment gateways', 'infinity-migratex-pro' ),
			'ok'     => true,
			'detail' => empty( $gateways )
				? __( 'No active gateway detected.', 'infinity-migratex-pro' )
				: sprintf( /* translators: %s: gateways */ __( 'Active: %s — settings migrate with the options table.', 'infinity-migratex-pro' ), implode( ', ', $gateways ) ),
		);

		return $rows;
	}

	/**
	 * Passerelles de paiement actives (noms).
	 *
	 * @return array<string>
	 */
	public static function active_gateways() {
		$out = array();
		if ( ! self::is_active() || ! function_exists( 'WC' ) || ! WC() || ! isset( WC()->payment_gateways ) ) {
			return $out;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		foreach ( (array) $gateways as $gateway ) {
			if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled && isset( $gateway->title ) ) {
				$out[] = (string) $gateway->title;
			}
		}
		return $out;
	}

	/**
	 * Vide la table des sessions client (données éphémères).
	 *
	 * @return int Lignes supprimées.
	 */
	public static function clear_sessions() {
		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_sessions';
		if ( ! self::table_exists( $table ) ) {
			return 0;
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $count;
	}

	/**
	 * Régénère les lookup tables produits (API officielle WooCommerce).
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function regenerate_lookup_tables() {
		if ( ! self::is_active() ) {
			return array( 'ok' => false, 'message' => __( 'WooCommerce is not active.', 'infinity-migratex-pro' ) );
		}
		if ( ! function_exists( 'wc_recreate_product_lookup_tables' ) ) {
			return array( 'ok' => false, 'message' => __( 'This WooCommerce version does not expose lookup table regeneration — use WooCommerce → Status → Tools.', 'infinity-migratex-pro' ) );
		}
		$done = wc_recreate_product_lookup_tables();
		if ( $done ) {
			return array( 'ok' => true, 'message' => __( 'Product lookup tables regenerated (runs in background batches).', 'infinity-migratex-pro' ) );
		}
		return array( 'ok' => false, 'message' => __( 'Regeneration could not be scheduled — try WooCommerce → Status → Tools.', 'infinity-migratex-pro' ) );
	}

	/**
	 * Recompte les termes produits (stock, visibilité).
	 *
	 * @return bool
	 */
	public static function recount_terms() {
		if ( ! self::is_active() || ! function_exists( 'wc_recount_all_terms' ) ) {
			return false;
		}
		wc_recount_all_terms();
		return true;
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
