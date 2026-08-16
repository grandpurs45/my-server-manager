# Integrations API

Le module `Integrations API` ajoute un moteur generique de connexion, de decouverte et de collecte pour les equipements disposant d'une API locale.

Le premier connecteur disponible cible les equipements Reolink compatibles CGI, avec le Home Hub Pro comme plateforme de validation initiale.

## Principe

Une integration suit ce cycle :

1. creation de la source et stockage chiffre des identifiants ;
2. test DNS, TCP/TLS, HTTP et authentification ;
3. decouverte des ressources et metriques ;
4. selection des metriques utiles et de leur frequence ;
5. activation de la source ;
6. collecte et redecouverte planifiees par `scripts/check-api-integrations.php`.

La page Web ne lance aucune collecte periodique. Elle ne contacte la source que lorsque l'utilisateur clique explicitement sur `Tester` ou `Decouvrir`.

## Ajouter un Home Hub Reolink

Depuis `Infrastructure > Integrations API` :

1. cliquer sur `Nouvelle source` ;
2. choisir le connecteur `Reolink` ;
3. saisir uniquement l'adresse IP ou le nom DNS, sans `http://` ni chemin ;
4. choisir HTTP ou HTTPS et le port configure dans Reolink ;
5. renseigner un compte Reolink dedie en lecture quand les droits du firmware le permettent ;
6. conserver la verification TLS pour un certificat reconnu, ou la desactiver uniquement pour un certificat local auto-signe ;
7. enregistrer, puis cliquer sur `Tester` ;
8. cliquer sur `Decouvrir` ;
9. verifier les ressources, choisir les metriques et les intervalles ;
10. activer la source.

Les identifiants et mots de passe ne sont jamais reaffiches. Laisser ces deux champs vides lors d'une modification conserve les valeurs chiffrees existantes.

## Ressources Reolink detectees

Le connecteur interroge actuellement :

- `Login` et `Logout` pour la session API ;
- `GetDevInfo` pour l'identite du hub ;
- `GetChannelstatus` pour les canaux et leur etat ;
- `GetHddInfo` pour les stockages ;
- `GetBatteryInfo` pour chaque canal detecte.

Les commandes optionnelles non supportees par le firmware sont ignorees sans invalider toute la decouverte.

Les ressources disposent d'un identifiant externe stable : numero de serie du hub quand il est disponible, UID de camera, ou empreinte du canal en dernier recours. Une nouvelle decouverte met a jour les ressources existantes au lieu de les dupliquer.

Toutes les ressources restent rattachees a leur source API. Dans l'interface, il faut donc ouvrir l'hote Reolink concerne pour voir son hub, ses cameras et ses stockages. Plusieurs sources API ne melangent pas leurs ressources.

## Redecouverte automatique

Chaque source possede une frequence de redecouverte distincte de la frequence des metriques. La valeur par defaut est de 60 minutes et peut etre modifiee dans la configuration de l'hote API.

Quand une nouvelle camera est ajoutee au hub :

1. le collecteur la detecte au prochain passage de redecouverte ;
2. MSM l'ajoute automatiquement dans les ressources de cet hote ;
3. ses metriques restent desactivees jusqu'a leur validation dans l'interface ;
4. une ressource disparue est marquee absente sans etre rattachee a une autre source.

Le bouton `Decouvrir` permet de declencher immediatement le meme inventaire. Une source desactivee n'est pas redecouverte automatiquement.

## Consulter les valeurs

La fiche d'une source affiche, pour chaque metrique :

- la derniere valeur connue et sa date de collecte ;
- les dix derniers echantillons enregistres dans un volet d'historique ;
- la frequence de collecte et son etat d'activation.

L'historique commence apres les passages du collecteur planifie. Une valeur issue de la seule decouverte peut donc etre visible avant que le premier echantillon historique ne soit cree.

## Donnees brutes et normalisees

MSM conserve la valeur brute et la valeur normalisee des metriques. Les types normalises utilises dans ce premier lot sont notamment `BOOLEAN`, `INTEGER`, `TEXT`, `PERCENTAGE`, `TEMPERATURE`, `VOLTAGE`, `CURRENT`, `BYTES` et `ENUM`.

Points encore a confirmer sur equipement reel :

- unite exacte des champs `capacity` et `size` de `GetHddInfo` ;
- signification exhaustive des codes de charge et d'adaptateur ;
- signe et unite du courant selon les modeles de cameras ;
- impact de la frequence de collecte sur les cameras sur batterie.

Pour cette raison, les capacites brutes et les codes constructeur restent des metriques desactivees par defaut. Le pourcentage d'occupation est calcule uniquement quand `capacity` et `size` sont coherents.

## Ordonnancement

Afficher la ligne adaptee au chemin reel du projet :

```bash
php scripts/setup.php --cron
```

Ligne type :

```cron
* * * * * /usr/bin/php /var/www/msm/scripts/check-api-integrations.php >> /var/www/msm/logs/check-api-integrations.log 2>&1
```

Le script s'execute chaque minute mais ne contacte que les sources dont une metrique ou la redecouverte est arrivee a echeance.

Test manuel :

```bash
php scripts/check-api-integrations.php --force
tail -n 30 logs/check-api-integrations.log
```

## Securite

- Les secrets sont chiffres avec `MSM_SECRET_KEY`.
- Les reponses brutes ne doivent contenir aucun mot de passe ni jeton de session.
- Le jeton Reolink reste uniquement en memoire pendant un test ou une collecte.
- Les erreurs affichees sont limitees a une categorie et un message court.
- La verification TLS ne doit etre desactivee que sur un reseau maitrise.
- La suppression d'une source efface ses ressources, metriques et echantillons.

## Perimetre du premier lot

Ce lot livre le moteur generique, le connecteur Reolink, la decouverte, la selection de metriques et la collecte planifiee.

Restent prevus pour les iterations suivantes :

- suggestions de regles selon le type de metrique ;
- creation de regles dynamiques avec seuils et temporisations ;
- alertes sur apparition ou disparition de ressources ;
- comparaison detaillee entre deux decouvertes ;
- export Prometheus des metriques API selectionnees ;
- retention automatique des echantillons et donnees brutes expirees ;
- autres connecteurs specialises.
