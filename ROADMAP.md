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

- Traiter les alertes Dependabot GitHub.
- Verifier les warnings confort `php-zip` / `unzip` sur installation fraiche.
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

Report post-v1 ou si besoin confirme :

- [ ] Ajouter un historique minimal des changements d'inventaire.

## Phase 3 - Patch Management

Objectif : savoir quoi mettre a jour et exposer ces informations a l'interface et a Prometheus.

Cibles prioritaires :

- Linux via SSH et `apt` ;
- Proxmox via SSH, `apt` et `pveversion` ;
- Docker via SSH sur l'hote Docker ;
- Synology via SSH ou API DSM ;
- Windows via PowerShell SSH ou WinRM.

Donnees attendues :

- nombre de mises a jour normales ;
- nombre de mises a jour de securite ;
- liste detaillee des paquets ou composants ;
- reboot requis ;
- dernier check reussi ;
- message d'erreur du dernier check ;
- metriques Prometheus associees.

Architecture souhaitee :

- checks lances par scripts planifies ;
- resultats stockes en base ;
- UI et `/metrics.php` lisent les derniers resultats connus ;
- aucun check lourd dans une page de consultation.
- activation explicite du patch management par cible, comme pour le module securite.

## Phase 4 - Securite Operationnelle

Objectif : identifier rapidement les risques simples.

- Ports ouverts via SSH.
- Firewall actif ou inactif.
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

- Dashboard d'accueil utile.
- Page supervision fiable.
- Page patch management.
- Page securite.
- Page details cible.
- Etats homogenes :
  - OK ;
  - warning ;
  - critical ;
  - unknown.
- Historique minimal des checks.
- Boutons de refresh cibles.

## Phase 6 - Release v1

Objectif : livrer une version installee, documentee et maintenable.

- README complet.
- Guide d'installation.
- Guide de mise a jour.
- Exemple cron ou systemd timer.
- Exemple configuration Prometheus.
- Notes de compatibilite.
- Changelog propre.
- Tag `v1.0.0`.
- Verification sur installation fraiche.

## Priorites Immediates

1. Concevoir le modele de donnees Patch Management.
2. Implementer Linux et Proxmox en premier.
3. Exposer les resultats Patch Management dans l'interface et Prometheus.
4. Ajouter Docker.
5. Ajouter Synology.
6. Ajouter Windows.

## Hors Scope v1

- Remplacer Grafana.
- Remplacer Prometheus.
- Supervision temps reel avancee.
- Systeme d'alerting complet.
- Gestion multi-utilisateurs avancee.
- Orchestration automatique de patchs sans validation humaine.
- Interface plus moderne.
- Autodiscovery Proxmox, Docker ou reseau.
- Setup d'installation interactif complet.
