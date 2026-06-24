# Roadmap MSM

My Server Manager est un outil d'exploitation pour homelab et petite infrastructure. MSM ne remplace pas Grafana ou Prometheus : il collecte, analyse, stocke et expose des donnees metier utiles a l'exploitation.

## Vision

- Grafana : affichage, dashboards, alertes visuelles.
- Prometheus : collecte des metriques.
- MSM : inventaire, supervision legere, patch management, securite operationnelle, alerting interne et export Prometheus.

## Etat Actuel

Version actuelle : `v1.7.0`.

Socle valide :

- installation vierge rejouee et corrigee jusqu'a l'ajout d'une premiere cible ;
- guide d'installation, guide de mise a jour et assistant CLI disponibles ;
- authentification locale avec utilisateurs, droits modules et politique de mots de passe ;
- inventaire configurable des cibles ;
- profil materiel des cibles : physique, machine virtuelle, conteneur, appliance ou inconnu ;
- supervision ping / SSH / latence / disque avec refresh cible ;
- patch management Linux, Proxmox, `apt` et `dnf` ;
- cycle de vie OS avec support / obsolescence / upgrade connu ;
- securite operationnelle de base ;
- alerting interne avec regles globales, mur d'alertes, vue backoffice et traitement manuel ;
- sante materielle Linux/Proxmox avec temperatures, SMART, dashboard, alertes et export Prometheus ;
- export Prometheus stable ;
- titre d'onglet navigateur personnalisable par environnement.

## Priorite v1.7 - Mise a jour automatisee

Objectif : reduire une mise a jour MSM a une commande guidee, controlee et rejouable.

- Assistant CLI `scripts/update.php` initialise :
  - mode `--check` non destructif ;
  - controle du depot, de la version, des fichiers locaux, du systeme, de la base et des sauvegardes ;
  - affichage du plan execute par le mode `--apply`.
- Mode d'application controle `scripts/update.php --apply --target=vX.Y.Z` :
  - cible obligatoire et confirmation explicite ;
  - sauvegarde de `.env`, de la base et du contexte d'execution avant modification ;
  - recuperation du tag, Composer, migrations, logs et controle post-update ;
  - journal local et instructions de retour au code precedent en cas d'echec.
- Verifier avant modification :
  - dossier MSM et depot Git valides ;
  - branche, version et fichiers locaux modifies ;
  - prerequis, espace disque et connexion base ;
  - version cible disponible.
- Creer automatiquement avant mise a jour :
  - sauvegarde MariaDB/MySQL ;
  - sauvegarde de `.env` ;
  - rapport de version et d'ordonnancement courant.
- Appliquer avec validation explicite :
  - recuperation du code ou du tag cible ;
  - `composer install --no-dev --optimize-autoloader` ;
  - migrations ;
  - initialisation des nouveaux fichiers de logs ;
  - proposition d'ajout des nouveaux cron ou timers systemd.
- Relancer les checks principaux et `scripts/update-check.php`.
- Afficher un bilan final clair : OK, WARN, FAIL et actions restantes.
- Conserver un journal local de la mise a jour.
- Fournir une commande de rollback guidee sans executer de restauration destructive automatiquement.
- Prevoir des modes :
  - `--check` pour simuler et afficher les actions ;
  - `--apply` pour lancer la mise a jour ;
  - `--target=vX.Y.Z` pour cibler une release ;
  - `--yes` uniquement pour les confirmations non sensibles.
- Refuser la mise a jour automatique si des fichiers Git versionnes sont modifies localement.
- Ne jamais ecraser `.env`, restaurer une base ou modifier les droits sudo sans confirmation explicite.

## Priorite v1.5 - Traitement Manuel des Alertes

Objectif : rendre le backoffice alertes exploitable au quotidien sans partir sur un systeme de notification complet.

- Acquitter une alerte active.
- Retirer un acquittement.
- Ignorer une alerte active.
- Reactiver une alerte ignoree.
- Conserver les actions dans l'historique `alert_events`.
- Afficher l'historique recent d'une alerte depuis la liste backoffice.
- Sortir les alertes acquittees ou ignorees du mur d'alertes, du dashboard et des metriques actives.
- Garder les notifications sortantes, silences et fenetres de maintenance pour v1.x.

## Backlog v1.x

### Collecteurs

- Synology DSM.
- Windows via PowerShell SSH ou WinRM.
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

### OS Lifecycle

- Referentiel OS administrable depuis les parametres.
- Synchronisation optionnelle depuis `endoflife.date` :
  - script CLI dedie ;
  - cache local en base ;
  - aucune dependance API au chargement des pages ou de `/metrics.php` ;
  - conservation de la source et de la date de synchronisation.

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
