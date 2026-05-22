 My Server Manager (MSM)


![GitHub tag (latest)](https://img.shields.io/github/v/tag/grandpurs45/my-server-manager)

**MSM** est une application web de supervision et de gestion de serveurs Linux et Windows, développée en PHP avec une interface simple et efficace.

## 🚀 Fonctionnalités actuelles

- 📋 **Gestion des serveurs** : ajout, modification, statut UP/DOWN, détection OS (via SSH)
- 🛠️ **Supervision** : vérification régulière de l’état des serveurs (ping)
- ⚙️ **Paramètres dynamiques** : suffixe DNS, mode debug, etc.
- 🐞 **Mode debug** : activable depuis l’interface pour afficher les erreurs PHP
- 📦 **Système de migrations SQL** : versionnage propre des évolutions de la base
- 🎨 **Interface responsive** avec Tailwind CSS

## 📁 Structure du projet

```
msm/
├── autoloader.php
├── apply_migrations.php
├── includes/
│   ├── bootstrap.php
│   ├── db.php
│   ├── header.php / footer.php
├── classes/
│   ├── SettingsManager.php
│   └── SSHUtils.php
├── config/
│   └── settings-schema.php
├── pages/
│   ├── settings.php
│   ├── serveurs.php
├── scripts/
│   └── check-servers.php
├── migrations/
│   └── YYYY-MM-DD-description.sql
```

## ⚙️ Installation

1. Clone ce repo :
   ```bash
   git clone https://github.com/ton-user/msm.git
   ```

2. Configure la base de données (MariaDB), puis :
   ```bash
   php apply_migrations.php
   ```

3. Lancer en local avec XAMPP ou un serveur Apache/PHP
4. Accéder à l’application via `http://localhost/msm/`

## Export Prometheus

MSM expose un endpoint Prometheus en texte brut :

```text
http://localhost/msm/metrics
```

Si la reecriture Apache n'est pas active, utiliser directement :

```text
http://localhost/msm/metrics.php
```

Les metriques exposees par cette premiere version viennent uniquement de la base MSM. Le endpoint `/metrics` ne lance pas de ping, SSH ou analyse distante afin de rester rapide et compatible avec un scrape Prometheus regulier.

Exemple de sortie :

```text
# HELP msm_server_up Last known server reachability status from MSM.
# TYPE msm_server_up gauge
msm_server_up{server="srv-docker",hostname="srv-docker.lan"} 1
msm_ssh_ok{server="srv-docker",hostname="srv-docker.lan"} 1
msm_server_latency_ms{server="srv-docker",hostname="srv-docker.lan"} 4
```

## 🧠 Technologies utilisées

- PHP 8+
- MariaDB
- Tailwind CSS
- phpseclib (connexion SSH)
- Composer (autoload)
- Prometheus / Grafana (export de metriques)

## 📌 TODO / Roadmap

- Supervision CPU / RAM / disque
- Système d’alertes (mail / Discord)
- Sécurité des sites web (headers, HSTS…)
- Authentification utilisateur (mode admin)
- API REST pour les serveurs

## 🤝 Contribuer

Ce projet est en cours de développement, merci pour votre compréhension !

## 📄 Licence

Projet libre, sous licence MIT.
