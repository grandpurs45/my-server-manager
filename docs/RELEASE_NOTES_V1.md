# Release notes v1.0.0

MSM v1.0.0 marque le premier socle stable de My Server Manager pour un usage homelab / petite infrastructure.

L'objectif v1 est de fournir une source fiable de donnees d'exploitation, exploitable directement dans MSM et exportable vers Prometheus/Grafana.

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
- authentification applicative ;
- setup interactif.

Voir aussi [COMPATIBILITY.md](COMPATIBILITY.md).

## Mise a jour vers v1

Procedure recommandee :

```bash
git pull
composer install --no-dev --optimize-autoloader
php apply_migrations.php
php scripts/check-prerequisites.php
```

Puis relancer les checks si besoin :

```bash
php scripts/check-servers.php --force
php scripts/check-patches.php --force
php scripts/check-os-lifecycle.php --force
php scripts/check-security.php --force
php scripts/check-alerts.php --force
```

Voir [UPDATE.md](UPDATE.md) pour la procedure complete.

## Points d'attention

- Les alertes Dependabot connues avant v1 ont ete traitees cote runtime PHP et outillage npm local.
- Le projet ne fournit pas encore d'authentification applicative.
- Les checks planifies doivent etre configures manuellement avec cron ou systemd timer.
- Les secrets doivent rester hors Git dans `.env`.
