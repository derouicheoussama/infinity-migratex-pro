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
 * Tunnel d'achat Pro intégré — 4 étapes dans le plugin, sans quitter WordPress.
 *
 * 1. Plan        : Personal (1 site) ou Business (5 sites), annuel ou à vie.
 * 2. Coordonnées : nom, e-mail, WhatsApp optionnel (formulaire pratique).
 * 3. Paiement    : récapitulatif, paiement en ligne si configuré, copie de
 *                  la commande, raccourci « j'ai déjà une clé ».
 * 4. Activation  : clé de licence → Pro actif immédiatement, sans recharger.
 *
 * Le markup est rendu dans .imp-wrap (page_close) : il hérite du thème
 * clair / sombre / auto sans couleur figée. Déclencheurs : tout élément
 * [data-imp-checkout] (valeur = plan présélectionné), cartes Pro floutées,
 * upsells Turbo. Les prix sont des attributs data lus par le JS.
 */
final class IMP_Checkout {

	/**
	 * Déjà rendu sur cette page ? (évite le doublon page_close + admin_footer)
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Branchement : rendu autonome sur la page des extensions WordPress
	 * (plugins.php n'a ni .imp-wrap ni nos assets — on les y apporte).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets_plugins_screen' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_standalone' ) );
	}

	/**
	 * Sur plugins.php uniquement : charge l'interface du plugin pour que
	 * le tunnel d'achat y fonctionne comme dans les pages du plugin.
	 *
	 * @param string $hook Hook d'écran courant.
	 * @return void
	 */
	public static function assets_plugins_screen( $hook ) {
		if ( 'plugins.php' !== $hook || self::$rendered ) {
			return;
		}
		if ( ! IMP_Capabilities::user_can( 'manage' ) && ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// PERFORMANCE — micro-bundle dédié (~18 Ko) au lieu du pack admin
		// complet (~95 Ko) : uniquement les styles et le JS du tunnel
		// d'achat, extraits automatiquement de admin.css/admin.js par
		// tools/build-plugins-bundle.mjs (régénérer après modification
		// de ces fichiers). SCRIPT_DEBUG sert les sources lisibles.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_style( 'imp-checkout', IMP_URL . "assets/css/imp-checkout{$suffix}.css", array(), IMP_VERSION );
		wp_enqueue_script( 'imp-checkout', IMP_URL . "assets/js/imp-checkout{$suffix}.js", array(), IMP_VERSION, true );
		wp_localize_script(
			'imp-checkout',
			'IMP_Admin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'postUrl' => admin_url( 'admin-post.php' ),
				'nonce'   => wp_create_nonce( 'imp-admin' ),
				'page'    => 'plugins',
				'job'     => array( 'status' => '' ),
				'confirm' => 1,
				'debug'   => 0,
				'isPro'   => IMP_License::is_pro() ? 1 : 0,
				'theme'   => 'light',
				'proUrl'  => IMP_License::checkout_url(),
				'i18n'    => array(
					'close'       => __( 'Close', 'infinity-migratex-pro' ),
					'error'       => __( 'Error', 'infinity-migratex-pro' ),
					'networkError' => __( 'Connection lost — try again.', 'infinity-migratex-pro' ),
				),
			)
		);
	}

