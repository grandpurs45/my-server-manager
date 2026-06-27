# Release notes v1.0.0

MSM v1.0.0 marque le premier socle stable de My Server Manager pour un usage homelab / petite infrastructure.

L'objectif v1 est de fournir une source fiable de donnees d'exploitation, exploitable directement dans MSM et exportable vers Prometheus/Grafana.

## Evolution v1.8.0

La v1.8 ajoute un premier connecteur Home Assistant via SSH :

- nouveau type de cible `Home Assistant` ;
- collecte dediee avec `scripts/check-home-assistant.php` ;
- detection des versions Home Assistant Core, Supervisor et Home Assistant OS quand la CLI `ha` est disponible ;
- fallback systeme Linux limite si la CLI `ha` n'est pas exposee ;
- affichage des resultats dans la fiche cible ;
- fraicheur du check Home Assistant dans le dashboard ;
- metriques Prometheus `msm_home_assistant_*` ;
- generation cron/systemd et documentation dediee.

## Evolution v1.7.0

La v1.7 automatise et securise la mise a jour d'une instance MSM :

- prevalidation non destructive avec `php scripts/update.php --check` ;
- cible de release explicite avec `--target=vX.Y.Z` ;
- refus des fichiers Git versionnes modifies localement ;
- sauvegarde de `.env`, de MariaDB/MySQL et du contexte d'execution hors du dossier web ;
- installation Composer, migrations et initialisation des logs ;
- generation des propositions cron et systemd ;
- relance des checks principaux et controle post-update ;
- journal local et instructions de retour au code precedent sans restauration destructive automatique.

## Evolution v1.6.0

La v1.6 ajoute la sante materielle aux cibles Linux/Proxmox :

- profil materiel dans l'inventaire ;
- temperatures via `lm-sensors` ou `/sys/class/thermal` ;
- SMART en lecture seule sur les equipements physiques ;
- affichage detaille des disques, temperatures, heures, usure et erreurs media ;
- resumes temperature et SMART dans le dashboard ;
- alertes configurables de temperature, SMART, usure, erreurs media et fraicheur du collecteur ;
- metriques Prometheus associees ;
- nouveau script planifie `scripts/check-hardware-health.php`.

## Principes v1

- MSM stocke les derniers resultats connus en base.
- Les pages de consultation ne lancent pas de checks lourds.
- Les checks sont executes par scripts planifies.
- `/metrics.php` expose uniquement les donnees stockees.
- Grafana reste l'outil d'affichage et Prometheus l'outil de scrape.

## Fonctionnalites principales

- Inventaire des cibles :
  - type ;
  - environnement ;
  - criticite ;
  - tags ;
  - methode de collecte ;
  - modules actifs.
- Supervision :
  - ping UP/DOWN ;
  - statut SSH ;
  - latence ;
  - disque ;
  - fraicheur des checks ;
  - historique minimal des changements ping/SSH.
- Patch Management :
  - Linux `apt` ;
  - Linux `dnf` ;
  - Proxmox via socle Debian/apt ;
  - updates normales et securite ;
  - reboot requis ;
  - liste detaillee des paquets.
- Cycle de vie OS :
  - statut support connu ;
  - fin de support ;
  - upgrade connu quand reference.
- Securite operationnelle :
  - ports ouverts ;
  - exposition locale / liee / publique ;
  - statut firewall/UFW quand disponible ;
  - erreurs de collecte.
- Alerting interne :
  - serveur down ;
  - SSH KO ;
  - check supervision ancien ;
  - updates securite ;
  - reboot requis ;
  - OS obsolete ou proche fin de support ;
  - ports exposes ;
  - firewall inactif ou non detecte.
- Export Prometheus :
  - supervision ;
  - patch management ;
  - cycle de vie OS ;
  - securite ;
  - sante materielle ;
  - Home Assistant ;
  - alerting.

## Support v1

Support principal :

- Linux ;
- Proxmox base Debian/Linux.

Support limite :

- Windows : inventaire et supervision de base ;
- Synology : inventaire et supervision de base.

Hors v1 :

- Docker avance ;
- WinRM / patch Windows ;
- API Synology DSM ;
- autodiscovery ;
- notifications sortantes ;
- silences et maintenances ;
- setup interactif.

Voir aussi [COMPATIBILITY.md](COMPATIBILITY.md).

## Mise a jour vers v1

Procedure recommandee :

```bash
git pull
composer install --no-dev --optimize-autoloader
php apply_migrations.php
php scripts/update.php --check
```

Puis relancer les checks si besoin :

```bash
php scripts/check-servers.php --force
php scripts/check-patches.php --force
php scripts/check-os-lifecycle.php --force
php scripts/check-security.php --force
php scripts/check-hardware-health.php --force
php scripts/check-alerts.php --force
```

Voir [UPDATE.md](UPDATE.md) pour la procedure complete.

## Points d'attention

- Les alertes Dependabot connues avant v1 ont ete traitees cote runtime PHP et outillage npm local.
- L'authentification locale doit etre configuree avec des mots de passe forts et des droits modules limites.
- L'assistant genere les propositions cron/systemd mais ne modifie pas automatiquement l'ordonnancement existant.
- Les secrets doivent rester hors Git dans `.env`.
