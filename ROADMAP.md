# Roadmap MSM vers v1

My Server Manager doit devenir un outil d'exploitation pour homelab et petite infrastructure. La v1 doit rester simple, fiable et utile au quotidien : inventaire, supervision legere, patch management, securite de base et export Prometheus/Grafana.

## Vision Produit

- Grafana affiche les dashboards et les alertes visuelles.
- Prometheus collecte les metriques.
- MSM collecte, analyse, stocke et expose les donnees metier d'exploitation.

MSM ne doit pas remplacer Grafana ou Prometheus. MSM doit devenir une source fiable de donnees d'exploitation.

## Objectif v1

La v1 sera atteinte quand MSM permettra de :

- gerer un inventaire propre de cibles techniques ;
- superviser l'etat de base des serveurs ;
- exposer des metriques Prometheus stables ;
- connaitre les mises a jour disponibles sur les cibles principales ;
- identifier les signaux de securite operationnelle simples ;
- transformer les signaux critiques en alertes actives exploitables ;
- s'installer et se mettre a jour proprement.

## Phase 0 - Stabilisation du Socle

Statut : terminee dans `v0.15.0`.

Objectif : rendre l'application fiable avant d'ajouter de nouveaux modules.

- [x] Finaliser les migrations pour une installation fraiche.
- [x] Sortir les secrets du depot Git.
- [x] Ajouter un modele de configuration locale ignoree par Git.
- [x] Ajouter un `.env.example` ou equivalent.
- [x] Documenter la procedure d'installation vierge.
- [x] Ajouter un script de verification des prerequis.
- [x] Corriger l'encodage UTF-8 des fichiers et textes visibles.
- [x] Securiser les commandes systeme comme `ping`.
- [x] Ajouter une protection CSRF sur les formulaires critiques.
- [x] Ajouter une page diagnostic systeme :
  - version MSM ;
  - version PHP ;
  - timezone PHP ;
  - heure MariaDB ;
  - dernier check ;
  - statut des chemins et permissions essentiels.
- [x] Supporter une installation en sous-dossier comme `/msm/`.
- [x] Publier une release de stabilisation : `v0.15.0`.

## Maintenance Court Terme

Ces points ne bloquent pas la phase 1, mais doivent etre traites avant une v1.

- [x] Traiter les alertes Dependabot GitHub connues avant v1.
- [x] Verifier les warnings confort `php-zip` / `unzip`.
- Rejouer une installation vierge complete depuis la documentation sans intervention improvisee.

## Phase 1 - Observabilite Grafana-Proof

Objectif : exposer proprement les donnees MSM sans faire de checks lourds au moment du scrape.

- [x] Auditer l'endpoint `/metrics.php` actuel.
- [x] Stabiliser le format Prometheus expose.
- [x] Exposer les metriques Prometheus de base :
  - `msm_server_up` ;
  - `msm_ssh_ok` ;
  - `msm_server_latency_ms` ;
  - `msm_server_disk_usage_percent` ;
  - `msm_server_last_check_timestamp` ;
  - `msm_check_success`.
- [x] Ajouter les labels stables utiles sans surcharger les series :
  - `server` ;
  - `hostname` ;
  - `type`.
- [x] Ajouter le label `type` depuis l'inventaire MSM.
- [x] Garantir que `/metrics.php` lit seulement la base et ne lance pas de SSH, ping, apt, Docker ou appel API.
- [x] Documenter un exemple `prometheus.yml`.
- [x] Preparer un dashboard Grafana minimal.
- [x] Ajouter une verification simple de `/metrics.php` dans la documentation post-install.

## Phase 2 - Inventaire

Statut : terminee fonctionnellement, historique a garder pour plus tard.

Objectif : clarifier les entites gerees par MSM.

Types de cibles v1 :

- [x] serveur Linux ;
- [x] serveur Windows ;
- [x] hote Proxmox ;
- [x] NAS Synology ;
- [x] hote Docker ;
- [x] site web ou certificat ;
- [x] equipement reseau ;
- [x] autre.

Champs cibles :

- [x] nom ;
- [x] hostname ou IP ;
- [x] type ;
- [x] environnement ;
- [x] criticite ;
- [x] tags ;
- [x] methode de collecte ;
- [x] SSH active ou non ;
- [x] date du dernier check ;
- [x] statut du dernier check.
- [x] Options parametables pour types de cibles, environnements, criticites et methodes de collecte.
- [x] Tags affiches comme badges dans la liste.
- [x] Label Prometheus `type` alimente par l'inventaire.
- [x] Ajouter des filtres par type, environnement, criticite, statut et tag.
- [x] Ajouter une page detail cible orientee inventaire.
- [x] Ajouter une activation par cible pour le module securite.
- [x] Afficher rapidement les modules actifs par cible dans la liste des serveurs.

