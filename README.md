# My Server Manager (MSM)

MSM est une application web de supervision et de gestion de serveurs Linux et Windows pour homelab et petite infrastructure.

## Fonctionnalites

- Gestion des serveurs : ajout, modification, suppression.
- Supervision : statut ping, etat SSH, latence, disque, modules actifs, fraicheur du dernier check et historique minimal des changements.
- SSH : etat de connexion, detection OS, collecte disque.
- Patch Management : collecte planifiee des mises a jour Linux/Proxmox via SSH, `apt` et `dnf`.
- Cycle de vie OS : detection des fins de support et upgrades connus pour les distributions Linux supportees.
- Securite operationnelle : ports ouverts, exposition reseau, firewall, dernier controle et erreurs de collecte.
- Alerting : regles globales, alertes actives, mur d'alertes et vue backoffice.
- Etats operationnels homogenes : `OK`, `Warning`, `Critical`, `Unknown`.
- Notification de nouvelle version disponible avec lien vers les notes de release et le guide de mise a jour.
- Parametres dynamiques : debug, supervision, reseau.
- Migrations SQL versionnees.
- Export Prometheus pour Grafana.
- Guide d'installation et de mise a jour documente.
- Assistant CLI de setup / maintenance pour verifier l'installation, la base, les migrations, les logs et l'ordonnancement.

## Dashboard et fraicheur des checks

Le dashboard affiche une synthese des derniers resultats connus et ne lance aucun check lourd.

La carte `Fraicheur des checks` distingue :

- la derniere execution du script planifie ;
- le dernier resultat stocke en base ;
- le statut du script : termine, saute, en cours ou en erreur ;
- le dernier message court du script.

Cette distinction permet de voir si un cron tourne correctement mais saute un check a cause de l'intervalle interne, ou si le script echoue avant de produire un nouveau resultat.

## Perimetre v1

La v1 cible un usage homelab / petite infrastructure avec un support principal Linux et Proxmox.

- Linux / Proxmox : supervision, SSH, patch management, cycle de vie OS, securite operationnelle et metriques Prometheus.
- Windows / Synology : inventaire et supervision de base, support avance reporte en v1.x.
- Docker : hors v1, prevu en v1.x.

Le detail du support et des limites est documente dans [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md).

## Installation

Pour une installation complete sur un environnement vierge, suivre [docs/INSTALL.md](docs/INSTALL.md).

Pour mettre a jour une installation existante, suivre [docs/UPDATE.md](docs/UPDATE.md).

Verifier les prerequis depuis la racine du projet :

```bash
php scripts/check-prerequisites.php
```

Verifier l'installation complete et generer les lignes cron adaptees au chemin reel du projet :

```bash
php scripts/setup.php
php scripts/setup.php --install-deps
php scripts/setup.php --composer-install
php scripts/setup.php --cron
php scripts/setup.php --systemd
php scripts/setup.php --init-env
php scripts/setup.php --init-logs
php scripts/setup.php --db-sql
php scripts/setup.php --migrate
```

Apres une mise a jour, lancer le controle post-update :

```bash
php scripts/update-check.php
```

Resume rapide :

1. Installer le minimum pour cloner le depot :

   Debian / Ubuntu :

   ```bash
   sudo apt-get update
   sudo apt-get install -y php-cli git
   ```

   RHEL / Rocky Linux / AlmaLinux / Fedora :

   ```bash
   sudo dnf install -y php-cli git
   ```

2. Cloner le depot et entrer dans le projet :

   ```bash
   git clone https://github.com/grandpurs45/my-server-manager.git msm
   cd msm
   ```

3. Installer les dependances systeme MSM :

   ```bash
   php scripts/setup.php --install-deps
   ```

   Ajouter `--yes` uniquement apres verification des commandes affichees.

4. Installer les dependances PHP :

   ```bash
   php scripts/setup.php --composer-install
   ```

5. Preparer la configuration locale :

   ```bash
   php scripts/setup.php --init-env
   ```

6. Generer les commandes SQL, creer la base, puis editer `.env` :

   ```bash
   php scripts/setup.php --db-sql
   ```

   Remplacer `CHANGE_ME_STRONG_PASSWORD` par un mot de passe fort dans SQL et dans `.env`.

   Exemple de configuration `.env` :

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

