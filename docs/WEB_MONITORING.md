# Supervision des URLs

MSM supervise des URLs HTTP et HTTPS sans les confondre avec les serveurs de l inventaire. Une machine peut ainsi heberger plusieurs services web, chacun avec ses propres attentes et son propre intervalle.

## Configuration

La page `Supervision > URLs` permet de definir :

- le nom et l URL ;
- l environnement et la criticite ;
- l intervalle entre deux controles ;
- le timeout ;
- les codes HTTP acceptes, par exemple `200-399` ou `200-299,401` ;
- le suivi des redirections ;
- la verification du certificat TLS ;
- une chaine de contenu attendue facultative ;
- le nombre d echecs consecutifs avant ouverture d une alerte.

Une nouvelle cible est placee immediatement en attente de controle. Une modification ou une reactivation force egalement son prochain passage sans lancer de requete depuis la page web.

## Collecte

Le script dedie est :

```bash
php scripts/check-web.php
```

Pour controler immediatement toutes les URLs actives :

```bash
php scripts/check-web.php --force
```

Le collecteur stocke le dernier code HTTP, les temps DNS, connexion, TLS, premier octet et total, l URL finale, le nombre de redirections, le resultat du contenu attendu et la date d expiration du certificat quand cURL la fournit.

Le script doit etre appele chaque minute. Il selectionne uniquement les cibles dont l echeance est atteinte :

```cron
* * * * * /usr/bin/php /var/www/html/msm/scripts/check-web.php >> /var/www/html/msm/logs/check-web.log 2>&1
```

Le chemin exact est genere par :

```bash
php scripts/setup.php --cron
```

## Test rapide

Pour verifier le traitement des codes HTTP sans dependre d une application metier, utiliser temporairement les endpoints de test `httpbin` :

```text
https://httpbin.org/status/200
https://httpbin.org/status/500
```

Avec `Codes HTTP acceptes` configure a `200`, la premiere cible doit etre disponible et la seconde doit remonter une erreur HTTP 500 apres :

```bash
php scripts/check-web.php --force
php scripts/check-alerts.php --force
```

`http.cat` illustre les codes dans des pages qui repondent elles-memes en HTTP 200 ; il ne convient donc pas pour tester le statut recu par le collecteur. Ces services publics servent uniquement aux tests et ne doivent pas devenir des cibles permanentes de production.

## Alertes

Les regles sont administrables depuis `Alertes > Regles` :

- `url_unavailable` : erreur DNS, connexion, timeout, TLS ou redirection ;
- `url_http_status` : code HTTP hors de la plage acceptee ;
- `url_latency_high` : duree totale superieure au seuil en millisecondes ;
- `url_tls_expiry` : certificat arrivant a expiration selon le seuil en jours ;
- `url_content_mismatch` : chaine attendue absente.

Les alertes d indisponibilite, code HTTP et contenu respectent le nombre d echecs consecutifs configure sur la cible. Une execution reussie remet ce compteur a zero et permet la resolution automatique.

Un controle qui ne permet pas d evaluer une condition ne la resout pas. Par exemple, un timeout conserve une alerte de code HTTP deja active, car aucun nouveau code n a ete recu. Inversement, une reponse HTTP 500 ou 503 ne resout pas une indisponibilite de transport precedente : seule une execution entierement reussie confirme le retour a la normale. Cela evite les alternances artificielles d ouvertures et de resolutions quand une cible instable passe de timeout a code HTTP inattendu.

## Securite et limites

- seuls `http://` et `https://` sont acceptes ;
- les identifiants integres dans une URL sont refuses ;
- les redirections sont limitees a cinq et restent limitees a HTTP/HTTPS ;
- la reponse conservee en memoire est limitee a 1 Mio ;
- aucun corps de reponse ni secret n est stocke en base ;
- le controle TLS doit rester active en production ;
- MSM pouvant superviser le reseau interne du homelab, l acces a la creation des cibles doit rester reserve aux utilisateurs autorises au module Supervision.

Cette premiere version ne gere pas encore l authentification HTTP, les en-tetes personnalises, les requetes POST, les scenarios multi-etapes ou les sondes distribuees.