Report post-v1 ou si besoin confirme :

- [ ] Ajouter un historique minimal des changements d'inventaire.

## Phase 3 - Patch Management

Statut : en cours.

Objectif : savoir quoi mettre a jour et exposer ces informations a l'interface et a Prometheus.

Cibles prioritaires :

- Linux via SSH et `apt` ;
- Proxmox via SSH, `apt` et `pveversion` ;
- Synology via SSH ou API DSM ;
- Windows via PowerShell SSH ou WinRM.

Donnees attendues :

- nombre de mises a jour normales ;
- nombre de mises a jour de securite ;
- liste detaillee des paquets ou composants ;
- reboot requis ;
- fin de support OS et upgrade disponible ;
- dernier check reussi ;
- message d'erreur du dernier check ;
- metriques Prometheus associees.

Architecture souhaitee :

- checks lances par scripts planifies ;
- resultats stockes en base ;
- UI et `/metrics.php` lisent les derniers resultats connus ;
- aucun check lourd dans une page de consultation.
- activation explicite du patch management par cible, comme pour le module securite.

Socle :

- [x] Ajouter le flag `patch_management_enabled` sur les cibles.
- [x] Creer les tables de synthese et detail des checks patch management.
- [x] Ajouter une page Patch Management lisant les derniers resultats connus.
- [x] Preparer les classes metier de resultat et de lecture.
- [x] Tracer et afficher le collecteur utilise par resultat.
- [x] Ajouter une planification interne configurable des checks patch management.

Reste a faire :

- [x] Implementer le collecteur Linux/Proxmox initial via SSH et `apt`.
- [x] Ajouter le support Linux `dnf` / Rocky / RHEL-like.
- [x] Ajouter un premier collecteur de cycle de vie OS pour savoir si un systeme est supporte et si un upgrade est connu.
- [x] Exposer les metriques Prometheus Patch Management.
- [x] Exposer les metriques Prometheus de cycle de vie OS.
- [x] Ajouter la liste detaillee des paquets par cible.
- [ ] Ajouter Synology.
- [ ] Ajouter Windows.

Report post-v1 ou si besoin confirme :

- [ ] Rendre le referentiel de cycle de vie OS administrable depuis les parametres :
  - famille OS ;
  - version ;
  - nom de code ;
  - date de fin de support ;
  - version cible d'upgrade ;
  - source ;
  - note.

## Phase 4 - Securite Operationnelle

Objectif : identifier rapidement les risques simples.

- [x] Ports ouverts via SSH.
- [x] Firewall actif ou inactif.
- [x] Metriques Prometheus securite.
- Reboot requis.
- Services critiques.
- Certificats SSL :
  - validite ;
  - jours avant expiration ;
  - erreur de verification.
- Headers HTTP de securite :
  - HSTS ;
  - X-Frame-Options ;
  - X-Content-Type-Options ;
  - Content-Security-Policy.

## Phase 5 - Interface v1

Objectif : rendre MSM utilisable au quotidien.

- [x] Dashboard d'accueil utile.
- [x] Signaux securite visibles dans le dashboard.
- [x] Page supervision fiable.
- Page patch management.
- [x] Page securite.
- Page details cible.
- [x] Etats homogenes :
  - OK ;
  - warning ;
  - critical ;
  - unknown.
- [x] Historique minimal des checks.
- Boutons de refresh cibles reportes en v1.x.

## Phase 6 - Alerting v1

Objectif : transformer les derniers resultats connus en alertes exploitables dans MSM, sans remplacer les alertes visuelles Grafana.

Principes :

- le moteur d'alerting lit uniquement les donnees stockees en base ;
- aucun ping, SSH, apt, scan ou appel distant pendant l'evaluation ;
- les alertes sont ouvertes, mises a jour ou resolues par un script planifie ;
- l'interface lit les alertes courantes et leur historique.

Sources d'alerte v1 :

- [x] Serveur down.
- [x] SSH KO.
- [x] Dernier check trop ancien.
- [x] Mises a jour de securite disponibles.
- [x] Reboot requis.
- [x] OS obsolete ou fin de support proche.
- [x] Ports exposes.
- [x] Firewall inactif ou non detecte.

Socle technique :

- [x] Creer les tables `alert_rules`, `alerts` et `alert_events`.
- [x] Ajouter un script `scripts/check-alerts.php`.
- [x] Ajouter une page Mur d'alertes lisant uniquement la base.
- [x] Ajouter une vue backoffice des alertes.
- [x] Ajouter une page minimale de gestion des regles globales.
- [x] Ajouter les alertes actives au dashboard.
- [x] Exposer des metriques Prometheus :
  - `msm_alerts_active` ;
  - `msm_alert_active`.

Report post-v1 :

