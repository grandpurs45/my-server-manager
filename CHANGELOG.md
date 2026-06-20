# Changelog

All notable changes to this project will be documented in this file.

## [1.2.4](https://github.com/grandpurs45/my-server-manager/compare/v1.2.3...v1.2.4) (2026-06-20)

### Documentation

* correction de l'ordre du guide d'installation vierge pour ne plus appeler `scripts/setup.php` avant le clone du projet
* alignement du resume rapide README avec le flux bootstrap minimal, clone, setup MSM
* remplacement des exemples `apt` par `apt-get` pour les commandes scriptables
* ajout du cas APT `Dependances non satisfaites` / code 100 dans les erreurs frequentes
* separation des commandes MariaDB de creation de base et rappel explicite de personnalisation du mot de passe
* ajout du bootstrap pre-clone a la roadmap v1.x

### Bug Fixes

* ajout d'actions recommandees quand `php scripts/setup.php --install-deps --yes` echoue sur un etat APT casse
* correction du code retour CLI quand la connexion base ou les migrations echouent

## [1.2.3](https://github.com/grandpurs45/my-server-manager/compare/v1.2.2...v1.2.3) (2026-06-20)

### Features

* ajout de `php scripts/setup.php --install-deps` pour afficher ou executer avec `--yes` l'installation des dependances systeme
* ajout de `php scripts/setup.php --composer-install` pour installer les dependances PHP du projet
* ajout des couleurs ANSI dans `scripts/check-prerequisites.php`

### Documentation

* suppression de l'etape inutile si le terminal n'est pas dans `/var/www/html/msm`
* repli des erreurs frequentes dans le guide d'installation pour ne plus casser le deroule principal
* remplacement des commandes `chown "$USER"` par des exemples avec proprietaire applicatif explicite
* mise en evidence du remplacement obligatoire de `CHANGE_ME_STRONG_PASSWORD`

## [1.2.2](https://github.com/grandpurs45/my-server-manager/compare/v1.2.1...v1.2.2) (2026-06-19)

### Bug Fixes

* ajout de couleurs ANSI dans `scripts/setup.php` pour distinguer rapidement `OK`, `WARN`, `FAIL` et `INFO`

### Documentation

* suppression des verifications manuelles redondantes avant clone dans le guide d'installation
* clarification de l'ordre d'installation pour placer `--db-sql` avant la verification complete `setup.php`
* remplacement de l'exemple SQL statique par la generation assistee via `php scripts/setup.php --db-sql`

## [1.2.1](https://github.com/grandpurs45/my-server-manager/compare/v1.2.0...v1.2.1) (2026-06-19)

### Bug Fixes

* ajout d'actions recommandees dans `scripts/check-prerequisites.php` et `scripts/setup.php` pour guider les corrections apres WARN/FAIL

### Documentation

* simplification du prerequis RAM sans exposer le seuil interne du script
* clarification de l'etape `.env` / creation base pour guider l'utilisateur avant qu'il connaisse les identifiants MariaDB

## [1.2.0](https://github.com/grandpurs45/my-server-manager/compare/v1.1.2...v1.2.0) (2026-06-19)

### Features

* ajout de `scripts/setup.php` pour diagnostiquer l'installation, la base, les migrations, les logs et l'ordonnancement
* ajout de `scripts/update-check.php` pour valider une instance apres mise a jour
* generation d'un bloc cron adapte au chemin reel du projet et detection des anciennes redirections vers `/var/log`
* ajout des options `--init-env` et `--init-logs` pour preparer explicitement la configuration locale et les fichiers de logs
* ajout de l'option `--db-sql` pour generer les commandes SQL de creation de base et d'utilisateur
* ajout de l'option `--migrate` pour lancer explicitement les migrations depuis l'assistant setup
* ajout de l'option `--systemd` pour generer les fichiers `.service` et `.timer` systemd

### Bug Fixes

* abaissement du seuil memoire du script de prerequis pour accepter les VM 1 Go qui remontent environ 960 Mio utilisables

### Documentation

* ajout des assistants setup / maintenance dans le README, le guide d'installation, le guide de mise a jour et la documentation d'ordonnancement
* clarification du prerequis disque comme espace libre restant sur la partition MSM
* simplification des verifications manuelles au profit des scripts `check-prerequisites.php` et `setup.php`

## [1.1.2](https://github.com/grandpurs45/my-server-manager/compare/v1.1.1...v1.1.2) (2026-06-16)

### Bug Fixes

* conservation correcte de la valeur `0` pour desactiver l'expiration de session
* alignement de la conservation PHP des sessions sur la duree configuree dans MSM

### Documentation