	/**
	 * Rendu autonome (admin_footer) : uniquement sur plugins.php si la
	 * modale n'a pas déjà été montée dans .imp-wrap par page_close().
	 *
	 * @return void
	 */
	public static function render_standalone() {
		if ( self::$rendered ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		echo '<div class="imp-co-scope">';
		self::render();
		echo '</div>';
	}

	/**
	 * Rend la modale (appelée une fois par page du plugin).
	 *
	 * @return void
	 */
	public static function render() {
		if ( self::$rendered ) {
			return;
		}
		if ( ! IMP_Capabilities::user_can( 'manage' ) && ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		self::$rendered = true;
		$is_pro = IMP_License::is_pro();
		?>
		<div class="imp-checkout" id="imp-checkout" hidden role="dialog" aria-modal="true" aria-labelledby="imp-co-title">
			<div class="imp-co-overlay" data-imp-co-close tabindex="-1"></div>
			<div class="imp-co-dialog" role="document">
				<button type="button" class="imp-co-x" data-imp-co-close aria-label="<?php esc_attr_e( 'Close', 'infinity-migratex-pro' ); ?>">×</button>

				<header class="imp-co-head">
					<img class="imp-co-logo" src="<?php echo esc_url( IMP_URL . 'assets/images/logo-migratex-64.png' ); ?>" alt="" />
					<div>
						<h2 id="imp-co-title">★ <?php esc_html_e( 'Go Pro — Infinity MigrateX Pro', 'infinity-migratex-pro' ); ?></h2>
						<p><?php esc_html_e( 'Cloud backups, scheduling, AES-256 encryption and turbo speed — in 4 quick steps.', 'infinity-migratex-pro' ); ?></p>
					</div>
				</header>

				<ol class="imp-co-steps" aria-label="<?php esc_attr_e( 'Purchase steps', 'infinity-migratex-pro' ); ?>">
					<li data-imp-co-dot="1" class="is-active"><span>1</span><em><?php esc_html_e( 'Plan', 'infinity-migratex-pro' ); ?></em></li>
					<li data-imp-co-dot="2"><span>2</span><em><?php esc_html_e( 'Details', 'infinity-migratex-pro' ); ?></em></li>
					<li data-imp-co-dot="3"><span>3</span><em><?php esc_html_e( 'Payment', 'infinity-migratex-pro' ); ?></em></li>
					<li data-imp-co-dot="4"><span>4</span><em><?php esc_html_e( 'Activate', 'infinity-migratex-pro' ); ?></em></li>
				</ol>

				<div class="imp-co-body">
					<!-- Étape 1 : choix du plan. -->
					<section data-imp-co-step="1">
						<div class="imp-co-billing" role="group" aria-label="<?php esc_attr_e( 'Billing cycle', 'infinity-migratex-pro' ); ?>">
							<button type="button" class="is-on" data-imp-co-billing="yearly"><?php esc_html_e( 'Yearly', 'infinity-migratex-pro' ); ?></button>
							<button type="button" data-imp-co-billing="lifetime"><?php esc_html_e( 'Lifetime', 'infinity-migratex-pro' ); ?> <small><?php esc_html_e( 'best value', 'infinity-migratex-pro' ); ?></small></button>
						</div>

						<div class="imp-co-plans">
							<label class="imp-co-plan" data-imp-co-plan="personal">
								<input type="radio" name="imp_co_plan" value="personal" checked />
								<span class="imp-co-plan-name"><?php esc_html_e( 'Personal', 'infinity-migratex-pro' ); ?></span>
								<span class="imp-co-plan-sites"><?php esc_html_e( '1 site', 'infinity-migratex-pro' ); ?></span>
								<span class="imp-co-plan-price"><b data-imp-co-price="39">39€</b><i data-imp-co-per>/<?php esc_html_e( 'year', 'infinity-migratex-pro' ); ?></i></span>
								<ul>
									<li><?php esc_html_e( 'Everything in Free', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'Cloud backups (Drive, Dropbox, FTP)', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'Scheduled backups + e-mail alerts', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'AES-256 archive encryption', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'Turbo speed + priority support', 'infinity-migratex-pro' ); ?></li>
								</ul>
								<span class="imp-co-tick" aria-hidden="true">✓</span>
							</label>

							<label class="imp-co-plan" data-imp-co-plan="business">
								<input type="radio" name="imp_co_plan" value="business" />
								<span class="imp-co-flag"><?php esc_html_e( 'Best value', 'infinity-migratex-pro' ); ?></span>
								<span class="imp-co-plan-name"><?php esc_html_e( 'Business', 'infinity-migratex-pro' ); ?></span>
								<span class="imp-co-plan-sites"><?php esc_html_e( '5 sites', 'infinity-migratex-pro' ); ?></span>
								<span class="imp-co-plan-price"><b data-imp-co-price="89">89€</b><i data-imp-co-per>/<?php esc_html_e( 'year', 'infinity-migratex-pro' ); ?></i></span>
								<ul>
									<li><?php esc_html_e( 'Everything in Personal', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( '5 sites — agencies & freelancers', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'Priority support, front of the queue', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'Early access to new destinations', 'infinity-migratex-pro' ); ?></li>
									<li><?php esc_html_e( 'Transferable between your clients', 'infinity-migratex-pro' ); ?></li>
								</ul>
								<span class="imp-co-tick" aria-hidden="true">✓</span>
							</label>
						</div>

						<button type="button" class="imp-co-compare-link" data-imp-co-compare><?php esc_html_e( 'Compare Free vs Pro', 'infinity-migratex-pro' ); ?> ▾</button>
						<?php if ( ! $is_pro && ! IMP_License::trial_used() ) : ?>
							<p style="margin:10px 0 0;text-align:center;">
								<button type="button" class="imp-btn imp-btn-ghost" data-imp-co-trial>🎁 <?php esc_html_e( 'Or start the 14-day free PRO trial', 'infinity-migratex-pro' ); ?></button>
							</p>
						<?php endif; ?>
						<div class="imp-co-compare" hidden>
							<table>
								<thead><tr><th><?php esc_html_e( 'Feature', 'infinity-migratex-pro' ); ?></th><th><?php esc_html_e( 'Free', 'infinity-migratex-pro' ); ?></th><th>Pro</th></tr></thead>
								<tbody>
									<tr><td><?php esc_html_e( 'Backups, restore, migration (unlimited size)', 'infinity-migratex-pro' ); ?></td><td>✓</td><td>✓</td></tr>
									<tr><td><?php esc_html_e( 'Cloud destinations (Drive, Dropbox, FTP)', 'infinity-migratex-pro' ); ?></td><td>—</td><td>✓</td></tr>
									<tr><td><?php esc_html_e( 'Scheduled backups + e-mail alerts', 'infinity-migratex-pro' ); ?></td><td>—</td><td>✓</td></tr>
									<tr><td><?php esc_html_e( 'AES-256 backup encryption', 'infinity-migratex-pro' ); ?></td><td>—</td><td>✓</td></tr>
									<tr><td><?php esc_html_e( 'Turbo speed + cross-site URL rewrite', 'infinity-migratex-pro' ); ?></td><td>—</td><td>✓</td></tr>
									<tr><td><?php esc_html_e( 'Priority support', 'infinity-migratex-pro' ); ?></td><td>—</td><td>✓</td></tr>
								</tbody>
							</table>
						</div>
					</section>

					<!-- Étape 2 : coordonnées. -->
					<section data-imp-co-step="2" hidden>
						<p class="imp-co-lede"><?php esc_html_e( 'Your license key and receipt are sent to this e-mail address.', 'infinity-migratex-pro' ); ?></p>
						<div class="imp-co-form">
							<label>
								<span><?php esc_html_e( 'Full name', 'infinity-migratex-pro' ); ?> *</span>
								<input type="text" name="imp_co_name" autocomplete="name" placeholder="<?php esc_attr_e( 'John Doe', 'infinity-migratex-pro' ); ?>" />
							</label>
							<label>
								<span><?php esc_html_e( 'E-mail', 'infinity-migratex-pro' ); ?> *</span>
								<input type="email" name="imp_co_email" autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'infinity-migratex-pro' ); ?>" />
							</label>
							<label>
								<span><?php esc_html_e( 'WhatsApp (optional)', 'infinity-migratex-pro' ); ?></span>
								<input type="text" name="imp_co_whatsapp" autocomplete="tel" placeholder="+213 …" />
							</label>
							<label>
								<span><?php esc_html_e( 'Website', 'infinity-migratex-pro' ); ?></span>
								<input type="url" value="<?php echo esc_url( home_url() ); ?>" readonly data-imp-co-site />
							</label>
						</div>
						<p class="imp-co-note"><?php esc_html_e( 'No account needed — the license is delivered by e-mail right after payment.', 'infinity-migratex-pro' ); ?></p>
					</section>

					<!-- Étape 3 : paiement. -->
					<section data-imp-co-step="3" hidden>
						<div class="imp-co-summary">
							<h3><?php esc_html_e( 'Order summary', 'infinity-migratex-pro' ); ?></h3>
							<dl>
								<div><dt><?php esc_html_e( 'Plan', 'infinity-migratex-pro' ); ?></dt><dd data-imp-co-sum-plan>—</dd></div>
								<div><dt><?php esc_html_e( 'Billing', 'infinity-migratex-pro' ); ?></dt><dd data-imp-co-sum-billing>—</dd></div>
								<div><dt><?php esc_html_e( 'Sites', 'infinity-migratex-pro' ); ?></dt><dd data-imp-co-sum-sites>—</dd></div>
								<div><dt><?php esc_html_e( 'Name', 'infinity-migratex-pro' ); ?></dt><dd data-imp-co-sum-name>—</dd></div>
								<div><dt><?php esc_html_e( 'E-mail', 'infinity-migratex-pro' ); ?></dt><dd data-imp-co-sum-email>—</dd></div>
								<div class="imp-co-total"><dt><?php esc_html_e( 'Total', 'infinity-migratex-pro' ); ?></dt><dd data-imp-co-sum-total>—</dd></div>
							</dl>
						</div>

						<?php if ( '' !== INFINITY_MIGRATEX_PRO_CHECKOUT_URL ) : ?>
							<a class="imp-btn imp-btn-primary imp-co-pay" href="<?php echo esc_url( INFINITY_MIGRATEX_PRO_CHECKOUT_URL ); ?>" target="_blank" rel="noopener noreferrer">🔒 <?php esc_html_e( 'Pay securely online', 'infinity-migratex-pro' ); ?></a>
							<p class="imp-co-note"><?php esc_html_e( 'After payment, come back here and activate the license key you received by e-mail.', 'infinity-migratex-pro' ); ?></p>
						<?php else : ?>
							<div class="imp-co-order">
								<p class="imp-co-note" style="margin-top:0;"><?php esc_html_e( 'Pay by order — send your order details with one click and receive your license key by e-mail:', 'infinity-migratex-pro' ); ?></p>
								<div class="imp-co-pay-alt">
									<?php if ( '' !== INFINITY_MIGRATEX_PRO_SALES_EMAIL ) : ?>
										<button type="button" class="imp-btn imp-btn-primary" data-imp-order-channel="email" data-sales="<?php echo esc_attr( INFINITY_MIGRATEX_PRO_SALES_EMAIL ); ?>">✉ <?php esc_html_e( 'Order by e-mail', 'infinity-migratex-pro' ); ?></button>
									<?php endif; ?>
									<?php if ( '' !== INFINITY_MIGRATEX_PRO_SALES_WHATSAPP ) : ?>
										<button type="button" class="imp-btn imp-btn-primary" data-imp-order-channel="whatsapp" data-sales="<?php echo esc_attr( INFINITY_MIGRATEX_PRO_SALES_WHATSAPP ); ?>">💬 <?php esc_html_e( 'Order by WhatsApp', 'infinity-migratex-pro' ); ?></button>
									<?php endif; ?>
								</div>
								<?php if ( '' === INFINITY_MIGRATEX_PRO_SALES_EMAIL && '' === INFINITY_MIGRATEX_PRO_SALES_WHATSAPP ) : ?>
									<p class="imp-co-note"><?php esc_html_e( 'Online checkout is being set up. Meanwhile: copy your order details below and send them via the author’s website — or continue to activation if you already have a key.', 'infinity-migratex-pro' ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="imp-co-pay-alt">
							<button type="button" class="imp-btn imp-btn-ghost" data-imp-co-copy>⧉ <?php esc_html_e( 'Copy order details', 'infinity-migratex-pro' ); ?></button>
							<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( IMP_License::checkout_url() ); ?>" target="_blank" rel="noopener noreferrer">↗ <?php esc_html_e( 'Open the order page', 'infinity-migratex-pro' ); ?></a>
						</div>
					</section>

					<!-- Étape 4 : activation. -->
					<section data-imp-co-step="4" hidden>
						<div data-imp-co-act <?php echo $is_pro ? 'hidden' : ''; ?>>
							<p class="imp-co-lede"><?php esc_html_e( 'Paste the license key you received after payment.', 'infinity-migratex-pro' ); ?></p>
							<div class="imp-co-keyrow">
								<input type="text" name="imp_co_key" placeholder="IMPX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" />
								<button type="button" class="imp-btn imp-btn-primary" data-imp-co-activate><?php esc_html_e( 'Activate Pro', 'infinity-migratex-pro' ); ?></button>
							</div>
							<p class="imp-co-error" data-imp-co-error hidden></p>
							<p class="imp-co-note"><?php esc_html_e( 'Activation is verified and rate-limited. Your key is bound to this site.', 'infinity-migratex-pro' ); ?></p>
						</div>

						<div class="imp-co-success" data-imp-co-success <?php echo $is_pro ? '' : 'hidden'; ?>>
							<span class="imp-co-check" aria-hidden="true">✓</span>
							<h3><?php esc_html_e( 'Pro is active on this site', 'infinity-migratex-pro' ); ?></h3>
							<p><?php esc_html_e( 'Unlocked instantly: cloud destinations, scheduled backups with e-mail alerts, AES-256 encryption, turbo speed and priority support.', 'infinity-migratex-pro' ); ?></p>
							<button type="button" class="imp-btn imp-btn-primary" data-imp-co-finish><?php esc_html_e( 'Start using Pro →', 'infinity-migratex-pro' ); ?></button>
						</div>
					</section>
				</div>

				<footer class="imp-co-foot">
					<button type="button" class="imp-btn imp-btn-ghost" data-imp-co-back hidden>← <?php esc_html_e( 'Back', 'infinity-migratex-pro' ); ?></button>
					<span class="imp-co-secure">🔒 <?php esc_html_e( 'Secure · 14-day money-back guarantee', 'infinity-migratex-pro' ); ?></span>
					<button type="button" class="imp-btn imp-btn-primary" data-imp-co-next><?php esc_html_e( 'Continue →', 'infinity-migratex-pro' ); ?></button>
				</footer>
			</div>
		</div>
		<?php
	}
}
