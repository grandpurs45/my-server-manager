# Roadmap MSM vers v1

My Server Manager doit devenir un outil d'exploitation pour homelab et petite infrastructure. La v1 doit rester simple, fiable et utile au quotidien : inventaire, supervision légère, patch management, sécurité de base et export Prometheus/Grafana.

## Vision Produit

- Grafana affiche les dashboards et les alertes visuelles.
- Prometheus collecte les métriques.
- MSM collecte, analyse, stocke et expose les données métier d'exploitation.

MSM ne doit pas remplacer Grafana ou Prometheus. MSM doit devenir une source fiable de données d'exploitation.

## Objectif v1

La v1 sera atteinte quand MSM permettra de :

- gérer un inventaire propre de cibles techniques ;
- superviser l'etat de base des serveurs ;
- exposer des métriques Prometheus stables ;
- connaitre les mises a jour disponibles sur les cibles principales ;
- identifier les signaux de sécurité opérationnelle simples ;
- s'installer et se mettre a jour proprement.

## Phase 0 - Stabilisation du Socle

Objectif : rendre l'application fiable avant d'ajouter de nouveaux modules.

- [x] Finaliser les migrations pour une installation fraiche.
- [x] Sortir les secrets du depot Git.
- [x] Ajouter un modele de configuration locale ignoree par Git.
- [x] Ajouter un `.env.example` ou equivalent.
- [x] Documenter la procedure d'installation vierge.
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

## Phase 1 - Observabilite Grafana-Proof

Objectif : exposer proprement les donnees MSM sans faire de checks lourds au moment du scrape.

- Stabiliser `/metrics.php`.
- Exposer les métriques Prometheus de base :
  - `msm_server_up` ;
  - `msm_ssh_ok` ;
  - `msm_server_latency_ms` ;
  - `msm_server_disk_usage_percent` ;
  - `msm_server_last_check_timestamp` ;
  - `msm_check_success`.
- Documenter un exemple `prometheus.yml`.
- Preparer un dashboard Grafana minimal.
- Garantir que `/metrics.php` lit seulement la base et ne lance pas de SSH, ping, apt, Docker ou appel API.

## Phase 2 - Inventaire

Objectif : clarifier les entites gerees par MSM.

Types de cibles v1 :

- serveur Linux ;
- serveur Windows ;
- hote Proxmox ;
- NAS Synology ;
- hote Docker ;
- site web ou certificat.

Champs cibles :

- nom ;
- hostname ou IP ;
- type ;
- environnement ;
- criticite ;
- tags ;
- methode de collecte ;
- SSH active ou non ;
- date du dernier check ;
- statut du dernier check.

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
- nombre de mises a jour de sécurité ;
- liste detaillee des paquets ou composants ;
- reboot requis ;
- dernier check reussi ;
- message d'erreur du dernier check ;
- métriques Prometheus associees.

Architecture souhaitee :

- checks lances par scripts planifies ;
- resultats stockes en base ;
- UI et `/metrics.php` lisent les derniers resultats connus ;
- aucun check lourd dans une page de consultation.

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

1. Completer les métriques Prometheus existantes.
2. Concevoir le modele de donnees Patch Management.
3. Implementer Linux et Proxmox en premier.
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
- interface plus moderne
- autodiscovery (Proxmox, DOcker, Réseaux...)
- creer un setup d'installation
