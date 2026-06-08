# Prometheus et Grafana

MSM expose les derniers resultats connus au format Prometheus via :

```text
http://msm.example.local/msm/metrics.php
```

L'endpoint lit uniquement la base MSM. Il ne lance pas de ping, SSH, analyse de patch, Docker ou appel API pendant le scrape.

## Metriques exposees

Les labels communs stables sont :

- `server` : nom MSM du serveur ;
- `hostname` : hostname ou IP configure dans MSM ;
- `type` : type de cible issu de l'inventaire MSM.

Le label `type` doit rester une valeur controlee par l'inventaire, par exemple `linux`, `windows`, `proxmox`, `synology`, `docker`, `website`, `network` ou `other`.

Certaines familles ajoutent des labels specialises :

- Patch Management : `update_type`, `collector`, `status` ;
- Cycle de vie OS : `os_family`, `os_version`, `support_status`.
- Securite : `status`.

```text
msm_server_up{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_ssh_ok{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_server_latency_ms{server="server-01",hostname="server-01.example.local",type="linux"} 4
msm_server_disk_usage_percent{server="server-01",hostname="server-01.example.local",type="linux"} 67
msm_server_last_check_timestamp{server="server-01",hostname="server-01.example.local",type="linux"} 1780000000
msm_check_success{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_updates_available{server="server-01",hostname="server-01.example.local",type="linux",update_type="security"} 2
msm_updates_available{server="server-01",hostname="server-01.example.local",type="linux",update_type="normal"} 11
msm_reboot_required{server="server-01",hostname="server-01.example.local",type="linux",collector="apt"} 1
msm_patch_check_status{server="server-01",hostname="server-01.example.local",type="linux",collector="apt",status="critical"} 1
msm_patch_check_timestamp{server="server-01",hostname="server-01.example.local",type="linux",collector="apt"} 1780000000
msm_os_support_status{server="server-01",hostname="server-01.example.local",type="linux",os_family="ubuntu",os_version="22.04",support_status="supported"} 1
msm_os_upgrade_available{server="server-01",hostname="server-01.example.local",type="linux",os_family="ubuntu",os_version="22.04"} 1
msm_os_support_end_timestamp{server="server-01",hostname="server-01.example.local",type="linux",os_family="ubuntu",os_version="22.04"} 1809043200
msm_os_lifecycle_check_timestamp{server="server-01",hostname="server-01.example.local",type="linux",os_family="ubuntu",os_version="22.04"} 1780000000
msm_security_check_success{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_security_check_status{server="server-01",hostname="server-01.example.local",type="linux",status="warning"} 1
msm_security_open_ports{server="server-01",hostname="server-01.example.local",type="linux"} 8
msm_security_exposed_ports{server="server-01",hostname="server-01.example.local",type="linux"} 2
msm_security_firewall_enabled{server="server-01",hostname="server-01.example.local",type="linux"} 1
msm_security_last_check_timestamp{server="server-01",hostname="server-01.example.local",type="linux"} 1780000000
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

Mises a jour de securite disponibles :

```promql
msm_updates_available{update_type="security"} > 0
```

Reboot requis :

```promql
msm_reboot_required == 1
```

Checks Patch Management en erreur :

```promql
msm_patch_check_status{status="error"} == 1
```

Upgrade OS disponible :

```promql
msm_os_upgrade_available == 1
```

OS obsolete ou fin de support proche :

```promql
msm_os_support_status{support_status=~"eol|eol_soon"} == 1
```

Checks securite en erreur :

```promql
msm_security_check_success == 0
```

Ports exposes :

```promql
msm_security_exposed_ports > 0
```

Firewall inactif ou non detecte :

```promql
msm_security_firewall_enabled == 0
```

## Dashboard Grafana minimal

Panneaux recommandes :

- Stat : nombre de serveurs down avec `sum(1 - msm_server_up)`.
- Table : etat par serveur avec `msm_server_up`, `msm_ssh_ok` et `msm_server_disk_usage_percent`.
- Time series : latence avec `msm_server_latency_ms`.
- Gauge : disque avec `msm_server_disk_usage_percent`.
- Stat : age du dernier check avec `time() - msm_server_last_check_timestamp`.
- Table : patch management avec `msm_updates_available`, `msm_reboot_required` et `msm_patch_check_status`.
- Table : cycle de vie OS avec `msm_os_support_status`, `msm_os_upgrade_available` et `msm_os_support_end_timestamp`.
- Table : securite avec `msm_security_check_status`, `msm_security_exposed_ports` et `msm_security_firewall_enabled`.

## Points d'attention

- Prometheus doit pouvoir joindre l'URL `/metrics.php`.
- Les checks MSM doivent etre planifies avec cron ou systemd timer.
- Si les metriques restent anciennes, verifier `scripts/check-servers.php`, le log associe et la page diagnostic MSM.