- [ ] Parametrage avance des regles d'alerting.
- [ ] Desactivation d'une alerte par hote, module ou item precis.
- [ ] Notifications email, webhook, Discord ou autre canal externe.
- [ ] Escalade et acquittement avances.
- [ ] Fenetres de maintenance.

## Phase 7 - Release v1

Objectif : livrer une version installee, documentee et maintenable.

- [x] README complet.
- [x] Guide d'installation.
- [x] Guide de mise a jour.
- [x] Exemple cron ou systemd timer.
- [x] Exemple configuration Prometheus.
- [x] Notes de compatibilite.
- [x] Changelog propre.
- [x] Tag `v1.0.0`.
- Verification sur installation fraiche reportee avec le setup assiste v1.x.

## Roadmap v1.x

Objectif : etendre MSM apres une v1.0 stable, sans alourdir le socle initial.

## v1.1 - Authentification locale

Objectif : ajouter une authentification securisee basee sur la base MSM locale, sans dependance externe.

- [x] Ajouter une authentification locale securisee :
  - [x] mots de passe hashes avec `password_hash()` ;
  - [x] verification avec `password_verify()` ;
  - [x] sessions PHP durcies ;
  - [x] expiration configurable des sessions inactives ;
  - [x] protection CSRF sur les formulaires d'authentification et d'administration ;
  - [x] protection des pages backoffice.
- [x] Ajouter un compte administrateur local par defaut.
- [x] Prevoir la personnalisation du compte administrateur par defaut lors d'une future installation assistee :
  - nom utilisateur ;
  - mot de passe.
- [x] Ajouter une interface de gestion des utilisateurs dans les parametres :
  - [x] liste des utilisateurs ;
  - [x] creation d'un utilisateur ;
  - [x] modification d'un utilisateur ;
  - [x] activation / desactivation ;
  - [x] changement de mot de passe ;
  - [x] suppression ou verrouillage selon le niveau de risque.
- [x] Ajouter une gestion des droits par modules :
  - dashboard ;
  - serveurs ;
  - supervision ;
  - alertes ;
  - patch management ;
  - securite ;
  - diagnostic ;
  - parametres ;
  - export Prometheus si besoin.
- [x] Ajouter une interface de gestion des droits :
  - [x] droits par utilisateur ;
  - [x] profil administrateur ;
  - [x] profil lecture seule si utile ;
  - [x] controles visibles dans l'UI selon les droits.
- [x] Ajouter des parametres de complexite des mots de passe :
  - [x] longueur minimale ;
  - [x] majuscule ;
  - [x] minuscule ;
  - [x] chiffre ;
  - [x] caractere special ;
  - [x] refus du mot de passe identique au nom utilisateur.
- [x] Ajouter un generateur de mot de passe optionnel dans l'interface de creation / modification utilisateur.
- [x] Tracer les evenements d'authentification importants :
  - connexion reussie ;
  - echec de connexion ;
  - changement de mot de passe ;
  - creation / modification / desactivation d'un utilisateur.
- [x] Patch v1.1.1 :
  - [x] rendre le bandeau de changement de mot de passe obligatoire plus visible ;
  - [x] tracer les executions des scripts planifies dans la fraicheur des checks ;
  - [x] distinguer derniere execution de script et dernier resultat stocke.

Hors v1.1, a reporter dans une roadmap future :

- [ ] Connecteurs d'identite externes :
  - Active Directory ;
  - LDAP ;
  - Keycloak ;
  - OIDC / OAuth2 ;
  - autre annuaire externe.
- [ ] Analyse des mots de passe faibles ou deja compromis dans des fuites publiques.
- [ ] Envoi d'un email a la creation d'un compte ou lors d'un changement de mot de passe.
- [ ] Lien mot de passe perdu / reinitialisation autonome.

Docker :

- [ ] Ajouter un inventaire Docker via SSH sur l'hote Docker :
  - containers ;
  - images ;
  - tags ;
  - statut ;
  - ports exposes.
- [ ] Stocker les resultats Docker en base sans check lourd dans l'UI.
- [ ] Afficher les containers dans la fiche cible ou une page dediee.
- [ ] Ajouter une synthese Docker dans Patch Management.
- [ ] Detecter prudemment les images obsoletes ou mises a jour disponibles.
- [ ] Exposer les metriques Prometheus Docker utiles.

Alerting avance :

- [ ] Rendre les regles d'alerting administrables depuis l'interface :
  - activation ou desactivation globale ;
  - severite ;
  - seuils ;
  - delai avant ouverture ;
  - delai avant resolution.
- [ ] Permettre de desactiver l'alerting ou certaines familles d'alertes pour un hote complet.
- [ ] Permettre de desactiver une alerte pour un module precis d'une cible :
  - supervision ;
  - SSH ;
  - patch management ;
  - cycle de vie OS ;
  - securite.
