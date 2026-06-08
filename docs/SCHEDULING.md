# Ordonnancement des checks MSM

MSM ne lance pas les checks lourds pendant l'affichage des pages ni pendant le scrape Prometheus. Les scripts CLI collectent les donnees, les stockent en base, puis l'interface et `/metrics.php` lisent les derniers resultats connus.

## Scripts a planifier

| Script | Role | Frequence cron conseillee | Intervalle interne MSM |
| --- | --- | --- | --- |
| `scripts/check-servers.php` | supervision de base, ping, SSH, OS, disque | toutes les minutes | `supervision / check_interval_minutes` |
| `scripts/check-patches.php` | patch management Linux/Proxmox | toutes les 10 a 15 minutes | `patch_management / check_interval_hours` |
| `scripts/check-os-lifecycle.php` | cycle de vie OS et upgrades connus | toutes les heures ou tous les jours | `os_lifecycle / check_interval_hours` |
| `scripts/check-security.php` | ports ouverts et pare-feu | toutes les heures ou tous les jours | `security / check_interval_hours` |

Les scripts peuvent etre appeles plus souvent que necessaire : chacun respecte son intervalle interne et saute l'execution si le dernier check est trop recent.

## Execution manuelle

Depuis la racine du projet :

```bash
php scripts/check-servers.php
php scripts/check-patches.php
php scripts/check-os-lifecycle.php
php scripts/check-security.php
```

Pour ignorer ponctuellement l'intervalle interne :

```bash
php scripts/check-patches.php --force
php scripts/check-os-lifecycle.php --force
php scripts/check-security.php --force
```

`--force` est utile apres une correction de configuration ou pour verifier immediatement une cible. Ne pas l'utiliser dans cron sauf besoin specifique.

## Logs

Creer un dossier de logs accessible par l'utilisateur qui execute les scripts :

```bash
mkdir -p /var/www/html/msm/logs
```

Adapter le chemin au dossier reel d'installation.

Exemples de fichiers :

```text
logs/check-servers.log
logs/check-patches.log
logs/check-os-lifecycle.log
logs/check-security.log
```

## Option 1 - Cron simple

Verifier le chemin de PHP :

```bash
which php
```

Editer la crontab :

```bash
crontab -e
```

Exemple :

```cron
* * * * * /usr/bin/php /var/www/html/msm/scripts/check-servers.php >> /var/www/html/msm/logs/check-servers.log 2>&1
*/10 * * * * /usr/bin/php /var/www/html/msm/scripts/check-patches.php >> /var/www/html/msm/logs/check-patches.log 2>&1
15 * * * * /usr/bin/php /var/www/html/msm/scripts/check-os-lifecycle.php >> /var/www/html/msm/logs/check-os-lifecycle.log 2>&1
30 * * * * /usr/bin/php /var/www/html/msm/scripts/check-security.php >> /var/www/html/msm/logs/check-security.log 2>&1
```

Recommandations :

- lancer `check-servers.php` souvent, car il est protege par l'intervalle `supervision` ;
- lancer `check-patches.php` regulierement, mais laisser MSM appliquer son intervalle interne ;
- lancer `check-os-lifecycle.php` au moins une fois par jour ou par heure, sans `--force`.
- lancer `check-security.php` au moins une fois par jour ou par heure, sans `--force`.

Verifier :

```bash
tail -n 50 /var/www/html/msm/logs/check-servers.log
tail -n 50 /var/www/html/msm/logs/check-patches.log
tail -n 50 /var/www/html/msm/logs/check-os-lifecycle.log
tail -n 50 /var/www/html/msm/logs/check-security.log
```

## Option 2 - Systemd timers

Systemd donne des logs centralises avec `journalctl` et evite certains pieges cron. Les exemples ci-dessous utilisent `/var/www/html/msm` et `/usr/bin/php`.

### Supervision

`/etc/systemd/system/msm-check-servers.service`

