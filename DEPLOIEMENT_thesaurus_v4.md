# Déploiement — thésaurus SMF externalisés (profil Joconde v4)

Ce guide décrit l'installation d'une instance CollectiveAccess (Providence) utilisant
le **profil Joconde v4**, où les thésaurus SMF/Joconde (Opentheso) sont servis via
**InformationService** au lieu d'être chargés dans `ca_list_items`.

**Bénéfice** : base installée bien plus légère (pas des milliers d'items de liste,
index de recherche non gonflé), install plus simple (aucun harvest en base), et
sélection assistée par un widget arborescent côté client.

---

## 1. Prérequis serveur

- **PHP** avec **mbstring** (gd déjà requis par CA). *(pas de sqlite requis)*
- **Un cache CA fonctionnel** — le défaut `file` (cache disque) **suffit**. Le widget arborescent est
  100 % client (JSON statique + IndexedDB) et ne dépend d'aucun cache serveur. Le seul usage serveur
  est l'**autocomplétion native** du champ, qui met en cache un index compact via `ExternalCache`
  (backend configuré de CA). **redis n'est donc PAS requis** ; il n'est qu'un *plus* (cache plus rapide)
  recommandé uniquement en forte concurrence sur un très gros thésaurus (ex. th285). Sans redis, le
  cache fichier fait le travail.
- **Apache mod_deflate** avec compression de `application/json` (1er téléchargement des stores :
  th285 passe de ~17 Mo à ~0,9 Mo). Exemple de drop-in :
  ```apache
  # /etc/apache2/conf-available/deflate-json.conf  (puis: a2enconf deflate-json && a2enmod deflate)
  <IfModule mod_deflate.c><IfModule mod_filter.c>
    AddOutputFilterByType DEFLATE application/json
  </IfModule></IfModule>
  ```
- Le `.htaccess` de Providence doit autoriser le service des `.json` (déjà le cas par défaut).

---

## 2. Installation d'une NOUVELLE instance

1. **Déployer le plugin museesDeFrance (version v4+)** dans `app/plugins/museesDeFrance`
   (clone git ou symlink). Il embarque : le plugin IS `SMFThesaurus`, le helper
   `SMFThesaurusStore`, les **stores JSON** (`assets/thesauri/thNNN.json`), le **widget**
   (`assets/js|css`), et le **profil** (`assets/profile/profil_joconde_v4.xml`).

2. **Charger le profil v4** — nouvelle install. Le profil (et le `profile.xsd` requis par l'installeur)
   sont fournis dans `app/plugins/museesDeFrance/assets/profile/` :
   ```bash
   php support/bin/caUtils install \
       --profile-directory app/plugins/museesDeFrance/assets/profile \
       --profile-name profil_joconde_v4 \
       --admin-email admin@example.org [--overwrite]
   ```
   (Sur une install existante à réaligner : `caUtils update-installation-profile` avec les mêmes
   `--profile-directory`/`--profile-name`.)

3. **Étapes « glue » par instance** (ce que le profil ne fait pas) — script idempotent :
   ```bash
   php app/plugins/museesDeFrance/command-line/install_smf_thesaurus.php \
       --base-dir /var/www/.../providence --apply
   ```
   Il crée le **symlink du plugin IS** dans `app/lib/Plugins/InformationService/`, ajoute les
   **entrées de traduction** dans `app/conf/translations.conf` (« Plus d'informations »), et
   **contrôle les prérequis** (stores, redis, mod_deflate) en avertissement.

4. **NE PAS lancer le harvest** des thésaurus (`lib/get_or_update_all_thesaurus.php`) : en v4 il est
   inutile (les champs sont en IS). Un garde-fou l'empêche déjà d'importer un thésaurus déjà externalisé.

5. **Vider le cache** : `php support/bin/caUtils clear-caches` (ou flush redis de l'instance).

---

## 3. Vérification

- Élément IS bien reconnu :
  ```sql
  SELECT element_code, datatype FROM ca_metadata_elements WHERE datatype = 20;  -- 20 = InformationService
  ```
- Store servi + compressé :
  ```bash
  curl -H "Accept-Encoding: gzip" -I https://<host>/<url_root>/app/plugins/museesDeFrance/assets/thesauri/th294.json
  # -> Content-Encoding: gzip
  ```
- Dans l'éditeur d'objet : un champ SMF (ex. Domaine) affiche l'autocomplétion IS + le bouton
  « Parcourir l'arbre » ; la sélection enregistre le libellé, l'URI Opentheso est conservée.

---

## 4. Mettre à jour un thésaurus

Reconstruire un store depuis Opentheso (déterministe, diff git propre) :
```bash
php app/plugins/museesDeFrance/command-line/build_thesaurus_json.php --id th294
```
Le widget re-télécharge automatiquement (vérif de version par `Last-Modified` du fichier) ;
le cache serveur compact s'invalide sur le `mtime` du JSON.

---

## 5. Migrer une instance EXISTANTE (Liste -> IS)

Pour un musée déjà en production sur des listes locales/th* : convertir chaque élément et migrer
ses valeurs (transactionnel, backups, appariement par `idno` ou par libellé) :
```bash
php app/plugins/museesDeFrance/command-line/migrate_element_to_is.php \
    --element domaine --thesaurus th294 --match idno \
    --base-dir /var/www/.../providence --database <db> --apply
# puis idem pour les autres champs (--match label pour les lexiques dmf_lex*), puis reindex.
```
Après migration, les listes devenues orphelines (aucun élément ne les référence, cf. `SELECT ... JOIN ca_lists`)
peuvent être supprimées pour alléger la base — **après dump de sécurité** et en vérifiant qu'aucune
n'est partagée par un autre élément.

---

## 6. Notes d'architecture

- **Côté client** : le widget télécharge chaque thésaurus une fois puis le persiste en **IndexedDB** ;
  navigation + recherche 100 % client ensuite (aucun endpoint serveur). Fraîcheur par requête HEAD.
- **Côté serveur** : seule l'**autocomplétion native** du champ IS passe par `SMFThesaurus::lookup()`,
  qui utilise un **index compact caché en redis** (clé par mtime) — pas de re-parse du gros JSON par requête.
- **Stockage sur l'objet** : CA range le `prefLabel` (indexé/affiché) et l'`URI Opentheso` dans des
  colonnes séparées (`value_longtext1` / `value_longtext2`). Termes hors thésaurus = texte libre (URI vide).
