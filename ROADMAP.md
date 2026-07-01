# Roadmap MSM

My Server Manager est un outil d'exploitation pour homelab et petite infrastructure. MSM ne remplace pas Grafana ou Prometheus : il collecte, analyse, stocke et expose des donnees metier utiles a l'exploitation.

## Vision

- Grafana : affichage, dashboards, alertes visuelles.
- Prometheus : collecte des metriques.
- MSM : inventaire, supervision legere, patch management, securite operationnelle, alerting interne et export Prometheus.

## Etat Actuel

Version actuelle : `v1.10.0`.

Socle valide :

- installation vierge rejouee et corrigee jusqu'a l'ajout d'une premiere cible ;
- guide d'installation, guide de mise a jour et assistant CLI disponibles ;
- authentification locale avec utilisateurs, droits modules et politique de mots de passe ;
- inventaire configurable des cibles ;
- profil materiel des cibles : physique, machine virtuelle, conteneur, appliance ou inconnu ;
- supervision ping / SSH / latence / disque avec refresh cible ;
- patch management Linux, Proxmox, `apt` et `dnf` ;
- cycle de vie OS avec support / obsolescence / upgrade calcule, referentiel administrable et synchronisation endoflife.date ;
- securite operationnelle de base ;
- alerting interne avec regles globales, mur d'alertes, vue backoffice et traitement manuel ;
- sante materielle Linux/Proxmox avec temperatures, SMART, dashboard, alertes et export Prometheus ;
- connecteur Home Assistant SSH avec versions Core, Supervisor, OS, statut d'update et export Prometheus ;
- logos OS extensibles avec upload manuel et recuperation automatique depuis une source connue ;
- page Collecteurs / Checks pour controler scripts, logs, intervalles internes et lignes cron attendues ;
- export Prometheus stable ;
- titre d'onglet navigateur personnalisable par environnement.

## Priorite v1.11 - Collecteurs et Maintenance

Objectif : rendre les collecteurs administrables et reduire encore le diagnostic manuel en production.

- Gestion des collecteurs depuis l'interface :
  - activer ou desactiver un collecteur globalement ;
  - visualiser les familles supportees ;
  - afficher les scripts, logs, intervalles et commandes de test ;
  - preparer l'activation / desactivation par hote et par item.
- Diagnostic SSH integre :
  - test DNS ;
  - test TCP port SSH ;
  - test authentification ;
  - commande simple type `whoami` / `uname` ;
  - messages reformules sans exposer les secrets.
- Setup web / installation assistee :
  - assistant navigateur pour premiere configuration ;
  - verification `.env`, base, migrations et droits ;
  - aide a la creation des cron avec confirmation explicite ;
  - aucun secret affiche en clair.

## Backlog v1.x

### Collecteurs

- Synology DSM.
- Windows via PowerShell SSH ou WinRM.
- Home Assistant avance :
  - statut des services essentiels ;
  - redemarrage requis ;
  - stockage et sante simple ;
  - alertes dediees Home Assistant.
- Docker via SSH sur l'hote Docker : containers, images, statuts, ports exposes.
- Proxmox avance : discovery, VMs, conteneurs, stockage.

### Alerting et Notifications

- Parametrage avance des regles : severite, seuils, delais.
- Desactivation par hote, module ou item precis.
- Overrides d'alerting par cible :
  - activer ou desactiver une regle pour une cible precise ;
  - definir un seuil specifique par cible quand la regle le supporte ;
  - afficher l'heritage entre regle globale et exception cible ;
  - resoudre automatiquement les alertes actives quand une regle est desactivee pour une cible.
- Silences et fenetres de maintenance.
- Notifications sortantes : email, webhook, Discord ou autre canal.
- Historique des silences, desactivations et notifications.

### Inventaire et UX

- Fiche cible par onglets : resume, inventaire, supervision, patch management, cycle de vie OS, securite, historique.
- Diagnostic par cible : DNS, TCP, SSH, ping, causes probables sans exposer les secrets.
- Retours d'action plus utiles : messages visibles, erreurs reformulees, liens vers diagnostic ou logs.
- Historique des changements d'inventaire.
- Colonnes personnalisables dans les listes.
- Interface parametres plus moderne.
- Interface globale plus moderne.
- Gestion des collecteurs depuis une interface : activation, desactivation, ordre, familles.

### Installation et Maintenance

- Bootstrap pre-clone capable d'installer PHP/Git puis de cloner MSM.
- Setup interactif complet.
- Creation optionnelle des cron ou timers par l'assistant, avec confirmation explicite.
- Personnalisation du compte admin initial pendant l'installation.
- Outil de diagnostic cible integre au backoffice.

### Authentification

- Vue des evenements d'authentification.
- Connecteurs externes reportes : LDAP, Active Directory, Keycloak, OIDC/OAuth2.
- Mot de passe perdu / reinitialisation autonome.
- Notification utilisateur lors de creation de compte ou changement de mot de passe.
- Analyse de mots de passe faibles ou compromis.

## Realise

### v1.10 - Cycle de Vie OS Administrable

- Page `Parametres > Cycle OS` :
  - ajout, modification et suppression des references OS ;
  - comptage des serveurs concernes par reference ;
  - filtre pour afficher uniquement les OS detectes dans le homelab ;
  - tri des colonnes OS, serveurs, fin de support, statut, upgrade, source et derniere mise a jour.