```ini
[Unit]
Description=MSM server supervision check

[Service]
Type=oneshot
WorkingDirectory=/var/www/html/msm
ExecStart=/usr/bin/php /var/www/html/msm/scripts/check-servers.php
User=www-data
Group=www-data
```

`/etc/systemd/system/msm-check-servers.timer`

```ini
[Unit]
Description=Run MSM server supervision check every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
Unit=msm-check-servers.service

[Install]
WantedBy=timers.target
```

### Patch Management

`/etc/systemd/system/msm-check-patches.service`

```ini
[Unit]
Description=MSM patch management check

[Service]
Type=oneshot
WorkingDirectory=/var/www/html/msm
ExecStart=/usr/bin/php /var/www/html/msm/scripts/check-patches.php
User=www-data
Group=www-data
```

`/etc/systemd/system/msm-check-patches.timer`

```ini
[Unit]
Description=Run MSM patch management check every 10 minutes

[Timer]
OnBootSec=5min
OnUnitActiveSec=10min
Unit=msm-check-patches.service

[Install]
WantedBy=timers.target
```

### Cycle de vie OS

`/etc/systemd/system/msm-check-os-lifecycle.service`

```ini
[Unit]
Description=MSM OS lifecycle check

[Service]
Type=oneshot
WorkingDirectory=/var/www/html/msm
ExecStart=/usr/bin/php /var/www/html/msm/scripts/check-os-lifecycle.php
User=www-data
Group=www-data
```

`/etc/systemd/system/msm-check-os-lifecycle.timer`

```ini
[Unit]
Description=Run MSM OS lifecycle check hourly

[Timer]
OnBootSec=10min
OnUnitActiveSec=1h
Unit=msm-check-os-lifecycle.service

[Install]
WantedBy=timers.target
```

### Securite

`/etc/systemd/system/msm-check-security.service`

```ini
[Unit]
Description=MSM security check

[Service]
Type=oneshot
WorkingDirectory=/var/www/html/msm
ExecStart=/usr/bin/php /var/www/html/msm/scripts/check-security.php
User=www-data
Group=www-data
```

`/etc/systemd/system/msm-check-security.timer`

```ini
[Unit]
Description=Run MSM security check hourly

[Timer]
OnBootSec=15min
OnUnitActiveSec=1h
Unit=msm-check-security.service

[Install]
WantedBy=timers.target
```

### Activer les timers

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now msm-check-servers.timer
sudo systemctl enable --now msm-check-patches.timer
sudo systemctl enable --now msm-check-os-lifecycle.timer
sudo systemctl enable --now msm-check-security.timer
```

Verifier :

```bash
systemctl list-timers 'msm-*'
journalctl -u msm-check-servers.service -n 50
journalctl -u msm-check-patches.service -n 50
journalctl -u msm-check-os-lifecycle.service -n 50
journalctl -u msm-check-security.service -n 50
```

Sur RHEL/Rocky/AlmaLinux/Fedora, remplacer souvent `www-data` par `apache`.

## Ordre recommande apres installation

1. Appliquer les migrations :

   ```bash
   php apply_migrations.php
   ```

2. Lancer une supervision manuelle :

   ```bash
   php scripts/check-servers.php
   ```

3. Lancer un premier Patch Management force :

   ```bash
   php scripts/check-patches.php --force
   ```

4. Lancer un premier cycle de vie OS force :

   ```bash
   php scripts/check-os-lifecycle.php --force
   ```

5. Lancer un premier controle securite force :

   ```bash
   php scripts/check-security.php --force
   ```

6. Configurer cron ou systemd timer.

7. Verifier `/metrics.php` et la page Diagnostic.

## Points d'attention

- L'utilisateur qui execute les scripts doit pouvoir lire `.env`, `vendor/` et ecrire dans `logs/`.
- Les checks SSH utilisent les identifiants stockes dans MSM ; verifier la cle `MSM_SECRET_KEY` avant migration ou restauration.
- Les scripts doivent etre lances depuis la racine du projet ou utiliser leur chemin complet.
- Ne pas lancer les checks depuis les pages web : l'UI doit rester en lecture des derniers resultats connus.
