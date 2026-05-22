# Installation vierge MSM

Ce guide decrit une installation neuve de My Server Manager sur un serveur Apache/PHP avec MariaDB.

## Modes d'installation possibles

MSM peut etre installe de plusieurs facons selon l'environnement cible :

- Serveur Linux : mode recommande pour une installation homelab ou petite production.
- Serveur Windows : possible avec une stack PHP/MariaDB locale, par exemple XAMPP ou WAMP.
- Docker : possible comme mode d'installation cible, avec des volumes persistants pour la base, la configuration et les logs. A ce stade, le projet ne fournit pas encore d'image Docker officielle ni de fichier `compose.yaml` pret a l'emploi.

La procedure ci-dessous decrit principalement une installation native sur serveur Linux avec Apache, PHP et MariaDB. Les principes restent les memes pour Windows ou Docker : installer PHP, MariaDB/MySQL, Composer, creer `.env`, appliquer les migrations, puis configurer l'execution planifiee des checks.

## Prerequis materiel

Configuration minimale pour un petit homelab :

- 1 vCPU.
- 1 Go de RAM.
- 5 Go d'espace disque disponible.
- Acces reseau vers les serveurs a superviser.

Configuration recommandee :

- 2 vCPU.
- 2 Go de RAM ou plus.
- 10 Go d'espace disque ou plus.
- Stockage persistant pour la base de donnees, le fichier `.env` et les logs.

La consommation depend surtout du nombre de serveurs supervises, de la frequence des checks, du volume de logs conserve et des futurs modules actifs.

## Prerequis logiciels

- PHP 8.0 ou plus recent.
- Extensions PHP :
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
  - `zip` recommande pour Composer.
- MariaDB ou MySQL.
- Apache avec PHP active.
- Composer.
- Git.
- `ping` pour les checks de disponibilite.
- `unzip` recommande pour Composer.
- `ssh` recommande pour certains diagnostics et tests manuels.

## Installation des dependances systeme

Sur une installation Linux vierge, installer d'abord les paquets systeme.

Debian / Ubuntu :

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server mariadb-client php php-cli php-mysql php-mbstring php-xml php-curl php-zip unzip git composer
sudo systemctl enable --now apache2 mariadb
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install -y httpd mariadb-server mariadb php php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip unzip git
sudo systemctl enable --now httpd
sudo systemctl enable --now mariadb
```

Sur certaines versions RHEL / Rocky Linux / AlmaLinux, le paquet `composer` n'est pas disponible dans les depots actifs. Installer alors Composer separement :

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
rm composer-setup.php
composer --version
```

Verifier ensuite que PHP est en version 8.0 ou plus recente :

```bash
php -v
```

Ces commandes installent les dependances systeme. Les dependances PHP du projet sont ensuite installees avec `composer install` a l'etape 3.

## Verification manuelle rapide

Avant de recuperer le projet, verifier rapidement les outils de base avec :

```bash
php -v
php -m
composer --version
git --version
mysql --version
apache2 -v
df -h .
free -h
```

Sur Windows avec XAMPP/WAMP, utiliser aussi :

```powershell
php -v
php -m
composer --version
git --version
```

## 1. Recuperer le projet

```bash
cd /var/www/html
git clone https://github.com/grandpurs45/my-server-manager.git msm
cd msm
```

## 2. Verifier automatiquement les prerequis

Une fois le projet clone, le script de verification est disponible dans `scripts/check-prerequisites.php`.

Verifier d'abord que le terminal est bien place dans la racine du projet :

```bash
pwd
ls scripts/check-prerequisites.php
```

Le dossier courant doit etre le dossier clone, par exemple :

```text
/var/www/html/msm
```

Depuis cette racine du projet, lancer :

```bash
php scripts/check-prerequisites.php
```

Si le terminal est encore dans `/var/www/html`, utiliser plutot :

```bash
php msm/scripts/check-prerequisites.php
```

Le script verifie :

