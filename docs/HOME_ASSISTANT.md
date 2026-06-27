# Connecteur Home Assistant

MSM peut collecter un premier etat Home Assistant via SSH pour les cibles dont le type est `home_assistant`.

## Prerequis

- La cible doit accepter SSH.
- Le module SSH doit etre active sur la fiche cible MSM.
- Le type de cible doit etre `Home Assistant`.
- Le profil materiel peut rester `Machine virtuelle` pour une instance Home Assistant en VM.
- Si la CLI `ha` est disponible dans la session SSH, MSM collecte les informations Home Assistant detaillees.
- Si la CLI `ha` n'est pas disponible, MSM conserve un fallback systeme Linux limite.

Pour une instance existante avec une liste de types personnalisee, ajouter dans `Parametres > Inventaire` :

```text
home_assistant=Home Assistant
```

## Donnees collectees

Selon ce que la cible expose, MSM stocke :

- type d'installation detecte ;
- version Home Assistant Core ;
- derniere version Core connue si disponible ;
- update Core disponible ;
- version Supervisor ;
- update Supervisor disponible ;
- version Home Assistant OS ;
- update OS disponible ;
- OS hote ;
- kernel ;
- statut du check et message d'erreur eventuel.

## Execution

Execution manuelle :

```bash
php scripts/check-home-assistant.php --force
```

Le resultat est visible :

- dans la fiche cible, section `Home Assistant` ;
- sur le dashboard, carte de fraicheur `Home Assistant` ;
- dans `/metrics.php`, via les metriques `msm_home_assistant_*` ;
- dans le fichier `logs/check-home-assistant.log` si le script est lance par cron.

Execution planifiee recommandee :

```cron
*/15 * * * * /usr/bin/php /var/www/html/msm/scripts/check-home-assistant.php >> /var/www/html/msm/logs/check-home-assistant.log 2>&1
```

Pour generer la ligne adaptee au chemin reel de l'installation :

```bash
php scripts/setup.php --cron
```

Le script respecte l'intervalle interne `home_assistant / check_interval_minutes`.

Ne pas configurer cron et systemd timer en double pour ce meme script.

## Limites

- Le connecteur ne modifie pas Home Assistant.
- Aucun upgrade n'est lance par MSM.
- Les installations Home Assistant ne fournissent pas toutes la CLI `ha` via SSH.
- Les alertes dediees Home Assistant sont prevues pour une version v1.x ulterieure.
