# Notifications MSM

MSM peut notifier les ouvertures et les resolutions d'alertes sans ajouter de nouveau cron.
Le traitement est execute a la fin de `scripts/check-alerts.php`.

## Canaux disponibles

- Discord : URL de webhook creee dans les parametres du salon Discord.
- Webhook JSON generique : endpoint HTTP ou HTTPS gere par un outil externe.

Les URLs de webhook sont chiffrees en base avec `MSM_SECRET_KEY`. Elles ne sont jamais
reaffichees dans l'interface. Une URL vide pendant la modification conserve la valeur
deja enregistree.

## Configuration

1. Appliquer les migrations :

   ```bash
   php apply_migrations.php
   ```

2. Ouvrir `Securite et alertes > Notifications`.
3. Renseigner facultativement l'URL publique de MSM pour ajouter un lien vers la page
   Alertes dans les messages.
4. Creer un canal et choisir :
   - le type Discord ou webhook generique ;
   - la severite minimale ;
   - les notifications d'ouverture et/ou de resolution ;
   - l'etat actif du canal.
5. Utiliser `Enregistrer et tester`, puis lancer :

   ```bash
   php scripts/check-alerts.php --force
   ```

Un canal nouvellement cree ne rejoue pas l'historique anterieur. Seuls les nouveaux
evenements d'alerte sont mis en file.

## Webhook generique

MSM envoie une requete `POST` avec `Content-Type: application/json`.

Exemple de charge utile :

```json
{
  "application": "My Server Manager",
  "event": "opened",
  "severity": "warning",
  "alert": {
    "id": 42,
    "rule": "patch_security_updates",
    "source": "patch_management",
    "status": "active",
    "title": "Mises a jour securite sur srv-web",
    "message": "3 mise(s) a jour de securite disponible(s)."
  },
  "target": {
    "name": "srv-web",
    "hostname": "srv-web.lan"
  },
  "occurred_at": "2026-07-26 10:00:00",
  "url": "https://msm.example.lan/pages/alerts.php"
}
```

L'endpoint doit retourner un code HTTP `2xx`. Les redirections ne sont pas suivies.
La verification TLS reste active pour les endpoints HTTPS.

## Fiabilite et historique

- Une contrainte en base interdit l'envoi multiple du meme evenement au meme canal.
- Chaque livraison est reservee atomiquement pour eviter un double envoi si deux
  executions du cron se chevauchent.
- Un envoi echoue est retente lors des executions suivantes de `check-alerts.php`.
- Le nombre maximal de tentatives est configurable de 1 a 10.
- Les derniers envois, codes HTTP et erreurs sont visibles sur la page Notifications.
- La desactivation d'un canal suspend ses envois sans supprimer son historique.
- La suppression d'un canal supprime egalement son historique d'envoi.

Une panne de canal n'empeche pas MSM de creer ou de resoudre les alertes internes.

## Diagnostic

Verifier le journal du collecteur Alerting :

```bash
tail -n 50 logs/check-alerts.log
```

La ligne de synthese contient :

```text
notifications_queued=1 notifications_sent=1 notifications_failed=0
```

Verifier egalement :

- que l'extension PHP `curl` est active ;
- que le serveur MSM peut resoudre et joindre le domaine du webhook ;
- que l'URL n'a pas ete revoquee ;
- que l'heure PHP et MariaDB sont coherentes.
