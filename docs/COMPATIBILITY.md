# Compatibilite v1

Ce document fixe le perimetre supporte pour MSM v1.0.0.

MSM reste un outil personnel d'exploitation homelab / petite infrastructure. La v1 vise un socle fiable, pas une couverture exhaustive de toutes les plateformes.

## Support v1

### Linux

Support principal v1.

- inventaire ;
- supervision ping ;
- statut SSH ;
- detection OS ;
- collecte disque ;
- patch management via SSH ;
- collecteurs `apt` et `dnf` ;
- reboot requis ;
- cycle de vie OS pour les references connues ;
- securite operationnelle de base :
  - ports ouverts ;
  - exposition locale / liee / publique ;
  - statut UFW quand disponible.

### Proxmox

Support v1 base sur le socle Linux/Debian.

- inventaire ;
- supervision ping ;
- statut SSH ;
- patch management via `apt` ;
- cycle de vie OS lorsque l'OS est reconnu ;
- securite operationnelle de base.

Les integrations Proxmox avancees, comme l'inventaire des VM/LXC via API, sont hors v1.

### Windows

Support v1 limite.

- inventaire ;
- supervision ping ;
- statut SSH si le serveur accepte SSH ;
- affichage dans les vues principales.

Le patch management Windows, WinRM et les controles securite Windows sont prevus pour v1.x.

### Synology

Support v1 limite.

- inventaire ;
- supervision ping ;
- affichage dans les vues principales.

Les collecteurs DSM, mises a jour Synology et controles dedies sont prevus pour v1.x.

### Home Assistant

Support v1 limite.

- inventaire ;
- supervision ping ;
- statut SSH si l'instance accepte SSH ;
- collecte SSH dediee si la cible est configuree avec le type `home_assistant` ;
- detection progressive des versions Home Assistant, Supervisor et OS quand la CLI `ha` est disponible ;
- fallback systeme Linux si la CLI `ha` n'est pas exposee ;
- affichage dans les vues principales et export Prometheus.

Le connecteur doit rester compatible avec les installations en VM, bare metal, conteneur ou appliance. Les alertes Home Assistant dediees restent prevues pour v1.x.

### Docker

Docker est hors v1.

La roadmap v1.x prevoit un inventaire Docker via l'hote, avec containers, images, statuts, ports exposes et metriques Prometheus utiles.

## Garanties v1

- Les pages de consultation ne lancent pas de checks lourds.
- Les endpoints Prometheus lisent les derniers resultats stockes en base.
- Les checks lourds sont lances par scripts planifies.
- Les secrets doivent rester dans `.env` ou dans la base chiffree.
- Les migrations doivent permettre une installation ou mise a jour reproductible.

## Limites connues v1

- Pas d'authentification applicative.
- Pas de notifications sortantes.
- Pas de silences ou fenetres de maintenance.
- Pas de refresh cible par module.
- Pas de setup interactif d'installation.
- Pas d'autodiscovery Docker, Proxmox ou reseau.
- Pas de gestion avancee des collecteurs depuis l'interface.

## Avant une mise en production personnelle

- Executer `php scripts/check-prerequisites.php`.
- Verifier `.env`.
- Appliquer les migrations avec `php apply_migrations.php`.
- Configurer l'ordonnancement avec `docs/SCHEDULING.md`.
- Verifier `/metrics.php` si Prometheus est utilise.
