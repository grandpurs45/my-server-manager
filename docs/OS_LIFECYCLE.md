# Cycle de vie OS

MSM peut collecter le cycle de vie connu des systemes Linux via SSH afin d'identifier :

- un OS encore supporte ;
- une fin de support proche ;
- un OS obsolete ;
- un upgrade majeur ou mineur connu.

Le check ne s'execute pas pendant l'affichage d'une page. Le script collecte les donnees, les stocke en base, puis l'interface lit le dernier resultat connu.

## Cibles supportees au depart

- Ubuntu LTS ;
- Debian ;
- Rocky Linux.

Les autres distributions sont enregistrees en statut `unknown` tant qu'aucune reference n'est disponible dans `os_lifecycle_references`.

## Fonctionnement

Le collecteur se connecte en SSH sur les cibles Linux/Proxmox avec le patch management actif, lit `/etc/os-release`, puis compare la famille et la version avec la table `os_lifecycle_references`.

```bash
php scripts/check-os-lifecycle.php
```

Exemple de sortie :

```text
[srv-web.lan] os_lifecycle=supported os=ubuntu 22.04 upgrade=24.04
[srv-docker.lan] os_lifecycle=supported os=rocky 10.1 upgrade=10.2
[2026-06-07 15:20:00] Verification cycle de vie OS terminee.
```

## Lecture dans l'interface

La page detail d'une cible affiche un bloc `Cycle de vie OS` avec :

- le statut de support ;
- l'OS detecte ;
- la date de fin de support connue ;
- l'upgrade disponible si une cible est connue ;
- le dernier check ;
- l'erreur eventuelle.

## References integrees

Les references initiales couvrent les versions courantes observees dans le homelab :

- Ubuntu 22.04, 24.04 et 26.04 LTS ;
- Debian 12 et 13 ;
- Rocky Linux 9, 10, 10.1 et 10.2.

Ces donnees sont versionnees par migration SQL. Elles pourront ensuite etre rendues administrables depuis les parametres si le besoin apparait.

Sources principales :

- Ubuntu Releases : https://releases.ubuntu.com/
- Debian bookworm release information : https://www.debian.org/releases/bookworm/
- Rocky Linux Release and Version Guide : https://wiki.rockylinux.org/rocky/version/

## Planification

Le cycle de vie OS change lentement. Une execution quotidienne ou hebdomadaire suffit.

Exemple cron hebdomadaire :

```cron
15 4 * * 1 cd /var/www/html/msm && php scripts/check-os-lifecycle.php >> logs/os-lifecycle.log 2>&1
```

Adapter le chemin au dossier reel d'installation.