* clarification des frequences cron, des intervalles internes et des permissions de logs

### Security

* mise a jour de `phpseclib/phpseclib` de `3.0.53` vers `3.0.55`

## [1.1.1](https://github.com/grandpurs45/my-server-manager/compare/v1.1.0...v1.1.1) (2026-06-12)

### Bug Fixes

* mise en evidence du bandeau de changement de mot de passe obligatoire
* ajout du suivi d'execution des scripts planifies dans la fraicheur des checks

## [1.1.0](https://github.com/grandpurs45/my-server-manager/compare/v1.0.0...v1.1.0) (2026-06-12)

### Features

* ajout d'une authentification locale en base avec compte administrateur initial
* ajout de la gestion des utilisateurs, droits modulaires et politique de mots de passe
* ajout d'un parametre de duree de session avec `0` pour desactiver l'expiration
* ajout d'une recherche et du tri dans la liste des utilisateurs

### Bug Fixes

* suppression automatique de l'avertissement de mot de passe initial apres changement du mot de passe courant

### Changed

* interface utilisateurs compactee avec formulaire de creation replie et gestion par ligne

## [1.0.0](https://github.com/grandpurs45/my-server-manager/compare/v0.34.0...v1.0.0) (2026-06-10)

### Release

* publication du premier socle stable MSM pour homelab et petite infrastructure
* stabilisation du perimetre v1 : Linux, Proxmox, inventaire, supervision, patch management, securite, alerting interne et export Prometheus
* finalisation de la documentation v1, des notes de release et de la roadmap v1.x

## [0.34.0](https://github.com/grandpurs45/my-server-manager/compare/v0.33.0...v0.34.0) (2026-06-09)

### Documentation

* clarification du README pour le perimetre v1
* ajout des notes de release v1
* ajout d'une section de validation Prometheus

### Security

* mise a jour de `phpseclib/phpseclib` de `3.0.44` vers `3.0.53`
* retrait de l'outillage npm `standard-version` et de son lockfile, uniquement utilises pour la release locale

## [0.33.0](https://github.com/grandpurs45/my-server-manager/compare/v0.32.0...v0.33.0) (2026-06-09)

### Documentation

* ajout des notes de compatibilite v1
* clarification de la roadmap v1.x pour le setup assiste et les refresh cibles


### Chores

* suppression de fichiers de test et d'une ancienne API non utilisee

## [0.32.0](https://github.com/grandpurs45/my-server-manager/compare/v0.31.0...v0.32.0) (2026-06-09)


### Features