- la version PHP ;
- la disponibilite de la fonction PHP `exec()` ;
- les extensions PHP requises ;
- les extensions PHP recommandees pour Composer ;
- la presence de Git et Composer ;
- la presence de `ping` pour les checks de disponibilite ;
- la presence de `unzip` pour Composer ;
- la presence d'un client MariaDB/MySQL ;
- la detection d'Apache quand la commande est disponible ;
- le statut du service Apache sur Linux avec systemd ;
- l'espace disque disponible ;
- la memoire systeme quand elle est detectable ;
- la presence de `.env` ;
- les permissions de `logs/` ;
- l'acces au dossier `migrations/`.

Les statuts possibles sont :

- `OK` : le point est valide ;
- `WARN` : le point doit etre verifie, mais ne bloque pas toujours l'installation ;
- `FAIL` : le point doit etre corrige avant de continuer.

Exemple :

```text
[OK] PHP version - 8.2.12
[OK] PHP extension pdo_mysql
[WARN] Local config .env - missing; copy .env.example to .env before running MSM
```

### Erreurs frequentes du script de prerequis

#### `[FAIL] PHP extension pdo_mysql - missing`

MSM utilise MariaDB/MySQL via PDO. Installer l'extension PHP MySQL, puis redemarrer Apache.

Debian / Ubuntu :

```bash
sudo apt update
sudo apt install php-mysql
sudo systemctl restart apache2
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install php-mysqlnd
sudo systemctl restart httpd
```

Verifier ensuite :

```bash
php -m | grep pdo_mysql
```

#### `[FAIL] PHP function exec - disabled`

MSM utilise `exec()` pour lancer certains checks systeme, notamment `ping`. Si cette fonction est desactivee dans `php.ini`, les serveurs peuvent rester en statut `DOWN`.

Chercher la directive `disable_functions` :

```bash
php -i | grep disable_functions
```

Si `exec` apparait dans la liste, retirer `exec` de `disable_functions`, puis redemarrer Apache.

#### `[FAIL] Command ping - not found in PATH`

MSM utilise `ping` pour determiner si un serveur est joignable.

Debian / Ubuntu :

```bash
sudo apt install iputils-ping
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install iputils
```

Verifier ensuite :

```bash
ping -c 1 127.0.0.1
```

#### `[FAIL] Command composer - not found in PATH`

Composer est necessaire pour installer les dependances PHP.

Debian / Ubuntu :

```bash
sudo apt update
sudo apt install composer
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
rm composer-setup.php
```

Verifier ensuite :

```bash
composer --version
```

#### `The zip extension and unzip/7z commands are both missing`

Composer peut continuer en clonant les dependances depuis les sources, mais l'installation est plus lente et plus verbeuse. Installer `php-zip` et `unzip`.

Debian / Ubuntu :

```bash
sudo apt install php-zip unzip
sudo systemctl restart apache2
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install php-zip unzip
sudo systemctl restart httpd
```

Verifier ensuite :

```bash
php -m | grep zip
unzip -v
```

#### `[WARN] Apache service - installed but not active`

Apache est installe, mais le service n'est pas demarre. Le site retournera souvent `connection refused` tant que le service est arrete.

Debian / Ubuntu :

```bash
sudo systemctl enable --now apache2
sudo systemctl status apache2
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo systemctl enable --now httpd
sudo systemctl status httpd
```

Verifier ensuite que le port HTTP ecoute :

```bash
sudo ss -ltnp | grep ':80'
```

#### `[WARN] MariaDB/MySQL client - not found in PATH`

Ce warning indique que la commande `mysql` ou `mariadb` n'est pas disponible dans le terminal. Ce n'est pas bloquant si la base est distante ou geree autrement, mais c'est utile pour tester la connexion.

Debian / Ubuntu :

```bash
sudo apt install mariadb-client
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install mariadb
```

#### `Failed to enable unit: Unit mariadb.service does not exist`

Cette erreur signifie generalement que le serveur MariaDB n'est pas installe, ou que l'installation des paquets a echoue avant d'installer `mariadb-server`.

Verifier le paquet :

```bash
rpm -q mariadb-server
```

Si le paquet n'est pas installe :

```bash
sudo dnf install -y mariadb-server
```

Verifier ensuite que le service existe :

```bash
systemctl list-unit-files | grep -E 'mariadb|mysql'
```

Puis activer MariaDB :

