# Installation vierge MSM

Ce guide decrit une installation neuve de My Server Manager sur un serveur Apache/PHP avec MariaDB.

Pour une instance deja installee, utiliser le guide de mise a jour : [UPDATE.md](UPDATE.md).

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
- 5 Go d'espace disque libre sur la partition qui heberge MSM, apres installation de l'OS et des dependances.
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

Sur une installation Linux vierge, installer d'abord le minimum necessaire pour recuperer le projet et lancer le script setup.

Debian / Ubuntu :

```bash
sudo apt update
sudo apt install -y php-cli git
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
sudo dnf install -y php-cli git
```

Une fois le projet clone, MSM peut afficher les commandes adaptees a la distribution pour installer le reste des dependances :

```bash
php scripts/setup.php --install-deps
```

Pour les executer automatiquement apres verification :

```bash
php scripts/setup.php --install-deps --yes
```

Sans `--yes`, aucune commande systeme n'est executee.

Installation manuelle equivalente :

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

Ces commandes installent les dependances systeme. Les dependances PHP du projet sont ensuite installees a l'etape 3.

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

Un warning sur `.env` ou `logs/` est normal a cette etape si l'installation vient juste d'etre clonee. Le script affiche les actions recommandees a executer ensuite.

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

`scripts/setup.php` complete ce controle avec :

- la detection du chemin reel du projet ;
- la generation du bloc cron recommande ;
- la verification de la crontab chargee quand `crontab` est disponible ;
- la detection d'anciennes redirections vers `/var/log/msm-check-*` ;
- la verification des fichiers de logs attendus dans `logs/` ;
- la connexion base et le nombre de migrations appliquees.

Options utiles :

- `--init-env` : cree `.env` depuis `.env.example` si le fichier est absent ;
- `--init-logs` : cree `logs/` et les fichiers `check-*.log` attendus ;
- `--db-sql` : affiche les commandes SQL de creation de base et d'utilisateur ;
- `--migrate` : lance explicitement `apply_migrations.php` ;
- `--install-deps` : affiche les commandes d'installation des dependances systeme ;
- `--composer-install` : installe les dependances PHP du projet avec Composer ;
- `--cron` : affiche uniquement le bloc cron recommande.
- `--systemd` : affiche les fichiers `.service` et `.timer` systemd recommandes.

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

<details>
<summary>Erreurs frequentes du script de prerequis</summary>

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

Composer ne peut pas creer le dossier `vendor/` car l'utilisateur qui lance Composer n'a pas les droits d'ecriture sur le dossier projet.

Ne pas utiliser `"$USER"` si le shell est connecte directement en `root` : dans ce cas, `$USER` vaut `root` et la commande ne change rien. Choisir explicitement l'utilisateur qui gere le deploiement et le groupe du serveur web.

Debian / Ubuntu :

```bash
APP_OWNER=<utilisateur_deploiement>
WEB_GROUP=www-data
sudo chown -R "$APP_OWNER":"$WEB_GROUP" /var/www/html/msm
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
APP_OWNER=<utilisateur_deploiement>
WEB_GROUP=apache
sudo chown -R "$APP_OWNER":"$WEB_GROUP" /var/www/html/msm
```

Le code applicatif ne doit pas etre donne en ecriture a l'utilisateur Apache. Seul `logs/` doit etre inscriptible par l'utilisateur qui execute les checks ou par le serveur web.

</details>

## 3. Installer les dependances PHP du projet

Si le projet est clone dans `/var/www/html`, verifier que l'utilisateur courant peut ecrire dans le dossier projet avant de lancer Composer :

```bash
pwd
ls -ld .
```

Si le dossier appartient a `root`, a `apache` ou a un autre utilisateur, corriger les droits avec un proprietaire applicatif explicite.

Debian / Ubuntu :

```bash
APP_OWNER=<utilisateur_deploiement>
WEB_GROUP=www-data
sudo chown -R "$APP_OWNER":"$WEB_GROUP" /var/www/html/msm
git config --global --add safe.directory /var/www/html/msm
```

RHEL / Rocky Linux / AlmaLinux / Fedora :

```bash
APP_OWNER=<utilisateur_deploiement>
WEB_GROUP=apache
sudo chown -R "$APP_OWNER":"$WEB_GROUP" /var/www/html/msm
git config --global --add safe.directory /var/www/html/msm
```

Cette commande permet a Composer de creer le dossier `vendor/` et evite l'erreur Git `detected dubious ownership`. Remplacer `<utilisateur_deploiement>` par un vrai utilisateur Linux, par exemple l'utilisateur avec lequel vous administrez la machine. Ne pas mettre `apache` ou `www-data` comme proprietaire du code applicatif.

Installer ensuite les dependances PHP du projet :

```bash
php scripts/setup.php --composer-install
```

Equivalent manuel :

```bash
composer install --no-dev --optimize-autoloader
```

Pour une installation de developpement locale, `composer install` suffit.

## 4. Creer la configuration locale et la base

Creer `.env` depuis le modele du projet :

```bash
php scripts/setup.php --init-env
```

Generer ensuite les commandes SQL adaptees a MSM :

```bash
php scripts/setup.php --db-sql
```

Le script affiche :

