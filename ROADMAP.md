# Roadmap MSM

My Server Manager est un outil d'exploitation pour homelab et petite infrastructure. MSM ne remplace pas Grafana ou Prometheus : il collecte, analyse, stocke et expose des donnees metier utiles a l'exploitation.

## Vision

- Grafana : affichage, dashboards, alertes visuelles.
- Prometheus : collecte des metriques.
- MSM : inventaire, supervision legere, patch management, securite operationnelle, alerting interne et export Prometheus.

## Etat Actuel

Version de travail : `v1.4.x`.

Socle valide :

- installation vierge rejouee et corrigee jusqu'a l'ajout d'une premiere cible ;
- guide d'installation, guide de mise a jour et assistant CLI disponibles ;
- authentification locale avec utilisateurs, droits modules et politique de mots de passe ;
- inventaire configurable des cibles ;
- supervision ping / SSH / latence / disque avec refresh cible ;
- patch management Linux, Proxmox, `apt` et `dnf` ;
- cycle de vie OS avec support / obsolescence / upgrade connu ;
- securite operationnelle de base ;
- alerting interne avec regles globales, mur d'alertes et vue backoffice ;
- export Prometheus stable ;
- titre d'onglet navigateur personnalisable par environnement.

## Priorite v1.5 - Diagnostic par Cible

Objectif : expliquer rapidement pourquoi un check echoue sur une cible precise.

- Afficher un diagnostic depuis la fiche cible.
- Tester la resolution DNS vue par PHP.
- Tester le ping MSM.
- Tester le port TCP SSH.
- Tester le login SSH via phpseclib.
- Lire l'OS distant si SSH fonctionne.
- Afficher les derniers messages d'erreur patch, cycle OS et securite.
- Garder cette page sans exposition de secrets.

## Backlog v1.x

### Collecteurs

- Synology DSM.
- Windows via PowerShell SSH ou WinRM.
- Docker via SSH sur l'hote Docker : containers, images, statuts, ports exposes.
- Proxmox avance : discovery, VMs, conteneurs, stockage.

### Alerting et Notifications

- Parametrage avance des regles : severite, seuils, delais.
- Desactivation par hote, module ou item precis.
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