```bash
sudo systemctl enable --now mariadb
```

#### `copy(composer-setup.php): Failed to open stream: Permission denied`

Cette erreur apparait quand l'installateur Composer est telecharge depuis un dossier non inscriptible par l'utilisateur courant, par exemple `/var/www/html/msm`.

Relancer l'installation depuis `/tmp` :

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
rm composer-setup.php
composer --version
```

Puis revenir dans le projet :

```bash
cd /var/www/html/msm
```

#### `[WARN] Local config .env - missing`

Normal juste apres le clone. Creer la configuration locale :

```bash
cp .env.example .env
```

Puis renseigner les variables MariaDB et `MSM_SECRET_KEY`.

#### `[WARN] logs directory - exists but is not writable by the current user`

Le dossier `logs/` doit etre accessible en ecriture par l'utilisateur qui lance les scripts, et par l'utilisateur Apache en production.

Debian / Ubuntu :

```bash
sudo chown -R www-data:www-data logs
sudo chmod -R 750 logs
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo chown -R apache:apache logs
sudo chmod -R 750 logs
```

Pendant une installation manuelle, si le script est lance avec votre utilisateur courant, il est aussi possible de donner temporairement les droits a cet utilisateur, puis d'ajuster les droits pour Apache avant la mise en service.

#### `detected dubious ownership in repository`

Git refuse d'utiliser un depot dont le proprietaire ne correspond pas a l'utilisateur courant.

Si le projet est bien celui que vous venez de cloner dans `/var/www/html/msm`, declarer ce dossier comme sur :

```bash
git config --global --add safe.directory /var/www/html/msm
```

#### `/var/www/html/msm/vendor does not exist and could not be created`

Composer ne peut pas creer le dossier `vendor/` car l'utilisateur courant n'a pas les droits d'ecriture sur le dossier projet.

Pendant l'installation, donner le projet a l'utilisateur courant :

```bash
sudo chown -R "$USER":"$USER" /var/www/html/msm
```

Puis relancer :

```bash
composer install --no-dev --optimize-autoloader
```

## 3. Installer les dependances PHP du projet

Si le projet est clone dans `/var/www/html`, verifier que l'utilisateur courant peut ecrire dans le dossier projet avant de lancer Composer :

```bash
pwd
ls -ld .
```

Si le dossier appartient a `root`, a `apache` ou a un autre utilisateur, corriger temporairement les droits pour l'utilisateur qui fait l'installation :

```bash
sudo chown -R "$USER":"$USER" /var/www/html/msm
git config --global --add safe.directory /var/www/html/msm
```

Cette commande permet a Composer de creer le dossier `vendor/` et evite l'erreur Git `detected dubious ownership`.

Installer ensuite les dependances PHP :

```bash
composer install --no-dev --optimize-autoloader
```

Pour une installation de developpement locale, `composer install` suffit.

## 4. Creer la base de donnees

Verifier que MariaDB est demarre :

```bash
sudo systemctl status mariadb
```

Entrer dans le client MariaDB en administrateur :

```bash
sudo mariadb
```

Le prompt change et affiche quelque chose comme :

```text
MariaDB [(none)]>
```

Executer ensuite les commandes SQL suivantes :

```sql
CREATE DATABASE msm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'msm'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON msm.* TO 'msm'@'localhost';
FLUSH PRIVILEGES;
```

Remplacer `change-me` par un mot de passe local solide.

Quitter le client MariaDB :

```sql
EXIT;
```

Tester la connexion avec l'utilisateur cree :

```bash
mariadb -u msm -p msm
```

Entrer le mot de passe choisi. Si le prompt MariaDB s'ouvre, la base et l'utilisateur sont corrects.

Quitter ensuite :

```sql
EXIT;
```

## 5. Creer la configuration locale

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

## 6. Appliquer les migrations

```bash
php apply_migrations.php
```

Le script cree automatiquement la table `migrations_applied` si elle n'existe pas.

## 7. Verifier les permissions

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

## 8. Configurer Apache

MSM peut fonctionner dans un sous-dossier, par exemple :

```text
http://msm.example.local/msm/
```

L'endpoint Prometheus par defaut ne depend pas de `mod_rewrite` :

```text
http://msm.example.local/msm/metrics.php
```

Les fichiers sensibles `.env`, `.key` et `.pem` sont bloques par `.htaccess` quand Apache autorise les fichiers `.htaccess`.

MSM detecte automatiquement s'il est installe a la racine du vhost ou dans un sous-dossier comme `/msm/`. Les liens du menu, les formulaires et les assets doivent donc fonctionner dans les deux cas.

### Attention au pare-feu

Le serveur qui heberge MSM doit autoriser le trafic HTTP ou HTTPS entrant selon la configuration choisie :

- HTTP : port `80/tcp` ;
- HTTPS : port `443/tcp`.

Exemple avec `firewalld` sur RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

Exemple avec `ufw` sur Debian / Ubuntu :

```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

