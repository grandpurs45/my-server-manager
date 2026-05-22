# Prometheus et Grafana

MSM expose les derniers resultats connus au format Prometheus via :

```text
http://msm.example.local/msm/metrics.php
```

L'endpoint lit uniquement la base MSM. Il ne lance pas de ping, SSH, analyse de patch, Docker ou appel API pendant le scrape.

## Metriques exposees

Les labels stables en phase 1 sont :

- `server` : nom MSM du serveur ;
- `hostname` : hostname ou IP configure dans MSM.

Le label `type` sera ajoute plus tard avec la phase Inventaire, quand MSM disposera d'une donnee fiable pour distinguer Linux, Windows, Proxmox, Synology, Docker ou site web.

```text
msm_server_up{server="server-01",hostname="server-01.example.local"} 1
msm_ssh_ok{server="server-01",hostname="server-01.example.local"} 1
msm_server_latency_ms{server="server-01",hostname="server-01.example.local"} 4
msm_server_disk_usage_percent{server="server-01",hostname="server-01.example.local"} 67
msm_server_last_check_timestamp{server="server-01",hostname="server-01.example.local"} 1780000000
msm_check_success{server="server-01",hostname="server-01.example.local"} 1
```

## Exemple prometheus.yml

```yaml
scrape_configs:
  - job_name: "msm"
    scrape_interval: 30s
    metrics_path: /msm/metrics.php
    static_configs:
      - targets:
          - "msm.example.local"
```

Si MSM est installe a la racine du vhost, utiliser :

```yaml
metrics_path: /metrics.php
```

## Requetes PromQL utiles

Serveurs down :

```promql
msm_server_up == 0
```

SSH en erreur :

```promql
msm_ssh_ok == 0
```

Latence moyenne :

```promql
avg(msm_server_latency_ms)
```

Disques a plus de 85 % :

```promql
msm_server_disk_usage_percent > 85
```

Age du dernier check en secondes :

```promql
time() - msm_server_last_check_timestamp
```

Checks absents depuis plus de 15 minutes :

```promql
time() - msm_server_last_check_timestamp > 900
```

## Dashboard Grafana minimal

Panneaux recommandes :

- Stat : nombre de serveurs down avec `sum(1 - msm_server_up)`.
- Table : etat par serveur avec `msm_server_up`, `msm_ssh_ok` et `msm_server_disk_usage_percent`.
- Time series : latence avec `msm_server_latency_ms`.
- Gauge : disque avec `msm_server_disk_usage_percent`.
- Stat : age du dernier check avec `time() - msm_server_last_check_timestamp`.

## Points d'attention

- Prometheus doit pouvoir joindre l'URL `/metrics.php`.
- Les checks MSM doivent etre planifies avec cron ou systemd timer.
- Si les metriques restent anciennes, verifier `scripts/check-servers.php`, le log associe et la page diagnostic MSM.
