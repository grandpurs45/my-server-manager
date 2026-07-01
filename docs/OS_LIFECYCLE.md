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

Le collecteur se connecte en SSH sur les cibles Linux/Proxmox avec SSH actif, lit `/etc/os-release`, puis compare la famille et la version avec la table `os_lifecycle_references`.

```bash
php scripts/check-os-lifecycle.php
```

Ignorer ponctuellement l'intervalle interne :

```bash
php scripts/check-os-lifecycle.php --force
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

- Ubuntu 12.04, 14.04, 16.04, 18.04, 20.04, 22.04, 24.04 et 26.04 LTS ;
- Debian 12 et 13 ;
- Rocky Linux 9, 10, 10.1 et 10.2.

Ces donnees sont versionnees par migration SQL et administrables depuis `Parametres > Cycle OS`.

## Gestion du referentiel

La page `Parametres > Cycle OS` permet de :

- consulter les familles, versions, codenames et dates de fin de support connues ;
- ajouter ou modifier manuellement une reference ;
- supprimer une reference qui n'est plus utile ;
- conserver une cible d'upgrade manuelle si besoin ;
- synchroniser les dates depuis `endoflife.date` pour les familles supportees ;
- ajouter ou supprimer une famille synchronisable sans modifier le code.

MSM conserve toujours les donnees en base locale. La synchronisation externe sert uniquement a alimenter ou rafraichir le referentiel local.

La cible d'upgrade est calculee automatiquement quand aucune cible manuelle n'est renseignee : MSM cherche la prochaine version supportee connue dans la meme famille OS. Une cible saisie manuellement reste prioritaire.

Synchronisation CLI :

```bash
php scripts/sync-os-lifecycle.php
```

Synchroniser une seule famille :

```bash
php scripts/sync-os-lifecycle.php --family=ubuntu
```

Familles synchronisables par defaut :

- `alpine` -> `endoflife.date/api/alpine.json`
- `ubuntu` -> `endoflife.date/api/ubuntu.json`
- `debian` -> `endoflife.date/api/debian.json`
- `rocky` -> `endoflife.date/api/rocky-linux.json`

La configuration est stockee dans `os_lifecycle / external_products` au format :

```text
famille_msm=produit_endoflife_date
```

## Alertes

Le moteur d'alerting expose maintenant trois cas OS lifecycle :

- `os_eol` : OS obsolete ;
- `os_eol_soon` : fin de support proche ;
- `os_lifecycle_unknown` : alerte informative si un OS est detecte mais qu'aucune date de fin de support n'est connue localement.

Sources principales :

- Ubuntu Releases : https://releases.ubuntu.com/
- Ubuntu 14.04 LTS Trusty Tahr ESM transition : https://ubuntu.com/blog/2019/02/05/ubuntu-14-04-trusty-tahr-end-of-life
- Ubuntu 18.04 LTS end of standard support : https://ubuntu.com/18-04
- Debian bookworm release information : https://www.debian.org/releases/bookworm/
- Rocky Linux Release and Version Guide : https://wiki.rockylinux.org/rocky/version/
- endoflife.date API : https://endoflife.date/docs/api

## Planification

Le cycle de vie OS change lentement. MSM applique un intervalle interne via le parametre :

```text
os_lifecycle / check_interval_hours
```

Valeur par defaut : `168` heures, soit une semaine. Une execution quotidienne ou hebdomadaire suffit.

Exemple cron hebdomadaire :

```cron
15 4 * * 1 cd /var/www/html/msm && php scripts/check-os-lifecycle.php >> logs/os-lifecycle.log 2>&1
```

Adapter le chemin au dossier reel d'installation.
