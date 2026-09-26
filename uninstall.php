<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity Migrate Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — signature et mentions à conserver.
 */

/**
 * Désinstallation.
 *
 * RÈGLE ABSOLUE : les backups et packages de l'utilisateur NE SONT JAMAIS
 * supprimés — même si l'option « Delete plugin data on uninstall » est
 * active. Cette option ne retire que les réglages, le journal et les
 * rapports du plugin. Par défaut : FALSE (rien n'est effacé).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$settings = get_option( 'imp_settings', array() );
$delete   = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

// Nettoyage inconditionnel : cron et job en cours (jamais les backups).
wp_clear_scheduled_hook( 'imp_cron_backup' );
wp_clear_scheduled_hook( 'imp_cron_maintenance' );
wp_clear_scheduled_hook( 'imp_cron_integrity' );
delete_option( 'imp_active_job' );

// Capabilities dédiées retirées de tous les rôles.
if ( function_exists( 'get_roles' ) || class_exists( 'WP_Roles' ) ) {
	$caps = array(
		'infinity_migrate_manage',
		'infinity_migrate_backup',
		'infinity_migrate_restore',
		'infinity_migrate_settings',
		'infinity_migrate_logs',
	);
	$roles = wp_roles();
	if ( $roles && isset( $roles->roles ) && is_array( $roles->roles ) ) {
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}

// Transients du plugin.
$transients = array(
	'imp_site_stats',
	'imp_core_checksums',
	'imp_gh_latest',
	'imp_job_lock',
	'imp_bg_last_step',
	'imp_activation_notice',
	'imp_updated_notice',
);
foreach ( $transients as $transient ) {
	delete_transient( $transient );
}

// Baseline d'intégrité + statut + erreur d'activation.
delete_option( 'imp_integrity_baseline' );
delete_option( 'imp_integrity_status' );
delete_option( 'imp_activation_error' );
delete_option( 'imp_fatal_trap' );

// Mu-plugin de diagnostic auto-installé.
$imp_mu = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : trailingslashit( WP_CONTENT_DIR ) . 'mu-plugins' ) . '/imp-diag.php';
if ( file_exists( $imp_mu ) ) {
	wp_delete_file( $imp_mu );
}

// Transients d'identifiants de migration résiduels (clés aléatoires).
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_imp\\_migration\\_creds\\_%' OR option_name LIKE '\\_transient\\_timeout\\_imp\\_migration\\_creds\\_%'"
);

if ( ! $delete ) {
	return; // Fin : données conservées, backups intacts.
}

/* ------------------------------------------------------------------ *
 * Suppression explicite des données du plugin (option demandée).
 * Les fichiers de backup dans le stockage restent sur disque.
 * ------------------------------------------------------------------ */

// Table du journal.
$table = $wpdb->prefix . 'imp_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Options.
$options = array(
	'imp_settings',
	'imp_db_version',
	'imp_last_scan',
	'imp_stats',
	'imp_license',
	'imp_last_scheduled_backup',
	'imp_rewrites_flushed_at',
);
foreach ( $options as $option ) {
	delete_option( $option );
}