Si MSM doit tester des serveurs distants, verifier aussi que les flux sortants necessaires sont autorises depuis le serveur MSM, par exemple ICMP pour le ping et `22/tcp` pour SSH.

## 9. Configurer le check planifie

MSM ne lance pas les checks lourds au chargement des pages. Les statuts doivent etre mis a jour par un script planifie :

```bash
php scripts/check-servers.php
```

Ce script met a jour les derniers resultats connus en base. Les pages MSM et l'endpoint Prometheus lisent ensuite ces donnees.

### Verifier le script manuellement

Depuis la racine du projet :

```bash
cd /var/www/html/msm
php scripts/check-servers.php
```

Si cette commande retourne une erreur, corriger l'erreur avant de configurer cron.

### Verifier le chemin PHP

Cron doit utiliser le chemin complet de PHP :

```bash
which php
```

Exemple courant :

```text
/usr/bin/php
```

### Preparer le fichier de log

Creer le dossier `logs/` s'il n'existe pas et verifier les droits :

```bash
mkdir -p /var/www/html/msm/logs
touch /var/www/html/msm/logs/check-servers.log
```

Si le cron est execute avec l'utilisateur courant, cet utilisateur doit pouvoir ecrire dans `logs/`. Si le cron est execute avec l'utilisateur Apache, adapter les droits comme indique dans l'etape 7.

### Installer et activer cron si necessaire

Debian / Ubuntu :

```bash
sudo apt install -y cron
sudo systemctl enable --now cron
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install -y cronie
sudo systemctl enable --now crond
```

### Ajouter la tache cron

Editer la crontab de l'utilisateur qui doit lancer les checks :

```bash
crontab -e
```

Ajouter une execution toutes les minutes :

```cron
* * * * * /usr/bin/php /var/www/html/msm/scripts/check-servers.php >> /var/www/html/msm/logs/check-servers.log 2>&1
```

Le script respecte ensuite l'intervalle configure dans MSM, par exemple `check_interval_minutes`. Il est donc normal de lancer cron toutes les minutes meme si MSM ne fait un vrai check que toutes les 5 ou 10 minutes selon la configuration.

Adapter les chemins selon l'installation :

- remplacer `/usr/bin/php` par le resultat de `which php` ;
- remplacer `/var/www/html/msm` par le dossier reel du projet.

### Verifier l'execution planifiee

Attendre une ou deux minutes, puis consulter le log :

```bash
tail -n 50 /var/www/html/msm/logs/check-servers.log
```

Verifier aussi la page diagnostic MSM : elle doit afficher un dernier check coherent.

## 10. Verifications post-install

Verifier d'abord que le serveur contient bien la derniere version recuperee :

```bash
cd /var/www/html/msm
git pull
git rev-parse --short HEAD
```

Ouvrir :

```text
http://msm.example.local/msm/pages/diagnostic.php
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
http://msm.example.local/msm/metrics.php
```

La page doit retourner du texte au format Prometheus. Exemples de lignes attendues :

```text
msm_server_up{server="server-01",hostname="server-01.example.local"} 1
msm_ssh_ok{server="server-01",hostname="server-01.example.local"} 1
msm_server_last_check_timestamp{server="server-01",hostname="server-01.example.local"} 1780000000
```

Depuis le serveur, il est aussi possible de tester :

```bash
curl -s http://localhost/msm/metrics.php | head
```

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
