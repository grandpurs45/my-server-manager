# My Server Manager (MSM)

MSM est une application web de supervision et de gestion de serveurs Linux et Windows pour homelab et petite infrastructure.

## Fonctionnalites

- Gestion des serveurs : ajout, modification, suppression.
- Supervision : statut UP/DOWN, latence, dernier check.
- SSH : etat de connexion, detection OS, collecte disque.
- Patch Management : collecte planifiee des mises a jour Linux/Proxmox via SSH, `apt` et `dnf`.
- Cycle de vie OS : detection des fins de support et upgrades connus pour les distributions Linux supportees.
- Parametres dynamiques : debug, supervision, reseau.
- Migrations SQL versionnees.
- Export Prometheus pour Grafana.

## Installation

Pour une installation complete sur un environnement vierge, suivre [docs/INSTALL.md](docs/INSTALL.md).

Verifier les prerequis depuis la racine du projet :

```bash
php scripts/check-prerequisites.php
```

Resume rapide :

1. Cloner le depot :

   ```bash
   git clone https://github.com/grandpurs45/my-server-manager.git
   ```

2. Installer les dependances PHP :

   ```bash
   composer install
   ```

3. Preparer la configuration locale :

   ```bash
   cp .env.example .env
   ```

4. Editer `.env` avec les acces MariaDB et une cle locale :

   ```text
   MSM_DB_HOST=localhost
   MSM_DB_PORT=3306
   MSM_DB_NAME=msm
   MSM_DB_USER=root
   MSM_DB_PASS=
   MSM_DB_CHARSET=utf8mb4
   MSM_SECRET_KEY=replace-with-a-local-random-secret
   ```

   Pour generer une cle :

   ```bash
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

5. Configurer la base MariaDB, puis appliquer les migrations :

   ```bash
   php apply_migrations.php
   ```

6. Lancer avec XAMPP ou Apache/PHP, puis acceder a l'application :

   ```text
   http://localhost/msm/
   ```

## Configuration Locale

La configuration sensible ne doit pas etre versionnee. MSM lit le fichier `.env` a la racine du projet.

Variables disponibles :

```text
MSM_DB_HOST=localhost
MSM_DB_PORT=3306
MSM_DB_NAME=msm
MSM_DB_USER=root
MSM_DB_PASS=
MSM_DB_CHARSET=utf8mb4
MSM_SECRET_KEY=replace-with-a-local-random-secret
```

Les anciens fichiers `msm_secret.key` restent supportes temporairement pour compatibilite, mais les nouvelles installations doivent utiliser `MSM_SECRET_KEY` dans `.env`.

## Export Prometheus

MSM expose un endpoint Prometheus en texte brut :

```text
http://localhost/msm/metrics.php
```

Si `mod_rewrite` est actif et qu'une regle serveur est configuree, il est possible d'exposer aussi :

```text
http://localhost/msm/metrics
```

Les metriques exposees viennent uniquement de la base MSM. Le endpoint `metrics.php` ne lance pas de ping, SSH ou analyse distante afin de rester rapide et compatible avec un scrape Prometheus regulier.

Voir aussi [docs/PROMETHEUS.md](docs/PROMETHEUS.md) pour un exemple `prometheus.yml`, des requetes PromQL et un dashboard Grafana minimal.

Exemple de sortie :

```text
# HELP msm_server_up Last known server reachability status from MSM.
# TYPE msm_server_up gauge
msm_server_up{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_ssh_ok{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_server_latency_ms{server="server-01",hostname="server-01.example.local",type="linux"} 4
msm_server_disk_usage_percent{server="server-01",hostname="server-01.example.local",type="linux"} 67
msm_server_last_check_timestamp{server="server-01",hostname="server-01.example.local",type="linux"} 1780000000
msm_check_success{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_updates_available{server="server-01",hostname="server-01.example.local",type="linux",update_type="security"} 2
msm_reboot_required{server="server-01",hostname="server-01.example.local",type="linux",collector="apt"} 1
msm_os_upgrade_available{server="server-01",hostname="server-01.example.local",type="linux",os_family="ubuntu",os_version="22.04"} 1
```

## Scripts Utiles

Appliquer les migrations :

```bash
php apply_migrations.php
```

Lancer un check de supervision :

```bash
php scripts/check-servers.php
```

Lancer un check Patch Management :

```bash
php scripts/check-patches.php
```

Voir [docs/PATCH_MANAGEMENT.md](docs/PATCH_MANAGEMENT.md).

Lancer un check de cycle de vie OS :

```bash
php scripts/check-os-lifecycle.php
```

Voir [docs/OS_LIFECYCLE.md](docs/OS_LIFECYCLE.md).

## Roadmap

Voir [ROADMAP.md](ROADMAP.md).

## Technologies

- PHP 8+
- MariaDB
- Tailwind CSS
- phpseclib
- Composer
- Prometheus / Grafana

## Licence

Projet libre, sous licence MIT.
