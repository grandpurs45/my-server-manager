<?php
use phpseclib3\Net\SSH2;

class Serveur {
    public string $hostname;
    public int $port;
    public string $ssh_user;
    public string $ssh_password;
    public string $name;
    public string $ip_address;

    public function __construct(array $data) {
        $this->hostname     = $data['hostname'];
        $this->port         = $data['port'] ?? 22;
        $this->ssh_user     = $data['ssh_user'];
        $this->ssh_password = $data['ssh_password'];
        $this->name = $data['name'];
        $this->ip_address = $data['hostname']; // ou 'ip', selon le champ du formulaire
    }

    public function testSSHConnection(): bool {
    try {
        $ssh = new \phpseclib3\Net\SSH2($this->hostname, $this->port);
        return $ssh->login($this->ssh_user, $this->ssh_password);
    } catch (\Exception $e) {
        // Tu peux loguer ici si besoin : error_log($e->getMessage());
        return false;
    }
}

    public function save(PDO $pdo, string $status): bool {
        $stmt = $pdo->prepare('
    INSERT INTO servers (name, ip_address, hostname, port, ssh_user, ssh_password, ssh_status, last_check)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    return $stmt->execute([
        $this->name,
        $this->ip_address,
        $this->hostname,
        $this->port,
        $this->ssh_user,
        $this->ssh_password,
        $status
        ]);
    }
}