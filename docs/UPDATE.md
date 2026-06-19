# Mise a jour MSM

Ce guide decrit une mise a jour standard de My Server Manager sur une installation existante.

Les commandes sont a executer depuis la racine du projet MSM, par exemple `/var/www/html/msm` ou `/var/www/msm` selon l'installation.

## 1. Identifier la version actuelle

```bash
git describe --tags --always
git status --short
```

Si `git status --short` affiche des fichiers modifies localement, verifier qu'il s'agit uniquement de fichiers de configuration ignores par Git. Ne pas lancer `git pull` si des fichiers versionnes ont ete modifies localement sans savoir pourquoi.

## 2. Sauvegarder avant mise a jour

Sauvegarder la base :

```bash
mysqldump -u msm_user -p msm > msm-backup-$(date +%F-%H%M).sql
```

Adapter `msm_user` et `msm` aux valeurs reelles de `.env`.

Sauvegarder la configuration locale :

```bash
cp .env .env.backup-$(date +%F-%H%M)
```

Sauvegarder aussi toute configuration Apache, cron ou systemd specifique a l'instance si elle a ete modifiee manuellement.

## 3. Recuperer le code

```bash
git pull
```

Pour mettre a jour vers une version taguee precise :

```bash
git fetch --tags
git checkout v0.26.0
```

En production, utiliser `main` si l'instance suit le flux courant, ou un tag si l'on veut figer une version.

## 4. Mettre a jour les dependances PHP

```bash
composer install --no-dev --optimize-autoloader
```

Si Composer signale des permissions insuffisantes sur `vendor/`, corriger le proprietaire du dossier projet avant de relancer la commande.

## 5. Appliquer les migrations

```bash
php apply_migrations.php
```

Les migrations sont idempotentes : une migration deja appliquee doit apparaitre en `SKIP`.

Equivalent via l'assistant setup :

```bash
php scripts/setup.php --migrate
```

## 6. Verifier les prerequis

```bash
php scripts/check-prerequisites.php
```

Les items `FAIL` doivent etre corriges avant de considerer la mise a jour terminee. Les items `WARN` peuvent etre acceptables selon l'environnement, mais doivent etre compris.

Lancer ensuite le controle post-update MSM :

```bash
php scripts/update-check.php
```

Ce script verifie la version, les dependances, `.env`, la connexion base, les migrations, les logs et la presence des scripts dans la crontab quand elle est accessible.

## 7. Relancer les checks principaux

Apres une mise a jour applicative ou une migration importante :

```bash
php scripts/check-servers.php --force
php scripts/check-patches.php --force
php scripts/check-os-lifecycle.php --force
php scripts/check-security.php --force
php scripts/check-alerts.php --force
```

Les scripts lisent et ecrivent en base. Ils ne doivent pas etre lances depuis une page web.

## 8. Verifier l'application

Verifier les pages suivantes :

- Dashboard : `/`
- Diagnostic : `/pages/diagnostic.php`
- Supervision : `/pages/supervision.php`
- Patch Management : `/pages/patch-management.php`
- Securite serveurs : `/pages/securite-serveurs.php`
- Alertes : `/pages/alerts.php`
- Regles d'alertes : `/pages/alert-rules.php`
- Metrics Prometheus : `/metrics.php`

Verification CLI rapide :

```bash
curl -s http://localhost/msm/metrics.php | head
```

Adapter `/msm/` si l'application est installee a la racine du vhost.

## 9. Verifier l'ordonnancement

Cron :

```bash
crontab -l
tail -n 50 logs/check-servers.log
tail -n 50 logs/check-alerts.log
```

Systemd timers :

```bash
systemctl list-timers 'msm-*'
journalctl -u msm-check-servers.service -n 50
journalctl -u msm-check-alerts.service -n 50
```

Voir [SCHEDULING.md](SCHEDULING.md) pour les exemples complets.

Pour regenerer les lignes cron avec le chemin reel de l'installation :

```bash
php scripts/setup.php --cron
```

Pour regenerer les fichiers systemd avec le chemin reel de l'installation :

```bash
php scripts/setup.php --systemd
```

Si les fichiers de logs attendus sont absents apres une migration d'ordonnancement :

```bash
php scripts/setup.php --init-logs
```

Pour verifier ou regenerer les commandes SQL attendues a partir de `.env` :

```bash
php scripts/setup.php --db-sql
```

## 10. Rollback minimal

Si la mise a jour pose probleme :

1. Revenir au tag precedent :

   ```bash
   git checkout v0.25.0
   composer install --no-dev --optimize-autoloader
   ```

2. Restaurer la base si une migration a modifie le schema ou les donnees :

   ```bash
   mysql -u msm_user -p msm < msm-backup-YYYY-MM-DD-HHMM.sql
   ```

3. Restaurer `.env` si necessaire :

   ```bash
   cp .env.backup-YYYY-MM-DD-HHMM .env
   ```

4. Relancer les checks de verification :

   ```bash
   php scripts/check-prerequisites.php
   php scripts/check-servers.php --force
   php scripts/check-alerts.php --force
   ```

## Points d'attention

- Ne jamais versionner `.env`.
- Verifier les permissions de `logs/`, `vendor/` et du dossier projet apres un deploiement.
- Appliquer les migrations avant de tester les nouvelles pages.
- Les nouvelles metriques Prometheus peuvent necessiter une mise a jour des dashboards Grafana.
- Les alertes MSM sont internes ; les notifications sortantes sont prevues pour une version v1.x.
