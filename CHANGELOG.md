# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## [0.10.0](https://github.com/grandpurs45/my-server-manager/compare/v0.9.0...v0.10.0) (2025-07-05)


### Features

* CHiffrement AES des mots de passe en base de données ([c378d41](https://github.com/grandpurs45/my-server-manager/commit/c378d41a49190de080babdfdd97ddf6c5ad8d12c))

## [0.9.0](https://github.com/grandpurs45/my-server-manager/compare/v0.8.0...v0.9.0) (2025-07-04)


### Features

* ajout readme ([40592c9](https://github.com/grandpurs45/my-server-manager/commit/40592c9cd8f8fdccfa80001fa3c77b60468728d6))
* **supervision:** creation de la page de supervision, affichage en mode card ([c47581f](https://github.com/grandpurs45/my-server-manager/commit/c47581f16b363b58a9dae480836fd7a4c2182cec))
- Supervision des serveurs en temps réel (ping)
- Enregistrement de la latence et du statut UP/DOWN en base
- Table `server_metrics` pour historiser les mesures
- Bouton manuel de mise à jour des statuts
- Affichage des dernières latences dans les cartes
- Code de ping compatible Windows/Linux avec parsing amélioré

## [0.8.0](https://github.com/grandpurs45/my-server-manager/compare/v0.6.1...v0.8.0) (2025-07-03)


### Features

* Ajout du mode debug (affiche les erreurs php) ([eccf3ca](https://github.com/grandpurs45/my-server-manager/commit/eccf3ca6bf273a074ca037c25d45e7b7e823ae4f))
* ajout fonctione debug dans les paramètres ([90667ee](https://github.com/grandpurs45/my-server-manager/commit/90667ee7409f76bba59cb8157aa7f714a3d89352))
* creation de la page settings et de la classe SettingsManager ([ebaf13c](https://github.com/grandpurs45/my-server-manager/commit/ebaf13cc9a8f843646393d559984db4c24513dd1))
* Détection automatique de l’OS lors de l’ajout/modification d’un serveur ([2ad5d34](https://github.com/grandpurs45/my-server-manager/commit/2ad5d34e2f1fcb9cb06d26aec9c5c767f99e4713))


### Bug Fixes

* ajout colonne status dans bdd qui manquait en prod ([ab43156](https://github.com/grandpurs45/my-server-manager/commit/ab4315654bbee677c199c01bd18e6fedeb41f91f))

### [0.7.3](https://github.com/grandpurs45/my-server-manager/compare/v0.6.1...v0.7.3) (2025-06-30)


### Features

* Détection automatique de l’OS lors de l’ajout/modification d’un serveur ([9aca3eb](https://github.com/grandpurs45/my-server-manager/commit/9aca3eb64656951458684b3ca5571e837007b0ee))
- Détection automatique de l'OS à l'ajout/modification d'un serveur :
  - Linux : via `/etc/os-release`
  - Windows : via PowerShell `(Get-CimInstance Win32_OperatingSystem).Caption`
  - Fallback : commande `ver` pour les cas minimaux
- Affichage du nom complet de l’OS détecté
- Ajout automatique du logo OS (Debian, Ubuntu, Windows, Unknown, etc.)
- Gestion des connexions SSH via phpseclib3 avec timeout et logs
- Création d’un fichier `ssh-debug.log` dans `/logs/` pour le débogage SSH
- Affichage combiné possible des messages `success` et `error` à l’écran
- Ping initial et statut SSH mis à jour automatiquement à la modification

### Modifié
- Suppression du fichier `edit-server.php` (fusionné dans `serveurs.php`)
- `functions.php` : fonction `isHostUp()` plus robuste et compatible Windows/Linux
- Amélioration de l’apparence visuelle (logos, icônes statuts, messages utilisateurs)
- Refactorisation de la classe `SSHUtils` avec timeout, logs, fallback

### Corrigé
- [#0002] Formulaire non réinitialisé après modification
- Chargement infini de la page lié au div `#loading`
- Echec silencieux de `phpseclib` en cas de nom d’hôte non résolu (`.lan`)

### À venir
- Ping initial à l’ajout du serveur (non encore automatisé)
- Bouton manuel "Mettre à jour le statut"
- Suffixe DNS personnalisable dans les paramètres
- Blocage des doublons lors de l’ajout (hostname/nom identique)


### Bug Fixes

* ajout colonne status dans bdd qui manquait en prod ([ab43156](https://github.com/grandpurs45/my-server-manager/commit/ab4315654bbee677c199c01bd18e6fedeb41f91f))

### [0.6.1](https://github.com/grandpurs45/my-server-manager/compare/v0.6.0...v0.6.1) (2025-06-23)


### Bug Fixes

* correction du retour du ping sur windows ([d8875fd](https://github.com/grandpurs45/my-server-manager/commit/d8875fd1582b7bfe29b2e271ec00f314e7c08d15))

## [0.6.0](https://github.com/grandpurs45/my-server-manager/compare/v0.5.2...v0.6.0) (2025-06-23)


### Features

* transition vers stockage BDD des statuts et amélioration UX ([248b6ab](https://github.com/grandpurs45/my-server-manager/commit/248b6ab6e60c9e1694987a124894a91c95283831))

### [0.5.2](https://github.com/grandpurs45/my-server-manager/compare/v0.5.1...v0.5.2) (2025-06-23)


### Bug Fixes

* sauvegarde des données en cas de modifications BUG-003 ([844a9f7](https://github.com/grandpurs45/my-server-manager/commit/844a9f76ef4fd84de7787254c37c748fab524690))

### [0.5.1](https://github.com/grandpurs45/my-server-manager/compare/v0.5.0...v0.5.1) (2025-06-23)


### Features

* ajout de composer et de phpseclib ([afa55e4](https://github.com/grandpurs45/my-server-manager/commit/afa55e4fd73e28b3ce677535244791645f8008f5))
* ajout du support SSH et refonte formulaire serveur ([8985988](https://github.com/grandpurs45/my-server-manager/commit/8985988f1dad65eea09a85e4e8ce19eacd53c3e5))
* refonte du code pour passer en POO ([f2548ba](https://github.com/grandpurs45/my-server-manager/commit/f2548ba58b6c63a63bf582d8ba27d4b6e681d043))


### Bug Fixes

* reset du formulaire d'ajout après modification BUG-002 ([a778b88](https://github.com/grandpurs45/my-server-manager/commit/a778b8850d9f1b538e8fc79eb90394b2c9532a9d))

## [0.5.0](https://github.com/grandpurs45/my-server-manager/compare/v0.4.1...v0.5.0) (2025-06-22)


### Features

* amelioration du ping qui ne se fait plus au chargement de la page mais après ([b02347d](https://github.com/grandpurs45/my-server-manager/commit/b02347de153a09d4eba33d1ee9d6a02686186862))
* amelioration du spinner ([d85939d](https://github.com/grandpurs45/my-server-manager/commit/d85939d2c37a3c90bbe6c432e33d97861d2cd15a))
* ✨ Ajout du support SSH à l’ajout de serveur
* 🧠 Refonte partielle vers une structure orientée objet
* 🖼️ Modale d’ajout améliorée (design Tailwind + édition dynamique)
* 🗂️ Démarrage du registre de bugs connus

### Bug Fixes

* 🪲 Correctifs divers (nom/IP vides, statut modale en édition…)


### [0.4.1](https://github.com/grandpurs45/my-server-manager/compare/v0.4.0...v0.4.1) (2025-06-22)


### Features

* **migrations:** création de la table servers ([948c612](https://github.com/grandpurs45/my-server-manager/commit/948c6120b12fb02ae45409b0fbbc5e9fe5f1e3d5))


### Bug Fixes

* bug chargement serveur en production ([a2de778](https://github.com/grandpurs45/my-server-manager/commit/a2de778ea75389731eeb0c7f6adc158c3eeacc7d))
* correction du bug lorsqu'aucun serveur n'existe ([aae6a7f](https://github.com/grandpurs45/my-server-manager/commit/aae6a7fa710e8e8076d1ef104b93e4e263a1e6ab))
* correction du bug lorsqu'aucun serveur n'existe ([8d9c030](https://github.com/grandpurs45/my-server-manager/commit/8d9c0307225ef99adfa8c7ebc742207e336c2710))
* correction du bug lorsqu'aucun serveur n'existe ([b1e2d99](https://github.com/grandpurs45/my-server-manager/commit/b1e2d992f130ec7b04e1fc58565565c21bfaa2a6))
* correction du bug lorsqu'aucun serveur n'existe ([c9a9d1b](https://github.com/grandpurs45/my-server-manager/commit/c9a9d1b16a9b7b9c7ba468a6f90af273d46f4a15))
* rename tmp_migrations to migrations (lowercase) ([e4da177](https://github.com/grandpurs45/my-server-manager/commit/e4da177ade53aa89aea021e8051ee0d89d520573))
* renommage repertoire Migrations vers migrations pour la cohérence ([24f9bd1](https://github.com/grandpurs45/my-server-manager/commit/24f9bd17f6a5a7a934acc9f2cad4499aecae7c12))

## [0.4.0](https://github.com/grandpurs45/my-server-manager/compare/v0.3.0...v0.4.0) (2025-06-22)


### Features

* ajout lien serveur dans le menu de gauche ([c3f7365](https://github.com/grandpurs45/my-server-manager/commit/c3f736568b7d014f74dbb9ed4b7834a659eb21d7))
* processus de migration des modifs de bases de données ([7669d25](https://github.com/grandpurs45/my-server-manager/commit/7669d25979dcd4aaa8235bc8a436ec6bd44f5250))

## [0.3.0](https://github.com/grandpurs45/my-server-manager/compare/v0.2.3...v0.3.0) (2025-06-22)

### [0.2.3](https://github.com/grandpurs45/my-server-manager/compare/v0.2.2...v0.2.3) (2025-06-22)


### Features

* supervision réseau (UP/DOWN) + spinner visuel ([fea514b](https://github.com/grandpurs45/my-server-manager/commit/fea514bfe2a3bce7c10f5e02fd81f487e1ce35e5))

### [0.2.2](https://github.com/grandpurs45/my-server-manager/compare/v0.2.1...v0.2.2) (2025-06-22)


### Features

* boutton annulation sur le modal d'ajout et de modification du serveur ([c1a4839](https://github.com/grandpurs45/my-server-manager/commit/c1a4839ec47beac55070bb64c0a5c685f66ec5b2))
* modification d'un serveur ([edd6f26](https://github.com/grandpurs45/my-server-manager/commit/edd6f26c018618845f56826a72f6d08693cea138))


### Bug Fixes

* correction lors de l'annulation d'une modification de serveur qui laissait sur l'url de modification du serveur ([bdb218a](https://github.com/grandpurs45/my-server-manager/commit/bdb218a910362df7b629dd4b7477ee82be6cd58b))

### [0.2.1](https://github.com/grandpurs45/my-server-manager/compare/v0.1.0...v0.2.1) (2025-06-22)


### Features

* affichage des serveurs depuis la base de données ([3c27126](https://github.com/grandpurs45/my-server-manager/commit/3c27126d4532a8c603aa89721f0dd7a96c1cc4a9))


## [0.2.0] - 2025-06-21
### Ajouté
- Page de gestion dynamique des serveurs (affichage + ajout)
- Connexion à la base de données via PDO
- Détection et affichage de l’état `UP`
- Design et intégration avec Tailwind

## [0.1.0] - 2025-06-19
### Added
- Initialisation du projet, configuration Apache et Git
