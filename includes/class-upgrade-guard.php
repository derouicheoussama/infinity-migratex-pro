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

/**
 * Garde-fou de mise à jour : lance une sauvegarde de sécurité (snapshot
 * base de données) AVANT que WordPress ne télécharge/installe une mise à
 * jour de plugin, thème ou cœur. Fonctionne pour les mises à jour
 * manuelles (admin) ET automatiques (cron) — le filtre upgrader_pre_download
 * précède chaque téléchargement de paquet.
 *
 * Ne bloque JAMAIS la mise à jour : le job part dans le moteur
 * reprise-sur-interruption et est poussé au maximum immédiatement
 * (piggyback) ; l'origine « safety » est exclue de la rétention.
 */
final class IMP_Upgrade_Guard {

	/** @var string Transient anti-doublon (1 sauvegarde / heure max). */
	const DEBOUNCE = 'imp_safety_last';

	/**
	 * Branchement.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'maybe_backup' ), 10, 3 );
	}

	/**
	 * Déclenche la sauvegarde de sécurité si les conditions sont réunies.
	 *
	 * @param bool         $reply     Réponse du filtre (false = échec déjà).
	 * @param string|array $package   Paquet à télécharger.
	 * @param object       $upgrader  Instance WP_Upgrader.
	 * @return bool
	 */
	public static function maybe_backup( $reply, $package, $upgrader ) {
		if ( false === $reply || ! is_string( $package ) || '' === $package ) {
			return $reply; // Téléchargement déjà en échec : ne rien ajouter.
		}
		if ( ! class_exists( 'IMP_License' ) || ! IMP_License::is_pro() ) {
			return $reply; // Fonction Pro.
		}
		if ( ! imp_setting( 'preupdate_backup', 1 ) ) {
			return $reply; // Désactivé dans les réglages.
		}
		// Un seul déclenchement par heure (une session d'updates peut
		// télécharger une dizaine de paquets).
		$last = (int) get_transient( self::DEBOUNCE );
		if ( $last > time() - HOUR_IN_SECONDS ) {
			return $reply;
		}
		// Un job déjà actif : ne pas entrer en concurrence.
		$active = IMP_Job::get_active();
		if ( null !== $active && 'running' === $active['status'] ) {
			return $reply;
		}

		set_transient( self::DEBOUNCE, time(), HOUR_IN_SECONDS );

		$result = IMP_Job::start(
			'backup',
			array(
				'name'       => __( 'Safety backup — before update', 'infinity-migratex-pro' ),
				'components' => 'database',
				'origin'     => 'safety',
			)
		);

		if ( ! empty( $result['ok'] ) ) {
			// Pousse le snapshot aussi loin que possible tout de suite :
			// le dump SQL part AVANT que les fichiers de la mise à jour
			// ne changent. Le reste progresse en tâche de fond (piggyback
			// standard sur les visites/cron).
			IMP_Job::piggyback();

			if ( class_exists( 'IMP_Logger' ) ) {
				IMP_Logger::finish(
					IMP_Logger::start( __( 'Safety backup triggered', 'infinity-migratex-pro' ), IMP_Logger::TYPE_BACKUP, array( 'trigger' => 'upgrader' ) ),
					IMP_Logger::STATUS_COMPLETED,
					__( 'Database safety backup started before a WordPress update.', 'infinity-migratex-pro' )
				);
			}
		}

		return $reply; // Jamais bloquer la mise à jour de l'utilisateur.
	}
}
