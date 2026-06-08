# Patch Management

Le module Patch Management stocke les derniers resultats connus en base. Les pages MSM lisent ces donnees et ne lancent pas de checks lourds.

## Activation par cible

Dans `Serveurs`, modifier une cible puis cocher :

```text
Inclure dans le patch management
```

Le collecteur initial prend en charge les cibles `linux` et `proxmox` accessibles en SSH avec :

- `apt-get` pour Debian et Ubuntu ;
- `apt-get` avec collecteur `proxmox_apt` pour Proxmox ;
- `dnf` pour Rocky Linux et distributions RHEL-like.

## Lancer un check manuel

Depuis la racine du projet :

```bash
php scripts/check-patches.php
```

Ignorer ponctuellement l'intervalle interne :

```bash
php scripts/check-patches.php --force
```

Exemple de sortie :

```text
[server-01.example.local] patch_status=warning security=0 normal=12 reboot=no
[2026-06-04 21:00:00] Verification patch management terminee.
```

## Planification

MSM applique un intervalle interne via le parametre :

```text
patch_management / check_interval_hours
```

Valeur par defaut : `6` heures.

Le cron peut donc appeler le script regulierement. Si le dernier check est trop recent, le script s'arrete sans lancer de SSH.

Exemple cron toutes les 10 minutes :

```cron
*/10 * * * * /usr/bin/php /var/www/html/msm/scripts/check-patches.php >> /var/www/html/msm/logs/check-patches.log 2>&1
```

Adapter le chemin selon l'installation.

## Etats

- `ok` : aucune mise a jour connue.
- `warning` : mises a jour normales disponibles.
- `critical` : mises a jour de securite ou reboot requis.
- `error` : erreur de collecte.
- `unsupported` : cible activee mais non supportee par le collecteur actuel.

## Collecteurs disponibles

| Collecteur | Famille | Statut | Prerequis |
| --- | --- | --- | --- |
| `apt` | Debian, Ubuntu | Disponible | SSH, `apt-get` |
| `proxmox_apt` | Proxmox VE | Disponible | SSH, `apt-get` |
| `dnf` | Rocky Linux, RHEL-like | Disponible | SSH, `dnf` |
| `docker` | Hotes Docker | Prevu | SSH ou API Docker |
| `synology_dsm` | NAS Synology | Prevu | API DSM ou SSH |
| `windows_winrm` | Windows Server / Windows client | Prevu | WinRM ou PowerShell SSH |

## Limites actuelles

- Debian et Ubuntu via `apt`.
- Proxmox via `proxmox_apt`.
- Rocky / RHEL-like via `dnf`.
- Docker, Synology et Windows seront ajoutes progressivement.
- Les metriques Prometheus Patch Management lisent uniquement les derniers resultats stockes.