- les commandes SQL a executer dans MariaDB/MySQL ;
- les valeurs `MSM_DB_NAME`, `MSM_DB_USER` et `MSM_DB_PASS` a reporter dans `.env`.

> [!WARNING]
> **ATTENTION : si le mot de passe affiche est `CHANGE_ME_STRONG_PASSWORD`, remplacer cette valeur par un mot de passe fort dans la commande SQL ET dans `.env`.**

Entrer ensuite dans MariaDB/MySQL avec un compte administrateur :

```bash
sudo mariadb
```

ou, selon la distribution :

```bash
mysql -u root -p
```

Executer les commandes SQL affichees par `--db-sql`, puis quitter le client SQL :

```sql
EXIT;
```

Editer enfin `.env` et reporter les valeurs indiquees par `--db-sql`.

Important : conserver `MSM_SECRET_KEY`. Elle sert au chiffrement des mots de passe SSH stockes en base.

## 5. Appliquer les migrations

```bash
php scripts/setup.php --migrate
```

Le script cree automatiquement la table `migrations_applied` si elle n'existe pas.

## 6. Verifier les permissions

Le serveur web doit pouvoir lire :

- le code applicatif ;
- `.env` ;
- `vendor/`.

Le serveur web doit pouvoir ecrire dans :

- `logs/`.

Creer le dossier et les fichiers de logs attendus :

```bash
php scripts/setup.php --init-logs
```

Exemple d'ajustement de permissions pour Debian / Ubuntu :

```bash
sudo chown -R www-data:www-data logs
sudo chmod -R 750 logs
```

Adapter l'utilisateur Apache selon la distribution.

## 7. Configurer Apache

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

## 8. Configurer le check planifie

MSM ne lance pas les checks lourds au chargement des pages. Les statuts doivent etre mis a jour par des scripts planifies.

La documentation complete d'ordonnancement est disponible ici :

```text
docs/SCHEDULING.md
```

Elle couvre :

- cron ;
- systemd timers ;
- logs ;
- frequences conseillees ;
- scripts `check-servers.php`, `check-patches.php`, `check-os-lifecycle.php` ;
- option `--force`.

Verification minimale de supervision :

```bash
php scripts/check-servers.php --force
```

Les pages MSM et l'endpoint Prometheus lisent ensuite les derniers resultats connus en base.

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
touch /var/www/html/msm/logs/check-{servers,patches,os-lifecycle,security,alerts}.log
chmod 775 /var/www/html/msm/logs
```

Si le cron est execute avec l'utilisateur courant, cet utilisateur doit pouvoir ecrire dans `logs/`. Si le cron est execute avec l'utilisateur Apache, adapter les droits comme indique dans l'etape 7.

Eviter `/var/log` pour une crontab utilisateur, sauf si les fichiers ont ete crees avec les bons proprietaires et permissions. Une redirection impossible empeche le lancement du script PHP.

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

Ajouter les checks planifies :

```cron
* * * * * /usr/bin/php /var/www/html/msm/scripts/check-servers.php >> /var/www/html/msm/logs/check-servers.log 2>&1
*/10 * * * * /usr/bin/php /var/www/html/msm/scripts/check-patches.php >> /var/www/html/msm/logs/check-patches.log 2>&1
15 * * * * /usr/bin/php /var/www/html/msm/scripts/check-os-lifecycle.php >> /var/www/html/msm/logs/check-os-lifecycle.log 2>&1
30 * * * * /usr/bin/php /var/www/html/msm/scripts/check-security.php >> /var/www/html/msm/logs/check-security.log 2>&1
*/5 * * * * /usr/bin/php /var/www/html/msm/scripts/check-alerts.php >> /var/www/html/msm/logs/check-alerts.log 2>&1
```

Chaque script respecte ensuite l'intervalle configure dans MSM. Par exemple, le cron securite appelle le script toutes les heures a la minute 30, mais l'analyse complete n'est executee par defaut que toutes les 24 heures. Les appels intermediaires sont journalises comme `saute`.

Adapter les chemins selon l'installation :

- remplacer `/usr/bin/php` par le resultat de `which php` ;
- remplacer `/var/www/html/msm` par le dossier reel du projet.

### Verifier l'execution planifiee

Attendre une ou deux minutes, puis consulter le log :

```bash
tail -n 50 /var/www/html/msm/logs/check-servers.log
```

Verifier aussi la page diagnostic MSM : elle doit afficher un dernier check coherent.

Pour une configuration complete, suivre [SCHEDULING.md](SCHEDULING.md).

## 9. Verifications post-install

Verifier d'abord que le serveur contient bien la derniere version recuperee :

```bash
cd /var/www/html/msm
git pull
git rev-parse --short HEAD
```

Verifier ensuite l'installation complete cote CLI :

```bash
php scripts/setup.php
```

Le resultat attendu est zero `FAIL`. Les eventuels `WARN` doivent etre compris et justifies par l'environnement.

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
msm_server_up{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_ssh_ok{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_server_last_check_timestamp{server="server-01",hostname="server-01.example.local",type="linux"} 1780000000
```

Depuis le serveur, il est aussi possible de tester :

```bash
curl -s http://localhost/msm/metrics.php | head
```

## Mise a jour depuis une ancienne installation

La procedure complete de mise a jour est decrite dans [UPDATE.md](UPDATE.md).

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
