# Checklist de publication WordPress.org — Infinity Migrate Pro

## 1. Paquet conforme (généré)

Le ZIP **`infinity-migrate-pro-1.6.0-wporg.zip`** est le paquet de
soumission : il EXCLUT `docs/` (stratégie business), `tools/` (dev),
`README.md` (GitHub) et `.gitignore`. Il ne contient que le plugin +
readme.txt + LICENSE + .pot — 100 % code lisible, GPL, zéro obfuscation
(obligatoire sur wp.org : le code encodé est refusé ; la protection du
code reste l'affaire de la build Pro hors wp.org, voir PRO-LICENSING.md).

## 2. Vérifications de conformité (faites)

- [x] Licence GPL v2+ partout, en-têtes conservés
- [x] Aucune télémétrie, aucun appel distant non divulgué (wp.org checksums + GitHub updates uniquement)
- [x] Nonces + capabilities sur chaque action, escaping/sanitization systématiques
- [x] Aucune fonction obsolète, PHP 7.4+ (grammaire vérifiée par parseur)
- [x] Text domain unique `infinity-migrate-pro`, .pot fourni
- [x] uninstall.php propre (backups jamais supprimés)
- [x] readme.txt (description, FAQ, changelog, bannières à ajouter)

## 3. Étapes de soumission

1. Créer le compte + demander l'approbation « Plugin Developer » :
   https://wordpress.org/plugins/developers/add/ (nom du slug :
   `infinity-migrate-pro` — vérifier la disponibilité d'abord).
2. Soumettre ; à l'approbation, télécharger le dépôt SVN :
   `svn co https://plugins.svn.wordpress.org/infinity-migrate-pro`
3. `trunk/` ← contenu du ZIP wporg (fichiers à la racine de trunk).
4. `assets/` du SVN (SÉPARÉ du code) :
   - `banner-772x250.png` + `banner-1544x500.png`
   - `icon-128x128.png` + `icon-256x256.png` (le ∞ sur dégradé bleu)
   - `screenshot-1.png` … `screenshot-4.png` (dashboard, migration,
     backups, restore)
5. `svn add --force . && svn ci -m "1.6.0 initial release"`
6. Après approbation : la version wp.org devient prioritaire
   (l'updater GitHub s'efface automatiquement grâce au garde
   « response déjà présent »).

## 4. Après publication

- Tags SVN par version (`tags/1.6.0/`) à chaque release.
- Le README.md GitHub pointe vers wp.org pour les téléchargements.
- La build Pro (ionCube, canal privé) reste distribuée via GitHub privé.
