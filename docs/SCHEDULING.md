# Ordonnancement des checks MSM

MSM ne lance pas les checks lourds pendant l'affichage des pages ni pendant le scrape Prometheus. Les scripts CLI collectent les donnees, les stockent en base, puis l'interface et `/metrics.php` lisent les derniers resultats connus.

## Scripts a planifier

| Script | Role | Frequence cron conseillee | Intervalle interne MSM |
| --- | --- | --- | --- |
| `scripts/check-servers.php` | supervision de base, ping, SSH, OS, disque | toutes les minutes | `supervision / check_interval_minutes` |
| `scripts/check-patches.php` | patch management Linux/Proxmox | toutes les 10 a 15 minutes | `patch_management / check_interval_hours` |
| `scripts/check-os-lifecycle.php` | cycle de vie OS et upgrades connus | toutes les heures ou tous les jours | `os_lifecycle / check_interval_hours` |
| `scripts/check-security.php` | ports ouverts et pare-feu | toutes les heures ou tous les jours | `security / check_interval_hours` |
| `scripts/check-hardware-health.php` | temperatures et SMART des equipements physiques | toutes les 5 minutes | `hardware_health / check_interval_minutes` |
| `scripts/check-alerts.php` | evaluation des alertes actives | toutes les 1 a 5 minutes | `alerting / check_interval_minutes` |

Les scripts peuvent etre appeles plus souvent que necessaire : chacun respecte son intervalle interne et saute l'execution si le dernier check est trop recent.

## Execution manuelle

Depuis la racine du projet :

```bash
php scripts/check-servers.php
php scripts/check-patches.php
php scripts/check-os-lifecycle.php
php scripts/check-security.php
php scripts/check-hardware-health.php
php scripts/check-alerts.php
```

Pour ignorer ponctuellement l'intervalle interne :

```bash
php scripts/check-servers.php --force
php scripts/check-patches.php --force
php scripts/check-os-lifecycle.php --force
php scripts/check-security.php --force
php scripts/check-hardware-health.php --force
php scripts/check-alerts.php --force
```

`--force` est utile apres une correction de configuration ou pour verifier immediatement une cible. Ne pas l'utiliser dans cron sauf besoin specifique.

## Logs

Creer un dossier de logs accessible par l'utilisateur qui execute les scripts :

```bash
mkdir -p /var/www/html/msm/logs
touch /var/www/html/msm/logs/check-{servers,patches,os-lifecycle,security,alerts}.log
chmod 775 /var/www/html/msm/logs
```

Adapter le chemin au dossier reel d'installation.

MSM peut aussi preparer ces fichiers automatiquement dans le dossier du projet :

```bash
php scripts/setup.php --init-logs
```

Ne pas rediriger les logs vers `/var/log` depuis une crontab utilisateur sans avoir cree les fichiers et configure leurs droits au prealable. La redirection est ouverte par le shell avant le lancement de PHP : si l'utilisateur cron ne peut pas creer le fichier, le script MSM ne demarre pas.

Exemples de fichiers :

```text
logs/check-servers.log
logs/check-patches.log
logs/check-os-lifecycle.log
logs/check-security.log
logs/check-alerts.log
```

## Option 1 - Cron simple

Verifier le chemin de PHP :

```bash
which php
```

MSM peut generer le bloc cron adapte au chemin reel du projet :

```bash
php scripts/setup.php --cron
```