- Synchronisation optionnelle depuis `endoflife.date` :
  - familles configurables depuis l'interface ;
  - script CLI `scripts/sync-os-lifecycle.php` ;
  - stockage local en base, sans appel API au chargement des pages ou de `/metrics.php`.
- Calcul automatique de la cible d'upgrade :
  - recherche de la prochaine version supportee connue dans la meme famille OS ;
  - cible manuelle prioritaire quand elle est renseignee.
- Alerte informative pour les OS detectes sans date de fin de support connue.

### v1.9 - Collecteurs, Logos OS et Alertes Home Assistant

- Page `Collecteurs / Checks` :
  - statut OK, en cours, ancien ou erreur ;
  - derniere tentative, dernier resultat, fin d'execution et log ;
  - ligne cron attendue et bouton de copie ;
  - synthese dashboard simplifiee.
- Logos OS extensibles :
  - convention `assets/logos/os/<identifiant>.png|svg|webp` ;
  - upload manuel depuis les parametres MSM ;
  - recuperation automatique depuis une source connue avec validation du SVG.
- Alertes Home Assistant :
  - check en erreur ;
  - check trop ancien ;
  - updates Core, Supervisor et OS disponibles.
- Documentation d'installation, mise a jour et ordonnancement ajustee pour les nouveaux scripts.

### v1.8 - Connecteur Home Assistant

- Type de cible `Home Assistant`.
- Collecte SSH dediee via `scripts/check-home-assistant.php`.
- Detection des versions Core, Supervisor et Home Assistant OS quand la CLI `ha` est disponible.
- Fallback systeme Linux limite quand la CLI `ha` n'est pas exposee.
- Stockage en base des derniers resultats.
- Affichage dans la fiche cible.
- Export Prometheus `msm_home_assistant_*`.
- Documentation de l'ordonnancement cron/systemd.

### v1.7 - Mise a jour automatisee

- Assistant CLI `scripts/update.php --check` et `--apply`.
- Sauvegarde de `.env`, dump SQL, rapport d'execution et journal local.
- Composer, migrations, logs, controle post-update et relance non bloquante des checks principaux.
- Refus des fichiers Git versionnes modifies et absence de restauration destructive automatique.

### v1.6 - Sante Materielle

- Profil materiel dans l'inventaire.
- Collecte des temperatures via `lm-sensors` ou `/sys/class/thermal`.
- Collecte SMART en lecture seule avec `smartctl` sur les equipements physiques.
- Stockage des checks, sondes et disques en base.
- Affichage detaille sur la fiche cible et resumes dashboard.
- Metriques Prometheus pour temperatures, SMART, usure et erreurs media.
- Alertes configurables de temperature, SMART, usure, erreurs media et fraicheur du collecteur.

### v1.4 - Refresh Cible et Personnalisation Environnement

- Refresh cible par module depuis la fiche cible :
  - supervision ;
  - patch management ;
  - cycle de vie OS ;
  - securite.
- Titre d'onglet navigateur personnalisable depuis les parametres MSM.

### v1.3 - Notification de Nouvelle Version

- Notification de nouvelle version disponible dans l'interface.
- Cache local du resultat de verification de release GitHub.

### v1.2 - Setup et Maintenance

- Assistant CLI `scripts/setup.php`.
- Verification prerequis, dependances, base, migrations, logs et ordonnancement.
- Installation assistee des dependances systeme avec confirmation explicite.
- Aide guidee pour `.env`, SQL MariaDB et migrations.
- Choix cron vs systemd documente.
- Validation d'une installation fraiche reelle jusqu'a l'ajout d'une cible.

### v1.1 - Authentification Locale

- Authentification locale securisee.
- Compte administrateur local par defaut.
- Gestion des utilisateurs et des droits modules.
- Parametres de complexite des mots de passe.
- Generateur de mot de passe.
- Expiration configurable des sessions.
- Evenements d'authentification traces en base.

### v1.0 - Socle Produit

- Inventaire des cibles.
- Supervision legere.
- Export Prometheus.
- Patch management Linux / Proxmox.
- Cycle de vie OS initial.
- Securite operationnelle de base.
- Alerting interne v1.
- Documentation installation / mise a jour / Prometheus / compatibilite.

## Idees Futures / Hors Scope Court Terme

- Remplacer Grafana ou Prometheus.
- Supervision temps reel avancee.
- Systeme d'alerting complet avec notifications et escalades avancees.
- Gestion multi-utilisateurs avancee.
- Orchestration automatique de patchs sans validation humaine.
- Interface plus moderne.
- Interface d'administration plus moderne.
- Interface globale plus moderne.
- Modernisation des parametres d'inventaire.
- Autodiscovery Proxmox, Docker ou reseau.
- Discovery Proxmox.
- Collecteur Docker avance.
- Ajout, suppression, desactivation et ordonnancement des collecteurs.
- Setup d'installation interactif complet.
- Setup d'installation web pour guider une premiere configuration depuis le navigateur.
- Script d'installation capable de creer les cron directement avec confirmation explicite.
- Agent pour les serveurs.
- Multi-tenant ou gestion avancee d'organisations.
