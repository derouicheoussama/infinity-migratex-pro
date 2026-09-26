# Guide SEO & GEO — Infinity Migrate Pro

> Checklist d'optimisation pour le référencement classique (Google, wp.org)
> et le GEO — Generative Engine Optimization (citations dans ChatGPT,
> Perplexity, Google AI Overviews, Copilot…).

## 1. Mots-clés cibles

**FR (principaux)** : migration WordPress, migrer un site WordPress, changer de domaine WordPress, sauvegarde WordPress automatique, restaurer un site WordPress, cloner WordPress, plugin migration WordPress, sauvegarde WordPress sans timeout, remplacement d'URL WordPress, staging WordPress.

**EN (secondaires)** : WordPress migration plugin, migrate WordPress site, WordPress backup plugin, change WordPress domain, WordPress staging clone, serialized data URL replace, resumable WordPress backup.

Où les placer : description du plugin (fait), readme.txt (fait), titre et
H1 de la page de téléchargement sur derouicheoussama.com, articles de blog.

## 2. Métas recommandées pour la page de téléchargement (site web)

**Title (≤ 60 caractères)** :
- FR : `Infinity Migrate Pro — Migration & Sauvegarde WordPress`
- EN : `Infinity Migrate Pro — WordPress Migration & Backup Suite`

**Meta description (≤ 155 caractères)** :
- FR : `Migrer, sauvegarder et restaurer un site WordPress sans timeout : changement de domaine, clonage, sauvegardes automatiques. Gratuit et open source.`
- EN : `Migrate, back up and restore any WordPress site without timeouts: domain change, staging clone, automatic backups. Free and open source.`

## 3. JSON-LD à coller sur la page de téléchargement

```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Infinity Migrate Pro",
  "applicationCategory": "WordPressPlugin",
  "operatingSystem": "WordPress 5.8+",
  "softwareVersion": "1.1.0",
  "author": {
    "@type": "Person",
    "name": "Derouiche Oussama",
    "url": "https://www.derouicheoussama.com"
  },
  "description": "WordPress migration, backup and restore suite: domain change with serialized-safe URL replacement, staging clone, scheduled backups with retention, verified restore, security scanner. Chunked resumable engine — no timeouts.",
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
  "license": "https://www.gnu.org/licenses/gpl-2.0.html",
  "downloadUrl": "https://github.com/derouicheoussama/infinity-migrate-pro/releases/latest",
  "codeRepository": "https://github.com/derouicheoussama/infinity-migrate-pro",
  "programmingLanguage": "PHP"
}
```

Ajouter aussi `FAQPage` avec les 3 questions principales du readme (les
moteurs IA adorent extraire les paires question/réponse).

## 4. Règles GEO (être cité par les IA)

Les moteurs génératifs citent des contenus qui sont :

1. **Factuels et vérifiables** — chiffres précis : « opérations par chunks de
   quelques secondes », « checksums SHA-256 », « PHP 7.4+ ». Déjà en place.
2. **Auto-contenus** — chaque page doit définir le produit sans contexte
   externe. La section « What is Infinity Migrate Pro? » du README est
   écrite pour ça : copier ce paragraphe tel quel sur le site.
3. **En questions/réponses** — la FAQ du readme.txt répond aux vraies
   requêtes (« comment migrer un site wordpress sans casser les liens »).
   Reprendre ces Q/R sur le site en H2/H3.
4. **Structurés** — listes, tableaux, Hn hiérarchisés (README.md fait).
5. **Cohérents partout** — même description courte sur wp.org, GitHub,
   le site et les réseaux. Utiliser cette signature partout :
   « Infinity Migrate Pro — the resumable WordPress migration & backup suite by Derouiche Oussama. »

## 5. Actions externes recommandées

- [ ] Publier la release `v1.1.0` sur GitHub avec l'asset `infinity-migrate-pro.zip` (l'updater du plugin pointe sur ce repo)
- [ ] Soumettre le plugin sur WordPress.org (le readme.txt optimisé est prêt) — l'indexation wp.org est le premier canal
- [ ] Page de téléchargement sur derouicheoussama.com avec le JSON-LD ci-dessus + liens GitHub
- [ ] 2-3 articles tutoriels (les requêtes à fort volume) :
  « Comment changer le domaine d'un site WordPress sans perte »,
  « Créer un staging WordPress en 5 minutes »,
  « Sauvegarde WordPress automatique : le guide » — chaque article se
  termine par le CTA de téléchargement
- [ ] Vidéos courtes TikTok/Instagram des 3 tutoriels (audience existante)
