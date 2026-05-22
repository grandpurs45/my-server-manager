# Installation vierge MSM

Ce guide decrit une installation neuve de My Server Manager sur un serveur Apache/PHP avec MariaDB.

## Prerequis

- PHP 8.0 ou plus recent.
- Extensions PHP :
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
- MariaDB ou MySQL.
- Apache avec PHP active.
- Composer.
- Git.

## 1. Recuperer le projet

```bash
cd /var/www/html
git clone https://github.com/grandpurs45/my-server-manager.git msm
cd msm
```

## 2. Installer les dependances

```bash
composer install --no-dev --optimize-autoloader
```

Pour une installation de developpement locale, `composer install` suffit.

## 3. Creer la base de donnees

Exemple MariaDB :

```sql
CREATE DATABASE msm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'msm'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON msm.* TO 'msm'@'localhost';
FLUSH PRIVILEGES;
```

Adapter le nom d'utilisateur et le mot de passe selon l'environnement.

## 4. Creer la configuration locale

```bash
cp .env.example .env
```

Editer `.env` :

```text
MSM_DB_HOST=localhost
MSM_DB_PORT=3306
MSM_DB_NAME=msm
MSM_DB_USER=msm
MSM_DB_PASS=change-me
MSM_DB_CHARSET=utf8mb4
MSM_SECRET_KEY=replace-with-a-local-random-secret
```

Generer une cle locale :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copier la valeur generee dans `MSM_SECRET_KEY`.

Important : conserver cette cle. Elle sert au chiffrement des mots de passe SSH stockes en base.

## 5. Appliquer les migrations

```bash
php apply_migrations.php
```

Le script cree automatiquement la table `migrations_applied` si elle n'existe pas.

## 6. Verifier les permissions

Le serveur web doit pouvoir lire :

- le code applicatif ;
- `.env` ;
- `vendor/`.

Le serveur web doit pouvoir ecrire dans :

- `logs/`.

Exemple :

```bash
sudo chown -R www-data:www-data logs
sudo chmod -R 750 logs
```

Adapter l'utilisateur Apache selon la distribution.

## 7. Configurer Apache

MSM peut fonctionner dans un sous-dossier, par exemple :

```text
http://srv-msm.lan/msm/
```

L'endpoint Prometheus par defaut ne depend pas de `mod_rewrite` :

```text
http://srv-msm.lan/msm/metrics.php
```

Les fichiers sensibles `.env`, `.key` et `.pem` sont bloques par `.htaccess` quand Apache autorise les fichiers `.htaccess`.

## 8. Configurer le check planifie

Exemple cron toutes les minutes. Le script respecte ensuite l'intervalle configure dans MSM :

```cron
* * * * * /usr/bin/php /var/www/html/msm/scripts/check-servers.php >> /var/www/html/msm/logs/check-servers.log 2>&1
```

Adapter les chemins selon l'installation.

## 9. Verifications post-install

Ouvrir :

```text
http://srv-msm.lan/msm/pages/diagnostic.php
```

Verifier :

- version MSM ;
- version PHP ;
- connexion MariaDB ;
- heure PHP ;
- heure MariaDB ;
- presence de `.env` ;
- cle de chiffrement configuree ;
- dossier `logs/` accessible en ecriture ;
- dossier `migrations/` accessible en lecture.

Tester aussi :

```text
http://srv-msm.lan/msm/metrics.php
```

La page doit retourner du texte au format Prometheus.

## Mise a jour depuis une ancienne installation

Avant de passer a une version utilisant `.env`, copier la valeur actuelle de `msm_secret.key` dans `MSM_SECRET_KEY`.

Exemple :

```bash
cat msm_secret.key
```

Puis renseigner :

```text
MSM_SECRET_KEY=valeur-du-fichier-msm_secret.key
```

Sans cette valeur, les mots de passe SSH deja chiffres en base ne seront plus dechiffrables.
