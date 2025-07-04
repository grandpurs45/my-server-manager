 My Server Manager (MSM)

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

## 🧠 Technologies utilisées

- PHP 8+
- MariaDB
- Tailwind CSS
- phpseclib (connexion SSH)
- Composer (autoload)

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