# Guide Pro — Licence, canal GitHub privé, protection du code

> Architecture complète pour vendre et distribuer Infinity Migrate Pro
> en liant le plugin au compte GitHub de Derouiche Oussama.

## 1. Grille tarifaire recommandée (site de vente)

| Édition | Sites | Annuel | À vie (lifetime) |
|---|---|---|---|
| **Personal** | 1 site | **39 €/an** | **79 €** |
| **Business** | 5 sites | **89 €/an** | **179 €** |
| *(futur)* Agency | 25 sites | 199 €/an | — |

**Positionnement marché** (repères 2026) : Duplicator Pro ≈ 119 $/an (3 sites) ·
UpdraftPlus Premium ≈ 95 $/an (5 sites) · WPvivid Pro ≈ 99 $/an (3 sites).
L'offre **lifetime** est votre arme de lancement : rare chez la concurrence,
elle justifie un prix 2× l'annuel et crée des ambassadeurs.

**Promotion de lancement conseillée** : −30 % la première année
(Personal 29 €, Business 69 €) pendant 2 semaines.

## 2. Flux de licence signée (anti-contrefaçon)

Constantes côté client (wp-config.php) :

```php
define( 'INFINITY_MIGRATE_PRO_CHECKOUT_URL', 'https://votre-boutique/checkout' );
define( 'INFINITY_MIGRATE_PRO_LICENSE_API',  'https://votre-serveur/api/licence' );
define( 'INFINITY_MIGRATE_PRO_LICENSE_SECRET', 'votre-secret-partage-tres-long' );
```

Contrat de l'API (POST `license_key` + `site_url`) — réponse JSON :

```json
{
  "valid": true,
  "plan": "site1",            // ou "site5"
  "billing": "yearly",        // ou "lifetime"
  "expires": 1780000000,      // 0 = à vie
  "email": "client@mail.com",
  "domain": "clientsite.com", // domaine lié (anti-piratage)
  "sig": "<hmac_sha256>"      // voir ci-dessous
}
```

**Signature** (le client la vérifie, toute réponse altérée est refusée) :

```
sig = hash_hmac( 'sha256', "CLE|DOMAINE|PLAN|EXPIRES", SECRET )
```

Le plugin vérifie : signature valide → statut actif → **domaine lié =
domaine du site**. Une clé copiée sur un autre site perd les fonctions Pro
automatiquement. 5 tentatives d'activation/heure max (anti brute-force).

## 3. Canal Pro GitHub (mises à jour avancées)

1. Créez un dépôt **privé** `infinity-migrate-pro-pro` (ou gardez le public
   pour la version gratuite et un privé pour la Pro).
2. Générez un **fine-grained token** (read-only, contents) sur votre compte
   GitHub derouicheoussama.
3. Chez chaque client Pro, ajoutez dans wp-config.php :

```php
define( 'INFINITY_MIGRATE_PRO_UPDATE_CHANNEL', 'pro' );
define( 'INFINITY_MIGRATE_PRO_GH_REPO', 'derouicheoussama/infinity-migrate-pro-pro' );
define( 'INFINITY_MIGRATE_PRO_GH_TOKEN', 'github_pat_…' );
```

Le plugin télécharge alors les releases **privées** via un proxy
authentifié intégré (`admin-post.php?action=imp_proxy_update`, nonce +
capability `update_plugins`). Publiez chaque release avec l'asset
`infinity-migrate-pro.zip` (nom inchangé).

## 4. Anti-copie du code source (build Pro)

Le PHP distribué ne peut jamais être *rendu* illisible à 100 % — la
protection professionnelle se joue en 3 niveaux complémentaires :

1. **Scellement d'intégrité** (implémenté, activé) : baseline SHA-256 de
   tous les fichiers, vérification quotidienne + post-mise à jour + alerte
   admin — toute copie modifiée/revendue est détectable.
2. **Licence liée + signée** (implémenté) : sans clé valide au domaine,
   les fonctions Pro se verrouillent d'elles-mêmes — une copie piratée
   vaut une version gratuite.
3. **Build Pro obfusqué** (à la distribution) : encoder la build Pro avec
   **ionCube Encoder** (standard industriel, décodeur officiel chez les
   hébergeurs) ou **SourceGuardian**. Workflow :
   - `git checkout build-pro` → exécuter l'encodeur sur `core/` + `includes/`
     (garder `admin/` lisible pour l'UI) → `node tools/make-pot.mjs` →
     `zip infinity-migrate-pro.zip`.
   - Ne JAMAIS publier la build obfusquée sur wp.org (règle du dépôt) :
     la gratuite reste open source, la Pro est distribuée via GitHub
     privé / votre boutique.

La gratuite open source reste votre meilleur marketing : elle protège la
marque (∞ Infinity Coder signé dans chaque fichier) et alimente les
avis wp.org qui vendent la Pro.

## 5. Checklist de lancement Pro

- [ ] Boutique (Lemon Squeezy / Gumroad) avec 2 produits : Personal 1 site, Business 5 sites
- [ ] Serveur de licence (endpoint JSON signé HMAC, table clés : clé, plan, domaine, expires)
- [ ] Dépôt GitHub privé Pro + token read-only par client (révocable = coupe-nettoir anti-piratage)
- [ ] Page de vente derouicheoussama.com avec JSON-LD (docs/SEO-GEO.md) et la grille tarifaire
- [ ] Build Pro ionCube dans la pipeline de release