7. Appliquer les migrations :

   ```bash
   php scripts/setup.php --migrate
   ```

8. Lancer avec XAMPP ou Apache/PHP, puis acceder a l'application :

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
msm_security_exposed_ports{server="server-01",hostname="server-01.example.local",type="linux"} 2
msm_security_firewall_enabled{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_alerts_active{severity="critical"} 1
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

Forcer un check de supervision sans attendre l'intervalle interne :

```bash
php scripts/check-servers.php --force
```

Lancer un check Patch Management :

```bash
php scripts/check-patches.php
```

Forcer un check manuel sans attendre l'intervalle interne :

```bash
php scripts/check-patches.php --force
```

Voir [docs/PATCH_MANAGEMENT.md](docs/PATCH_MANAGEMENT.md).

Lancer un check de cycle de vie OS :

```bash
php scripts/check-os-lifecycle.php
```

Forcer un check manuel sans attendre l'intervalle interne :

```bash
php scripts/check-os-lifecycle.php --force
```

Voir [docs/OS_LIFECYCLE.md](docs/OS_LIFECYCLE.md).

Lancer un check securite :

```bash
php scripts/check-security.php
```

Forcer un check securite manuel sans attendre l'intervalle interne :

```bash
php scripts/check-security.php --force
```

Evaluer les alertes actives :

```bash
php scripts/check-alerts.php
```

Forcer l'evaluation des alertes sans attendre l'intervalle interne :

```bash
php scripts/check-alerts.php --force
```

Diagnostiquer une connexion SSH telle qu'elle est vue par MSM :

```bash
php scripts/debug-ssh.php "nom-ou-hostname"
```

Ce diagnostic verifie la cible stockee en base, la resolution DNS PHP, le ping utilise par MSM, le port TCP, le dechiffrement du secret et l'authentification `phpseclib`, sans afficher le mot de passe.

## Alerting

MSM dispose d'un moteur d'alerting interne base sur les derniers resultats stockes en base.

- Backoffice des alertes : `pages/alerts.php`
- Mur d'alertes pour affichage dedie : `pages/alerts-wall.php`
- Regles globales d'alertes : `pages/alert-rules.php`

La v1 couvre les regles globales, la severite et les seuils simples. Les notifications sortantes, les silences et les desactivations par hote ou par item sont prevus pour la roadmap v1.x.

## Authentification locale

MSM dispose d'une authentification locale stockee en base.

- Les pages backoffice sont protegees par session.
- Les mots de passe sont hashes avec les fonctions natives PHP.
- Les droits peuvent etre attribues par module.
- Les parametres permettent de regler la complexite minimale des mots de passe.
- La duree d'expiration de session est configurable en minutes, avec `0` pour desactiver l'expiration.
- L'interface `Parametres > Utilisateurs` permet de creer et administrer les comptes, avec recherche et tri sur la liste.

La valeur `0` desactive l'expiration MSM pour inactivite. Le cookie reste lie a la session du navigateur : fermer completement le navigateur peut demander une nouvelle connexion.

Apres application des migrations, le compte initial est :

```text
Utilisateur : admin
Mot de passe : admin
```

Ce mot de passe doit etre change apres la premiere connexion. La personnalisation du compte initial pendant l'installation est prevue dans une version v1.x avec assistant d'installation.

Planifier les checks en production :

```text
docs/SCHEDULING.md
```

Voir [docs/SCHEDULING.md](docs/SCHEDULING.md) pour les exemples cron et systemd timers.

Pour une crontab utilisateur, rediriger les sorties vers le dossier `logs/` de MSM plutot que vers `/var/log`, sauf si les fichiers systeme ont ete prepares avec les permissions adaptees.

## Roadmap

Voir [ROADMAP.md](ROADMAP.md).

## Release v1

Les notes de release v1 sont disponibles dans [docs/RELEASE_NOTES_V1.md](docs/RELEASE_NOTES_V1.md).

## Technologies

- PHP 8+
- MariaDB
- Tailwind CSS
- phpseclib
- Composer
- Prometheus / Grafana

## Licence

Projet libre, sous licence MIT.