- [ ] Permettre de desactiver une alerte pour un item precis, par exemple :
  - un port expose attendu ;
  - un firewall non gere par UFW ;
  - un reboot accepte temporairement ;
  - une mise a jour ignoree ;
  - un OS volontairement conserve.
- [ ] Ajouter une notion de silence ou maintenance temporaire avec date de fin.
- [ ] Tracer les desactivations et silences dans l'historique.

Explicitement hors v1 :

- [ ] Desactivation par hote.
- [ ] Desactivation par item precis.
- [ ] Silences et fenetres de maintenance.
- [ ] Notifications sortantes.

Notifications v1.x :

- [ ] Ajouter un moteur de notifications base sur les alertes actives :
  - envoi a l'ouverture ;
  - rappel tant que l'alerte reste active ;
  - notification de resolution.
- [ ] Ajouter des canaux configurables :
  - email ;
  - webhook generique ;
  - Discord ;
  - autre canal externe si besoin.
- [ ] Permettre d'activer ou desactiver les notifications par regle.
- [ ] Permettre d'activer ou desactiver les notifications par cible.
- [ ] Eviter le spam avec un delai minimal entre deux notifications pour la meme alerte.
- [ ] Tracer les tentatives de notification et leur statut.

Autres extensions v1.x :

- [ ] Ajouter Synology.
- [ ] Ajouter Windows.
- [ ] Permettre de personnaliser les colonnes visibles dans les listes d'administration :
  - utilisateurs ;
  - serveurs ;
  - inventaire ;
  - patch management si utile.
- [ ] Ajouter une vue des evenements d'authentification :
  - connexions reussies ;
  - echecs de connexion ;
  - changements de mot de passe ;
  - creation / modification / suppression de comptes ;
  - expiration de session.
- [ ] Ajouter un setup d'installation assiste pour l'ordonnancement :
  - choix explicite entre cron et systemd timers ;
  - ne pas configurer les deux par defaut ;
  - generation des commandes selon le dossier reel d'installation ;
  - verification adaptee au mode choisi ;
  - creation des logs et permissions necessaires ;
  - confirmation utilisateur avant modification de cron ou systemd.
- [ ] Ajouter un script ou assistant d'installation / mise a jour :
  - verification interactive des prerequis ;
  - creation guidee du `.env` ;
  - aide a la creation de la base ;
  - lancement des migrations ;
  - validation installation fraiche ;
  - validation mise a jour depuis une ancienne version.
- [ ] Ajouter des boutons de refresh cibles :
  - supervision d'une cible ;
  - patch management d'une cible ;
  - cycle de vie OS d'une cible ;
  - securite d'une cible.
- [ ] Ajouter un outil de diagnostic par cible pour expliquer les ecarts entre supervision et collecteurs :
  - resolution DNS vue par PHP et par le systeme ;
  - test TCP sur le port cible ;
  - test SSH phpseclib avec les identifiants MSM ;
  - comparaison ping systeme / statut MSM ;
  - affichage des causes probables sans exposer les secrets.
- [ ] Ajouter une navigation par onglets dans la fiche cible pour limiter le scroll :
  - resume ;
  - inventaire ;
  - supervision ;
  - patch management ;
  - cycle de vie OS ;
  - securite ;
  - historique.
- [ ] Ajouter l'historique minimal des changements d'inventaire.
- [ ] Rendre le referentiel de cycle de vie OS administrable depuis les parametres.
- [ ] Ajouter une synchronisation optionnelle du referentiel OS Lifecycle depuis `endoflife.date` :
  - script CLI dedie ;
  - cache local en base ;
  - aucune dependance API au chargement des pages ou de `/metrics.php` ;
  - conservation de la source et de la date de synchronisation.

## Priorites v1.1

1. Concevoir le modele de donnees utilisateurs / droits.
2. Ajouter l'authentification locale securisee.
3. Proteger les pages backoffice.
4. Ajouter l'interface de gestion des utilisateurs et des droits.

## Hors Scope v1

- Remplacer Grafana.
- Remplacer Prometheus.
- Supervision temps reel avancee.
- Systeme d'alerting complet avec notifications et escalades avancees.
- Gestion multi-utilisateurs avancee.
- Orchestration automatique de patchs sans validation humaine.
- Interface plus moderne.
- Autodiscovery Proxmox, Docker ou reseau.
- Collecteur Docker avance.
- Setup d'installation interactif complet.
- Discovery Proxmox
- Ajout, Suppression, desactivation, suppression de collecteurs
- interface d'administration plus moderne
- interface globale plus moderne
- modernisation des paramètres d'inventaire
- agent pour les serveurs ?
- script d'installation qui créé les cron directement