Ce script ne modifie pas la crontab. Il affiche les lignes a copier dans `crontab -e` et recommande le dossier `logs/` du projet pour eviter les problemes de droits avec `/var/log`.

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
*/5 * * * * /usr/bin/php /var/www/html/msm/scripts/check-hardware-health.php >> /var/www/html/msm/logs/check-hardware-health.log 2>&1
*/5 * * * * /usr/bin/php /var/www/html/msm/scripts/check-alerts.php >> /var/www/html/msm/logs/check-alerts.log 2>&1
```

Recommandations :

- lancer `check-servers.php` souvent, car il est protege par l'intervalle `supervision` ;
- lancer `check-patches.php` regulierement, mais laisser MSM appliquer son intervalle interne ;
- lancer `check-os-lifecycle.php` au moins une fois par jour ou par heure, sans `--force`.
- lancer `check-security.php` au moins une fois par jour ou par heure, sans `--force`.
- lancer `check-alerts.php` plus frequemment, car il lit uniquement la base.

Avec l'exemple ci-dessus :

- supervision : cron appelle le script chaque minute, MSM execute le check selon l'intervalle configure ;
- patch management : cron appelle le script toutes les 10 minutes, MSM execute par defaut toutes les 6 heures ;
- cycle de vie OS : cron appelle le script a la minute 15 de chaque heure, MSM execute par defaut toutes les 168 heures ;
- securite : cron appelle le script a la minute 30 de chaque heure, MSM execute par defaut toutes les 24 heures ;
- alerting : cron appelle le script toutes les 5 minutes, MSM execute selon son intervalle configure.

Une ligne `Verification ... sautee` dans un log confirme que cron fonctionne : l'intervalle interne MSM n'etait simplement pas encore atteint.

Verifier :

```bash
tail -n 50 /var/www/html/msm/logs/check-servers.log
tail -n 50 /var/www/html/msm/logs/check-patches.log
tail -n 50 /var/www/html/msm/logs/check-os-lifecycle.log
tail -n 50 /var/www/html/msm/logs/check-security.log
tail -n 50 /var/www/html/msm/logs/check-alerts.log
```

Verifier la crontab reellement chargee :

```bash
crontab -l | grep -v '^#' | grep -v '^$'
```

Verifier l'ensemble setup + ordonnancement :

```bash
php scripts/setup.php
```

Sur Debian / Ubuntu, verifier aussi les executions cron :

```bash
sudo journalctl -u cron --since "15 minutes ago" --no-pager
```

## Option 2 - Systemd timers

Systemd donne des logs centralises avec `journalctl` et evite certains pieges cron.

Generer les fichiers `.service` et `.timer` adaptes au chemin reel du projet :

```bash
php scripts/setup.php --systemd
```

Sur RHEL/Rocky/AlmaLinux/Fedora, l'utilisateur Apache est souvent `apache` :

```bash
php scripts/setup.php --systemd --systemd-user=apache --systemd-group=apache
```

Copier ensuite les blocs generes dans les fichiers indiques sous `/etc/systemd/system/`.

### Activer les timers

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now msm-check-servers.timer
sudo systemctl enable --now msm-check-patches.timer
sudo systemctl enable --now msm-check-os-lifecycle.timer
sudo systemctl enable --now msm-check-security.timer
sudo systemctl enable --now msm-check-alerts.timer
```

Verifier :

```bash
systemctl list-timers 'msm-*'
journalctl -u msm-check-servers.service -n 50
journalctl -u msm-check-patches.service -n 50
journalctl -u msm-check-os-lifecycle.service -n 50
journalctl -u msm-check-security.service -n 50
journalctl -u msm-check-alerts.service -n 50
```

Sur RHEL/Rocky/AlmaLinux/Fedora, generer les fichiers avec `--systemd-user=apache --systemd-group=apache`.

## Ordre recommande apres installation

1. Appliquer les migrations :

   ```bash
   php apply_migrations.php
   ```

2. Lancer une supervision manuelle :

   ```bash
   php scripts/check-servers.php --force
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

6. Evaluer les alertes :

   ```bash
   php scripts/check-alerts.php --force
   ```

7. Configurer cron ou systemd timer.

8. Verifier `/metrics.php`, le Mur d'alertes et la page Diagnostic.

## Points d'attention

- L'utilisateur qui execute les scripts doit pouvoir lire `.env`, `vendor/` et ecrire dans `logs/`.
- Les checks SSH utilisent les identifiants stockes dans MSM ; verifier la cle `MSM_SECRET_KEY` avant migration ou restauration.
- Les scripts doivent etre lances depuis la racine du projet ou utiliser leur chemin complet.
- Ne pas lancer les checks depuis les pages web : l'UI doit rester en lecture des derniers resultats connus.