* clean up security pages for v1 ([5f1c4e3](https://github.com/grandpurs45/my-server-manager/commit/5f1c4e3d10cd1a18a72ea289c4d856220117b10a))
* refonte de la page securite operationnelle pour la v1
* nettoyage de la fiche detail securite autour des ports ouverts, de l'exposition reseau et du pare-feu


### Documentation

* mise a jour du README et de la roadmap pour la page securite v1

## [0.31.0](https://github.com/grandpurs45/my-server-manager/compare/v0.30.0...v0.31.0) (2026-06-09)


### Features

* harmonize operational status badges ([33932c3](https://github.com/grandpurs45/my-server-manager/commit/33932c3cbbf4e2e2efe45e4606379da5eb5be240))
* ajout d'un helper commun pour les badges de statut operationnels
* harmonisation des statuts Patch Management, cycle de vie OS et securite


### Documentation

* mise a jour du README et de la roadmap pour les etats homogenes

## [0.30.0](https://github.com/grandpurs45/my-server-manager/compare/v0.29.0...v0.30.0) (2026-06-09)


### Features

* add minimal check history ([fdfc742](https://github.com/grandpurs45/my-server-manager/commit/fdfc74276684dfaeb452f69b01308ac1c0909bf2))
* ajout d'un historique minimal des changements de statut ping et SSH
* affichage des derniers changements supervision dans la fiche cible


### Documentation

* mise a jour du README et de la roadmap pour l'historique minimal des checks

## [0.29.0](https://github.com/grandpurs45/my-server-manager/compare/v0.28.0...v0.29.0) (2026-06-09)


### Features

* improve supervision reliability ([3eca00e](https://github.com/grandpurs45/my-server-manager/commit/3eca00eb2b74a3edb1c4d2141e00f30879d6d1fb))
* refonte de la page supervision avec statuts ping, SSH, modules actifs et fraicheur explicite
* correction du refresh manuel de supervision pour retester aussi SSH


### Documentation

* mise a jour du README et de la roadmap pour la supervision fiable

## [0.28.0](https://github.com/grandpurs45/my-server-manager/compare/v0.27.0...v0.28.0) (2026-06-09)


### Features

* improve alerting freshness diagnostics ([0a4b619](https://github.com/grandpurs45/my-server-manager/commit/0a4b6197f102fe329a2758b11b356102a5dd90f7))
* ajout de la fraicheur du check Alerting sur le dashboard d'exploitation
* enrichissement du diagnostic SSH avec le ping exact utilise par MSM


### Documentation

* documentation du diagnostic SSH et ajout d'un outil de diagnostic avance dans la roadmap v1.x

## [0.27.0](https://github.com/grandpurs45/my-server-manager/compare/v0.26.0...v0.27.0) (2026-06-09)


### Features

* improve server module visibility ([94e96a3](https://github.com/grandpurs45/my-server-manager/commit/94e96a3db651d55fb51520d499ca065affbbf574))
* ajout d'indicateurs Patch Management et Securite dans la liste des serveurs


### Documentation

* ajout d'un guide de mise a jour `docs/UPDATE.md`
* mise a jour du README, du guide d'installation et de la roadmap

## [0.26.0](https://github.com/grandpurs45/my-server-manager/compare/v0.25.0...v0.26.0) (2026-06-08)


### Features

* add alert rules management ([b96b87e](https://github.com/grandpurs45/my-server-manager/commit/b96b87edc884f583721a31f859c52771842d385a))
* ajout d'une page minimale de gestion des regles d'alertes

## [0.25.0](https://github.com/grandpurs45/my-server-manager/compare/v0.24.0...v0.25.0) (2026-06-08)


### Features

* add database-backed alerting ([9a4fb9a](https://github.com/grandpurs45/my-server-manager/commit/9a4fb9a3c258edbf7db956ae53fe227efd615b22))
* ajout du socle alerting avec stockage en base, script planifie et mur d'alertes base sur les alertes actives
* ajout d'une vue backoffice des alertes avec filtres simples
* ajout des metriques Prometheus `msm_alerts_active` et `msm_alert_active`
* ajout de l'option `--force` au script de supervision `check-servers.php`

## [0.24.0](https://github.com/grandpurs45/my-server-manager/compare/v0.23.0...v0.24.0) (2026-06-08)


### Features

* expose security metrics on dashboard ([45e054d](https://github.com/grandpurs45/my-server-manager/commit/45e054dfe0800fbcb8cf2561d34eca6b6eb03cb0))
* ajout des metriques Prometheus du module securite
* ajout des signaux securite au dashboard d'exploitation

## [0.23.0](https://github.com/grandpurs45/my-server-manager/compare/v0.22.0...v0.23.0) (2026-06-08)


### Features

* add scheduled security checks ([051106e](https://github.com/grandpurs45/my-server-manager/commit/051106e305d84814f858e934b8bdf08b198bd564))
* ajout d'une planification interne configurable pour les checks de cycle de vie OS
* ajout de l'option `--force` aux scripts Patch Management et Cycle de vie OS
* remplacement de la page d'accueil par un dashboard d'exploitation avec priorites et fraicheur des checks
* ajout d'un script `check-security.php` et du stockage des controles securite en base


### Documentation

* ajout d'une documentation dediee a l'ordonnancement cron et systemd timer des checks MSM


### Bug Fixes

* distinction du collecteur Proxmox via `proxmox_apt` dans les resultats Patch Management
* ajout des anciennes LTS Ubuntu au referentiel de cycle de vie pour identifier les OS obsoletes

## [0.22.0](https://github.com/grandpurs45/my-server-manager/compare/v0.21.0...v0.22.0) (2026-06-07)


### Features

* expose patch operations metrics ([cff2dc6](https://github.com/grandpurs45/my-server-manager/commit/cff2dc6d269610d7c479326154c18cee7a2dc208))

## [0.21.0](https://github.com/grandpurs45/my-server-manager/compare/v0.20.0...v0.21.0) (2026-06-07)


### Features

* add OS lifecycle checks ([aadbf96](https://github.com/grandpurs45/my-server-manager/commit/aadbf963f210cfcdaf9bdfa5d9fe84f9f3e1d495))


### Bug Fixes

* use Rocky Linux inventory logo ([8c37131](https://github.com/grandpurs45/my-server-manager/commit/8c37131774532e7057b17acedddaa5c3350eac33))

## [0.20.0](https://github.com/grandpurs45/my-server-manager/compare/v0.19.0...v0.20.0) (2026-06-07)

### Features

* affichage du detail des paquets Patch Management par cible

## [0.19.0](https://github.com/grandpurs45/my-server-manager/compare/v0.18.0...v0.19.0) (2026-06-06)

### Features

* ajout d'une planification interne configurable pour les checks Patch Management

## [0.18.0](https://github.com/grandpurs45/my-server-manager/compare/v0.17.0...v0.18.0) (2026-06-05)

### Features

* ajout du socle Patch Management : activation par cible, tables de resultats, classes de lecture et page de synthese
* ajout du premier collecteur Patch Management Linux/Proxmox via SSH et `apt`
* ajout du collecteur Patch Management `dnf` pour Rocky Linux et distributions RHEL-like
* mise en evidence visuelle des reboots requis dans le Patch Management
* ajout de la tracabilite du collecteur utilise dans les resultats Patch Management
* ajout d'une vue des collecteurs Patch Management disponibles et prevus

## [0.17.0](https://github.com/grandpurs45/my-server-manager/compare/v0.16.0...v0.17.0) (2026-06-04)

### Features

* ajout de filtres inventaire sur la liste des serveurs : type, environnement, criticite, statut et tag
* ajout d'une page detail cible avec synthese inventaire, supervision, metriques recentes et labels Prometheus
* ajout d'une option par cible pour activer ou exclure l'analyse securite serveur

## [0.16.0](https://github.com/grandpurs45/my-server-manager/compare/v0.15.0...v0.16.0) (2026-06-04)

### Features

* ajout des metriques Prometheus disque, timestamp du dernier check et succes du dernier check
* ajout des champs d'inventaire serveur : type, environnement, criticite, tags et methode de collecte
* ajout du label Prometheus `type` depuis l'inventaire MSM
* ajout d'options parametables pour les types de cibles, environnements, criticites et methodes de collecte
* ajout d'un champ tags avec rendu en badges
* ajout d'un fallback d'icone Linux generique pour les distributions sans icone dediee

### Bug Fixes

* correction du parsing de latence ping sur les environnements francises
* classement des serveurs existants en `other` par defaut lors de la migration inventaire
* affichage des parametres declares dans le schema meme lorsqu'ils ne sont pas encore enregistres en base
* nettoyage de l'ancien parametre reseau `Suffixe DNS` pour eviter les doublons

### Documentation

* ajout d'une verification concrete de `/metrics.php` dans la procedure post-install
* ajout d'une documentation Prometheus/Grafana avec exemple `prometheus.yml` et requetes PromQL
* clarification du report du label Prometheus `type` a la phase Inventaire

## [0.15.0](https://github.com/grandpurs45/my-server-manager/compare/v0.14.2...v0.15.0) (2026-05-22)

### Features

* ajout d'un chargement de configuration locale via `.env`
* externalisation de la configuration MariaDB et de la cle de chiffrement
* ajout d'une page diagnostic systeme
* ajout d'un script CLI de verification des prerequis d'installation
* verification du statut du service Apache dans le script de prerequis
* verification de `exec()` et `ping` pour les checks de disponibilite

### Bug Fixes

* correction des liens, formulaires et assets quand MSM est installe dans un sous-dossier comme `/msm/`
* suppression de wrappers `<main>` imbriques qui fragilisaient la mise en page
* protection de l'appel JavaScript `lucide.createIcons()` si la librairie externe ne charge pas

### Security

* retrait des fichiers de cle du suivi Git
* validation et echappement des hotes avant execution des commandes `ping` et `ssh`
* ajout d'une protection CSRF sur les formulaires critiques

### Documentation

* retrait des exemples lies a une configuration personnelle dans la procedure d'installation
* ajout d'un avertissement sur la configuration pare-feu du serveur MSM
* ajout d'une roadmap projet vers la v1
* ajout d'un exemple `.env.example`
* ajout d'une procedure d'installation vierge
* ajout des prerequis materiels et des modes d'installation possibles
* ajout des commandes d'installation des dependances systeme Linux
* clarification de l'installation de Composer sur Rocky/RHEL quand le paquet `composer` est absent
* documentation du cas `mariadb.service does not exist` sur Rocky/RHEL
* documentation de l'installation Composer depuis `/tmp` en cas de dossier projet non inscriptible
* detail de la creation MariaDB avec entree dans le client SQL et test de connexion
* documentation des erreurs Git `dubious ownership` et Composer `vendor` non creatable
* verification et documentation de `php-zip` et `unzip` pour Composer
* detail de la configuration cron pour le check planifie
* ajout des commandes de verification des prerequis dans la procedure d'installation
* documentation des erreurs frequentes du script de verification des prerequis
* correction des textes visibles encodes de travers dans les pages principales

### [0.14.2](https://github.com/grandpurs45/my-server-manager/compare/v0.14.1...v0.14.2) (2026-05-22)

### Bug Fixes

* calcul de l'age du dernier check cote MariaDB pour eviter les decalages de timezone PHP en production

### [0.14.1](https://github.com/grandpurs45/my-server-manager/compare/v0.14.0...v0.14.1) (2026-05-22)

### Bug Fixes

* retrait de la regle de rewrite `/metrics` dans `.htaccess` pour eviter une erreur 500 si `mod_rewrite` est absent

## [0.14.0](https://github.com/grandpurs45/my-server-manager/compare/v0.13.0...v0.14.0) (2026-05-22)

### Features

* ajout d'un endpoint Prometheus `/metrics` exposant les derniers statuts connus des serveurs
* ajout de la classe `MSM\PrometheusExporter` pour isoler le formatage des metriques Prometheus

### Bug Fixes

* correction du statut insere a la creation d'un serveur (`up` / `down`) pour rester coherent avec le schema SQL
* fiabilisation des scripts de migration pour initialiser automatiquement `migrations_applied`
* correction des migrations initiales pour permettre une installation sur base neuve
* correction de l'affichage du dernier check dans la page supervision lorsque la date est future ou decalee

## [0.13.0](https://github.com/grandpurs45/my-server-manager/compare/v0.12.0...v0.13.0) (2025-12-21)


### Features

* ajout d'un manifest.json pour automatiser le plein ecrans sur une tablette  du mur d'alerte ([b822331](https://github.com/grandpurs45/my-server-manager/commit/b822331f4190347e856951d301a3774ce7494047))
* ajout de la page mur d'alerte pour affichage sur un écran dédié ([0b99537](https://github.com/grandpurs45/my-server-manager/commit/0b995375a3b223a579b45f8717fbb31ae5574433))
* ajout des logos pour le mur PWA, et ajout d'un splashscreen ([39125d6](https://github.com/grandpurs45/my-server-manager/commit/39125d6d6584c551e17f2de7eac53ce2b9045355))
* **alerts:** mutualisation de la logique d'alertes via alerts_helper + ajout page standalone (mur d'alertes iPad) ([a892dfb](https://github.com/grandpurs45/my-server-manager/commit/a892dfbd95d634be2acb19470dc5d9fcb339fda2))


### Bug Fixes

* correction des balise META necessaire au PWA directement dans la page du mur d'alerte standalone ([c285685](https://github.com/grandpurs45/my-server-manager/commit/c285685f4c5f80fde7f0023f15ef252c4748d405))
* correction des balise META pour que le PWA fonctionne pour les materiels Apple ([b460f45](https://github.com/grandpurs45/my-server-manager/commit/b460f45e10e92f579fe0478cf08a6d0cb82f0e85))
* corrections des image pour le splash screen et ajout des deux format pour le paysage et le portrait ([db4fb69](https://github.com/grandpurs45/my-server-manager/commit/db4fb69c9ae823df483e1138e36c035c6b5dc1e4))
* inversion image splash screen portrait et paysage ([b60a745](https://github.com/grandpurs45/my-server-manager/commit/b60a745c50f3120d49814a6fa9db91afa810f019))

## [0.12.0](https://github.com/grandpurs45/my-server-manager/compare/v0.11.3...v0.12.0) (2025-12-06)


### Features

* ajout des pages securité serveurs et securité web ([8f0da78](https://github.com/grandpurs45/my-server-manager/commit/8f0da7800083ba0da086eaf3cd301ed26dc64ad4))
* mise à jour script check-servers + ajouts sécurité (SecurityAudit, détails sécurité) ([e7c7a2f](https://github.com/grandpurs45/my-server-manager/commit/e7c7a2f4acab42dba51a4e3da295c401b0540f25))


### Bug Fixes

* rendre decrypt() tolérant aux anciens mots de passe (IV / base64) ([0b1e549](https://github.com/grandpurs45/my-server-manager/commit/0b1e549f543dc3320ec9cf660288f22c2e670f0d))

### [0.11.3](https://github.com/grandpurs45/my-server-manager/compare/v0.11.2...v0.11.3) (2025-07-08)


### Features

* **supervision:** ajout du disque du sur la supervision ([2f1fecf](https://github.com/grandpurs45/my-server-manager/commit/2f1fecf831e47e76eb0c031b908bd226bebd0ef8))


### Bug Fixes

* correction connexion ssh ne fonctionnant jamais a la creation du serveur ([232e165](https://github.com/grandpurs45/my-server-manager/commit/232e165a8bf30a2eb2f1ac70f2b4694aa720a4d0))

### [0.11.2](https://github.com/grandpurs45/my-server-manager/compare/v0.11.1...v0.11.2) (2025-07-06)


### Bug Fixes

* actualisation serveur ne mettais pas a jour le ping si SSH désactivé ([0e1ecc1](https://github.com/grandpurs45/my-server-manager/commit/0e1ecc14f09cdb6736599164cace0ef6c70145da))

### [0.11.2](https://github.com/grandpurs45/my-server-manager/compare/v0.11.1...v0.11.2) (2025-07-06)


### Bug Fixes

* actualisation serveur ne mettais pas a jour le ping si SSH désactivé ([0e1ecc1](https://github.com/grandpurs45/my-server-manager/commit/0e1ecc14f09cdb6736599164cace0ef6c70145da))

### [0.11.1](https://github.com/grandpurs45/my-server-manager/compare/v0.11.0...v0.11.1) (2025-07-06)


### Bug Fixes

* actualisation serveur dans supervision ne fonctionnait plus ([c3e9701](https://github.com/grandpurs45/my-server-manager/commit/c3e970149a3096c1faafa00517ff07ff768961ed))

## [0.11.0](https://github.com/grandpurs45/my-server-manager/compare/v0.10.0...v0.11.0) (2025-07-06)


### Features

* **serveurs:** ajout case a cocher pour ne pas faire de ssh sur un serveur + status ssh désactivé ([dbf46a7](https://github.com/grandpurs45/my-server-manager/commit/dbf46a75ce9c783f37699ff80957658442a213fb))
* **serveurs:** ajout du ping a la creation du serveur ([60f2194](https://github.com/grandpurs45/my-server-manager/commit/60f2194bbaf83baad0eeddef776651608978adcf))

## [0.10.0](https://github.com/grandpurs45/my-server-manager/compare/v0.9.0...v0.10.0) (2025-07-05)


### Features

* CHiffrement AES des mots de passe en base de données ([c378d41](https://github.com/grandpurs45/my-server-manager/commit/c378d41a49190de080babdfdd97ddf6c5ad8d12c))
- 🔐 Ajout du chiffrement AES des mots de passe SSH (via fichier .key local)
- ✅ Génération d'une clé `msm_secret.key` à l'installation (à prévoir)
- 📝 Champ `ssh_password` ignoré si vide en modification
- 🛠️ Migration base : ajout de la colonne `ssh_port`
- 📥 Prise en compte du port SSH personnalisé à l’ajout et modification
- ✅ Gestion de la persistance du mot de passe chiffré
- 🛠️ Détection d’OS toujours active lors des modifications
- 💬 Amélioration des messages (succès/erreur) en fonction des cas

## [0.9.0](https://github.com/grandpurs45/my-server-manager/compare/v0.8.0...v0.9.0) (2025-07-04)


### Features

* ajout readme ([40592c9](https://github.com/grandpurs45/my-server-manager/commit/40592c9cd8f8fdccfa80001fa3c77b60468728d6))
* **supervision:** creation de la page de supervision, affichage en mode card ([c47581f](https://github.com/grandpurs45/my-server-manager/commit/c47581f16b363b58a9dae480836fd7a4c2182cec))
- Supervision des serveurs en temps réel (ping)
- Enregistrement de la latence et du statut UP/DOWN en base
- Table `server_metrics` pour historiser les mesures
- Bouton manuel de mise à jour des statuts
- Affichage des dernières latences dans les cartes
- Code de ping compatible Windows/Linux avec parsing amélioré

## [0.8.0](https://github.com/grandpurs45/my-server-manager/compare/v0.6.1...v0.8.0) (2025-07-03)


### Features

* Ajout du mode debug (affiche les erreurs php) ([eccf3ca](https://github.com/grandpurs45/my-server-manager/commit/eccf3ca6bf273a074ca037c25d45e7b7e823ae4f))
* ajout fonctione debug dans les paramètres ([90667ee](https://github.com/grandpurs45/my-server-manager/commit/90667ee7409f76bba59cb8157aa7f714a3d89352))
* creation de la page settings et de la classe SettingsManager ([ebaf13c](https://github.com/grandpurs45/my-server-manager/commit/ebaf13cc9a8f843646393d559984db4c24513dd1))
* Détection automatique de l’OS lors de l’ajout/modification d’un serveur ([2ad5d34](https://github.com/grandpurs45/my-server-manager/commit/2ad5d34e2f1fcb9cb06d26aec9c5c767f99e4713))


### Bug Fixes

* ajout colonne status dans bdd qui manquait en prod ([ab43156](https://github.com/grandpurs45/my-server-manager/commit/ab4315654bbee677c199c01bd18e6fedeb41f91f))

### [0.7.3](https://github.com/grandpurs45/my-server-manager/compare/v0.6.1...v0.7.3) (2025-06-30)


### Features

* Détection automatique de l’OS lors de l’ajout/modification d’un serveur ([9aca3eb](https://github.com/grandpurs45/my-server-manager/commit/9aca3eb64656951458684b3ca5571e837007b0ee))
- Détection automatique de l'OS à l'ajout/modification d'un serveur :
  - Linux : via `/etc/os-release`
  - Windows : via PowerShell `(Get-CimInstance Win32_OperatingSystem).Caption`
  - Fallback : commande `ver` pour les cas minimaux
- Affichage du nom complet de l’OS détecté
- Ajout automatique du logo OS (Debian, Ubuntu, Windows, Unknown, etc.)
- Gestion des connexions SSH via phpseclib3 avec timeout et logs
- Création d’un fichier `ssh-debug.log` dans `/logs/` pour le débogage SSH
- Affichage combiné possible des messages `success` et `error` à l’écran
- Ping initial et statut SSH mis à jour automatiquement à la modification

### Modifié
- Suppression du fichier `edit-server.php` (fusionné dans `serveurs.php`)
- `functions.php` : fonction `isHostUp()` plus robuste et compatible Windows/Linux
- Amélioration de l’apparence visuelle (logos, icônes statuts, messages utilisateurs)
- Refactorisation de la classe `SSHUtils` avec timeout, logs, fallback

### Corrigé
- [#0002] Formulaire non réinitialisé après modification
- Chargement infini de la page lié au div `#loading`
- Echec silencieux de `phpseclib` en cas de nom d’hôte non résolu (`.lan`)

### À venir
- Ping initial à l’ajout du serveur (non encore automatisé)
- Bouton manuel "Mettre à jour le statut"
- Suffixe DNS personnalisable dans les paramètres
- Blocage des doublons lors de l’ajout (hostname/nom identique)


### Bug Fixes

* ajout colonne status dans bdd qui manquait en prod ([ab43156](https://github.com/grandpurs45/my-server-manager/commit/ab4315654bbee677c199c01bd18e6fedeb41f91f))

### [0.6.1](https://github.com/grandpurs45/my-server-manager/compare/v0.6.0...v0.6.1) (2025-06-23)


### Bug Fixes

* correction du retour du ping sur windows ([d8875fd](https://github.com/grandpurs45/my-server-manager/commit/d8875fd1582b7bfe29b2e271ec00f314e7c08d15))

## [0.6.0](https://github.com/grandpurs45/my-server-manager/compare/v0.5.2...v0.6.0) (2025-06-23)


### Features

* transition vers stockage BDD des statuts et amélioration UX ([248b6ab](https://github.com/grandpurs45/my-server-manager/commit/248b6ab6e60c9e1694987a124894a91c95283831))

### [0.5.2](https://github.com/grandpurs45/my-server-manager/compare/v0.5.1...v0.5.2) (2025-06-23)


### Bug Fixes

* sauvegarde des données en cas de modifications BUG-003 ([844a9f7](https://github.com/grandpurs45/my-server-manager/commit/844a9f76ef4fd84de7787254c37c748fab524690))

### [0.5.1](https://github.com/grandpurs45/my-server-manager/compare/v0.5.0...v0.5.1) (2025-06-23)


### Features

* ajout de composer et de phpseclib ([afa55e4](https://github.com/grandpurs45/my-server-manager/commit/afa55e4fd73e28b3ce677535244791645f8008f5))
* ajout du support SSH et refonte formulaire serveur ([8985988](https://github.com/grandpurs45/my-server-manager/commit/8985988f1dad65eea09a85e4e8ce19eacd53c3e5))
* refonte du code pour passer en POO ([f2548ba](https://github.com/grandpurs45/my-server-manager/commit/f2548ba58b6c63a63bf582d8ba27d4b6e681d043))


### Bug Fixes

* reset du formulaire d'ajout après modification BUG-002 ([a778b88](https://github.com/grandpurs45/my-server-manager/commit/a778b8850d9f1b538e8fc79eb90394b2c9532a9d))

## [0.5.0](https://github.com/grandpurs45/my-server-manager/compare/v0.4.1...v0.5.0) (2025-06-22)


### Features

* amelioration du ping qui ne se fait plus au chargement de la page mais après ([b02347d](https://github.com/grandpurs45/my-server-manager/commit/b02347de153a09d4eba33d1ee9d6a02686186862))
* amelioration du spinner ([d85939d](https://github.com/grandpurs45/my-server-manager/commit/d85939d2c37a3c90bbe6c432e33d97861d2cd15a))
* ✨ Ajout du support SSH à l’ajout de serveur
* 🧠 Refonte partielle vers une structure orientée objet
* 🖼️ Modale d’ajout améliorée (design Tailwind + édition dynamique)
* 🗂️ Démarrage du registre de bugs connus

### Bug Fixes

* 🪲 Correctifs divers (nom/IP vides, statut modale en édition…)


### [0.4.1](https://github.com/grandpurs45/my-server-manager/compare/v0.4.0...v0.4.1) (2025-06-22)


### Features

* **migrations:** création de la table servers ([948c612](https://github.com/grandpurs45/my-server-manager/commit/948c6120b12fb02ae45409b0fbbc5e9fe5f1e3d5))


### Bug Fixes

* bug chargement serveur en production ([a2de778](https://github.com/grandpurs45/my-server-manager/commit/a2de778ea75389731eeb0c7f6adc158c3eeacc7d))
* correction du bug lorsqu'aucun serveur n'existe ([aae6a7f](https://github.com/grandpurs45/my-server-manager/commit/aae6a7fa710e8e8076d1ef104b93e4e263a1e6ab))
* correction du bug lorsqu'aucun serveur n'existe ([8d9c030](https://github.com/grandpurs45/my-server-manager/commit/8d9c0307225ef99adfa8c7ebc742207e336c2710))
* correction du bug lorsqu'aucun serveur n'existe ([b1e2d99](https://github.com/grandpurs45/my-server-manager/commit/b1e2d992f130ec7b04e1fc58565565c21bfaa2a6))
* correction du bug lorsqu'aucun serveur n'existe ([c9a9d1b](https://github.com/grandpurs45/my-server-manager/commit/c9a9d1b16a9b7b9c7ba468a6f90af273d46f4a15))
* rename tmp_migrations to migrations (lowercase) ([e4da177](https://github.com/grandpurs45/my-server-manager/commit/e4da177ade53aa89aea021e8051ee0d89d520573))
* renommage repertoire Migrations vers migrations pour la cohérence ([24f9bd1](https://github.com/grandpurs45/my-server-manager/commit/24f9bd17f6a5a7a934acc9f2cad4499aecae7c12))

## [0.4.0](https://github.com/grandpurs45/my-server-manager/compare/v0.3.0...v0.4.0) (2025-06-22)


### Features

* ajout lien serveur dans le menu de gauche ([c3f7365](https://github.com/grandpurs45/my-server-manager/commit/c3f736568b7d014f74dbb9ed4b7834a659eb21d7))
* processus de migration des modifs de bases de données ([7669d25](https://github.com/grandpurs45/my-server-manager/commit/7669d25979dcd4aaa8235bc8a436ec6bd44f5250))

## [0.3.0](https://github.com/grandpurs45/my-server-manager/compare/v0.2.3...v0.3.0) (2025-06-22)

### [0.2.3](https://github.com/grandpurs45/my-server-manager/compare/v0.2.2...v0.2.3) (2025-06-22)


### Features

* supervision réseau (UP/DOWN) + spinner visuel ([fea514b](https://github.com/grandpurs45/my-server-manager/commit/fea514bfe2a3bce7c10f5e02fd81f487e1ce35e5))

### [0.2.2](https://github.com/grandpurs45/my-server-manager/compare/v0.2.1...v0.2.2) (2025-06-22)


### Features

* boutton annulation sur le modal d'ajout et de modification du serveur ([c1a4839](https://github.com/grandpurs45/my-server-manager/commit/c1a4839ec47beac55070bb64c0a5c685f66ec5b2))
* modification d'un serveur ([edd6f26](https://github.com/grandpurs45/my-server-manager/commit/edd6f26c018618845f56826a72f6d08693cea138))


### Bug Fixes

* correction lors de l'annulation d'une modification de serveur qui laissait sur l'url de modification du serveur ([bdb218a](https://github.com/grandpurs45/my-server-manager/commit/bdb218a910362df7b629dd4b7477ee82be6cd58b))

### [0.2.1](https://github.com/grandpurs45/my-server-manager/compare/v0.1.0...v0.2.1) (2025-06-22)


### Features

* affichage des serveurs depuis la base de données ([3c27126](https://github.com/grandpurs45/my-server-manager/commit/3c27126d4532a8c603aa89721f0dd7a96c1cc4a9))


## [0.2.0] - 2025-06-21
### Ajouté
- Page de gestion dynamique des serveurs (affichage + ajout)
- Connexion à la base de données via PDO
- Détection et affichage de l’état `UP`
- Design et intégration avec Tailwind

## [0.1.0] - 2025-06-19
### Added
- Initialisation du projet, configuration Apache et Git
