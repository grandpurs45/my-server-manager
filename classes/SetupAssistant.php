<?php

class SetupAssistant
{
    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'openssl', 'mbstring'];
    private const RECOMMENDED_EXTENSIONS = ['zip'];
    private const REQUIRED_COMMANDS = ['git', 'composer', 'ping'];
    private const RECOMMENDED_COMMANDS = ['unzip', 'ssh'];
    private const REQUIRED_ENV = ['MSM_DB_HOST', 'MSM_DB_NAME', 'MSM_DB_USER', 'MSM_SECRET_KEY'];

    private const CHECKS = [
        [
            'name' => 'Supervision',
            'unit' => 'msm-check-servers',
            'description' => 'MSM server supervision check',
            'timer_description' => 'Run MSM server supervision check every minute',
            'cron' => '* * * * *',
            'on_boot' => '1min',
            'on_active' => '1min',
            'script' => 'check-servers.php',
            'log' => 'check-servers.log',
        ],
        [
            'name' => 'Patch Management',
            'unit' => 'msm-check-patches',
            'description' => 'MSM patch management check',
            'timer_description' => 'Run MSM patch management check every 10 minutes',
            'cron' => '*/10 * * * *',
            'on_boot' => '5min',
            'on_active' => '10min',
            'script' => 'check-patches.php',
            'log' => 'check-patches.log',
        ],
        [
            'name' => 'Cycle de vie OS',
            'unit' => 'msm-check-os-lifecycle',
            'description' => 'MSM OS lifecycle check',
            'timer_description' => 'Run MSM OS lifecycle check hourly',
            'cron' => '15 * * * *',
            'on_boot' => '10min',
            'on_active' => '1h',
            'script' => 'check-os-lifecycle.php',
            'log' => 'check-os-lifecycle.log',
        ],
        [
            'name' => 'Securite',
            'unit' => 'msm-check-security',
            'description' => 'MSM security check',
            'timer_description' => 'Run MSM security check hourly',
            'cron' => '30 * * * *',
            'on_boot' => '15min',
            'on_active' => '1h',
            'script' => 'check-security.php',
            'log' => 'check-security.log',
        ],
        [
            'name' => 'Sante materielle',
            'unit' => 'msm-check-hardware-health',
            'description' => 'MSM hardware health check',
            'timer_description' => 'Run MSM hardware health check every 5 minutes',
            'cron' => '*/5 * * * *',
            'on_boot' => '5min',
            'on_active' => '5min',
            'script' => 'check-hardware-health.php',
            'log' => 'check-hardware-health.log',
        ],
        [
            'name' => 'Alerting',
            'unit' => 'msm-check-alerts',
            'description' => 'MSM alerting evaluation',
            'timer_description' => 'Run MSM alerting evaluation every 5 minutes',
            'cron' => '*/5 * * * *',
            'on_boot' => '2min',
            'on_active' => '5min',
            'script' => 'check-alerts.php',
            'log' => 'check-alerts.log',
        ],
    ];

    private string $root;
    private int $errors = 0;
    private int $warnings = 0;
    private array $actions = [];
    private bool $colorsEnabled;

    public function __construct(string $root)
    {
        $realRoot = realpath($root);
        $this->root = $realRoot !== false ? $realRoot : $root;
        $this->colorsEnabled = $this->detectColorSupport();
    }

    public function runSetup(bool $cronOnly = false): int
    {
        $this->header('MSM setup assistant');

        if ($cronOnly) {
            $this->printCronInstructions();
            return 0;
        }

        $env = $this->readEnv();

        $this->section('Systeme');
        $this->checkPhp();
        $this->checkPhpExtensions();
        $this->checkCommands();
        $this->checkApache();

        $this->section('Projet');
        $this->checkProjectFiles();
        $this->checkLocalConfig($env);
        $this->checkLogs();

        $this->section('Base de donnees');
        $pdo = $this->checkDatabase($env);
        $this->checkMigrations($pdo);

        $this->section('Ordonnancement');
        $this->printCronInstructions();
        $this->checkCurrentCrontab();
        $this->checkSystemdTimers();
        $this->checkCheckLogs();

        $this->section('Resultat');
        return $this->printSummary();
    }

    public function prepareLocalConfig(): int
    {
        $this->header('MSM local config preparation');

        $source = $this->root . DIRECTORY_SEPARATOR . '.env.example';
        $target = $this->root . DIRECTORY_SEPARATOR . '.env';

        if (is_file($target)) {
            $this->warn('Local config .env', 'deja present; aucune modification');
            $this->addAction('Editer .env si les acces base doivent etre ajustes.');
            return $this->printSummary();
        }

        if (!is_file($source) || !is_readable($source)) {
            $this->fail('Fichier .env.example', 'absent ou illisible');
            return $this->printSummary();
        }

        $content = file_get_contents($source);
        if ($content === false) {
            $this->fail('Lecture .env.example', 'impossible');
            return $this->printSummary();
        }

        $secret = bin2hex(random_bytes(32));
        if (preg_match('/^MSM_SECRET_KEY=.*$/m', $content)) {
            $content = preg_replace('/^MSM_SECRET_KEY=.*$/m', 'MSM_SECRET_KEY=' . $secret, $content);
        } else {
            $content .= PHP_EOL . 'MSM_SECRET_KEY=' . $secret . PHP_EOL;
        }

        if (file_put_contents($target, $content) === false) {
            $this->fail('Creation .env', 'impossible');
            return $this->printSummary();
        }

        $this->ok('Creation .env', 'fichier cree avec une cle locale aleatoire');
        $this->warn('Configuration DB', 'generer les commandes SQL puis reporter les valeurs dans .env');
        $this->addAction('Lancer `php scripts/setup.php --db-sql` pour obtenir les commandes SQL.');

        return $this->printSummary();
    }

    public function prepareLogs(): int
    {
        $this->header('MSM logs preparation');

        $logs = $this->root . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logs) && !mkdir($logs, 0775, true)) {
            $this->fail('logs directory', 'creation impossible');
            return $this->printSummary();
        }

        if (!is_writable($logs)) {
            $this->fail('logs directory', 'not writable by current user');
            return $this->printSummary();
        }

        $this->ok('logs directory', $this->pathForShell($logs));

        foreach (self::CHECKS as $check) {
            $path = $logs . DIRECTORY_SEPARATOR . $check['log'];
            if (is_file($path)) {
                $this->ok($check['log'], 'deja present');
                continue;
            }

            if (touch($path)) {
                $this->ok($check['log'], 'cree');
            } else {
                $this->fail($check['log'], 'creation impossible');
            }
        }

        return $this->printSummary();
    }

    public function printDatabaseInstructions(): int
    {
        $this->header('MSM database preparation');

        $env = $this->readEnv();
        if ($env === []) {
            $this->info('Local config .env', 'absent; utilisation de .env.example pour generer un exemple');
            $env = $this->readEnv('.env.example');
        } else {
            $this->ok('Local config .env', 'utilise pour generer les commandes');
        }

        $db = trim((string) ($env['MSM_DB_NAME'] ?? 'msm'));
        $user = trim((string) ($env['MSM_DB_USER'] ?? 'msm_user'));
        $pass = (string) ($env['MSM_DB_PASS'] ?? '');
        $charset = trim((string) ($env['MSM_DB_CHARSET'] ?? 'utf8mb4'));

        if ($db === '') {
            $db = 'msm';
            $this->warn('MSM_DB_NAME', 'vide; exemple genere avec msm');
        }

        if ($user === '' || strtolower($user) === 'root') {
            $this->warn('MSM_DB_USER', 'root detecte; un utilisateur dedie est recommande');
            $user = 'msm_user';
        }

        if ($pass === '') {
            $this->warn('MSM_DB_PASS', 'vide; remplacer CHANGE_ME_STRONG_PASSWORD et reporter la valeur dans .env');
            $pass = 'CHANGE_ME_STRONG_PASSWORD';
        }

        $charset = preg_match('/^[a-zA-Z0-9_]+$/', $charset) ? $charset : 'utf8mb4';
        $collation = $charset === 'utf8mb4' ? 'utf8mb4_unicode_ci' : $charset . '_general_ci';

        $this->line('Executer ces commandes dans le client MariaDB/MySQL avec un compte administrateur :');
        $this->line('');
        $this->line('mysql -u root -p');
        $this->line('');
        $this->line('Puis :');
        $this->line('');
        $this->line('CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdentifier($db) . ' CHARACTER SET ' . $charset . ' COLLATE ' . $collation . ';');
        $this->line("CREATE USER IF NOT EXISTS '" . $this->escapeSqlString($user) . "'@'localhost' IDENTIFIED BY '" . $this->escapeSqlString($pass) . "';");
        $this->line("GRANT ALL PRIVILEGES ON " . $this->quoteIdentifier($db) . ".* TO '" . $this->escapeSqlString($user) . "'@'localhost';");
        $this->line('FLUSH PRIVILEGES;');
        $this->line('');
        $this->line('Reporter ensuite dans .env :');
        $this->line('MSM_DB_NAME=' . $db);
        $this->line('MSM_DB_USER=' . $user);
        $this->line('MSM_DB_PASS=' . $pass);
        if ($pass === 'CHANGE_ME_STRONG_PASSWORD') {
            $this->line('');
            $this->line($this->colorText('ATTENTION : remplacer CHANGE_ME_STRONG_PASSWORD par un mot de passe fort dans la commande SQL ET dans .env.', '31'));
        }
        $this->line('');
        $this->line('Si .env n existe pas encore : php scripts/setup.php --init-env');
        $this->line('Puis editer .env et remplacer les valeurs MSM_DB_* avec celles ci-dessus.');
        $this->line('');
        $this->line('Puis appliquer les migrations depuis la racine MSM :');
        $this->line('php scripts/setup.php --migrate');

        return $this->printSummary();
    }

    public function installSystemDependencies(bool $execute = false): int
    {
        $this->header('MSM system dependencies');

        $commands = $this->detectDependencyInstallCommands();
        if ($commands === []) {
            $this->fail('Distribution supportee', 'impossible de detecter apt-get, dnf ou yum');
            $this->addAction('Installer manuellement Apache, MariaDB/MySQL, PHP, les extensions PHP requises, Git, Composer et unzip.');
            return $this->printSummary();
        }

        $this->line('Commandes detectees pour installer les dependances systeme :');
        $this->line('');
        foreach ($commands as $command) {
            $this->line($command);
        }

        if (!$execute) {
            $this->line('');
            $this->warn('Mode simulation', 'aucune commande systeme executee');
            $this->addAction('Relancer avec `php scripts/setup.php --install-deps --yes` pour executer ces commandes.');
            $this->addAction('Relire les commandes avant execution, surtout sur une machine non dediee.');
            return $this->printSummary();
        }

        $this->line('');
        $this->line($this->colorText('Execution demandee par --yes.', '33'));
        foreach ($commands as $command) {
            $this->line('');
            $this->line('$ ' . $command);
            passthru($command, $code);
            if ($code !== 0) {
                $this->fail('Commande systeme', 'code ' . $code . ' pour: ' . $command);
                $this->addSystemDependencyFailureActions($command, $code);
                return $this->printSummary();
            }
        }

        $this->ok('Dependances systeme', 'commandes terminees');
        return $this->printSummary();
    }

    public function installComposerDependencies(): int
    {
        $this->header('MSM Composer dependencies');

        if (!$this->commandExists('composer')) {
            $this->fail('Command composer', 'not found in PATH');
            $this->addAction('Installer Composer, puis relancer `php scripts/setup.php --composer-install`.');
            return $this->printSummary();
        }

        $command = 'composer install --no-dev --optimize-autoloader';
        $this->line('$ ' . $command);
        passthru($command, $code);

        if ($code === 0) {
            $this->ok('Composer install', 'dependances installees');
        } else {
            $this->fail('Composer install', 'commande terminee avec code ' . $code);
        }

        return $this->printSummary();
    }

    public function runMigrations(): int
    {
        $this->header('MSM migrations');

        $script = $this->root . DIRECTORY_SEPARATOR . 'apply_migrations.php';
        if (!is_file($script) || !is_readable($script)) {
            $this->fail('apply_migrations.php', 'absent ou illisible');
            return $this->printSummary();
        }

        $this->line('Execution explicite des migrations MSM.');
        $this->line('Commande : ' . $this->pathForShell(PHP_BINARY) . ' ' . $this->pathForShell($script));
        $this->line('');

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
        passthru($command, $code);

        if ($code === 0) {
            $this->ok('Migrations', 'commande terminee');
        } else {
            $this->fail('Migrations', 'commande terminee avec code ' . $code);
        }

        return $this->printSummary();
    }

    public function runUpdateCheck(): int
    {
        $this->header('MSM update check');

        $env = $this->readEnv();

        $this->section('Version');
        $version = $this->readPackageVersion();
        $this->ok('Version MSM', $version);
        $this->checkGitStatus();

        $this->section('Dependances');
        $this->checkPhp();
        $this->checkProjectFiles();

        $this->section('Configuration et base');
        $this->checkLocalConfig($env);
        $pdo = $this->checkDatabase($env);
        $this->checkMigrations($pdo);

        $this->section('Checks planifies');
        $this->checkLogs();
        $this->checkCurrentCrontab();
        $this->checkCheckLogs();

        $this->section('Commandes post-update recommandees');
        $this->line('composer install --no-dev --optimize-autoloader');
        $this->line('php apply_migrations.php');
        $this->line('php scripts/setup.php --cron');

        $this->section('Resultat');
        return $this->printSummary();
    }

    public function printCronInstructions(): void
    {
        $php = $this->detectPhpBinary();
        $root = $this->pathForShell($this->root);
        $logs = $this->pathForShell($this->root . DIRECTORY_SEPARATOR . 'logs');

        $this->line('Chemin projet detecte : ' . $root);
        $this->line('PHP detecte           : ' . $php);
        $this->line('Logs recommandes      : ' . $logs);
        $this->line('');
        $this->line('Bloc cron recommande :');

        foreach (self::CHECKS as $check) {
            $this->line(sprintf(
                '%s %s %s/scripts/%s >> %s/%s 2>&1',
                $check['cron'],
                $php,
                $root,
                $check['script'],
                $logs,
                $check['log']
            ));
        }

        $this->line('');
        $this->line('A appliquer manuellement avec `crontab -e`. Ne pas configurer cron et systemd timers en double.');
    }

    public function printSystemdInstructions(string $user = 'www-data', ?string $group = null): void
    {
        $group = $group ?? $user;
        $php = $this->detectPhpBinary();
        $root = $this->pathForShell($this->root);

        $this->header('MSM systemd timer assistant');
        $this->line('Chemin projet detecte : ' . $root);
        $this->line('PHP detecte           : ' . $php);
        $this->line('Utilisateur systemd   : ' . $user);
        $this->line('Groupe systemd        : ' . $group);
        $this->line('');
        $this->line('Creer les fichiers suivants dans /etc/systemd/system/ :');

        foreach (self::CHECKS as $check) {
            $unit = $check['unit'];
            $script = $root . '/scripts/' . $check['script'];

            $this->line('');
            $this->line('# /etc/systemd/system/' . $unit . '.service');
            $this->line('[Unit]');
            $this->line('Description=' . $check['description']);
            $this->line('');
            $this->line('[Service]');
            $this->line('Type=oneshot');
            $this->line('WorkingDirectory=' . $root);
            $this->line('ExecStart=' . $php . ' ' . $script);
            $this->line('User=' . $user);
            $this->line('Group=' . $group);
            $this->line('');
            $this->line('# /etc/systemd/system/' . $unit . '.timer');
            $this->line('[Unit]');
            $this->line('Description=' . $check['timer_description']);
            $this->line('');
            $this->line('[Timer]');
            $this->line('OnBootSec=' . $check['on_boot']);
            $this->line('OnUnitActiveSec=' . $check['on_active']);
            $this->line('Unit=' . $unit . '.service');
            $this->line('');
            $this->line('[Install]');
            $this->line('WantedBy=timers.target');
        }

        $this->line('');
        $this->line("Commandes d'activation :");
        $this->line('sudo systemctl daemon-reload');

        foreach (self::CHECKS as $check) {
            $this->line('sudo systemctl enable --now ' . $check['unit'] . '.timer');
        }

        $this->line('');
        $this->line('Commandes de verification :');
        $this->line("systemctl list-timers 'msm-*'");

        foreach (self::CHECKS as $check) {
            $this->line('journalctl -u ' . $check['unit'] . '.service -n 50 --no-pager');
        }

        $this->line('');
        $this->line('Ne pas configurer cron et systemd timers en double.');
        $this->line('Sur RHEL/Rocky/AlmaLinux/Fedora, utiliser souvent --systemd-user=apache --systemd-group=apache.');
    }

    private function checkPhp(): void
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->ok('PHP version', PHP_VERSION);
        } else {
            $this->fail('PHP version', PHP_VERSION . ' detecte, PHP 8.0+ requis');
        }

        if (function_exists('exec')) {
            $this->ok('PHP function exec');
        } else {
            $this->fail('PHP function exec', 'desactivee');
        }
    }

    private function checkPhpExtensions(): void
    {
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $this->ok('PHP extension ' . $extension);
            } else {
                $this->fail('PHP extension ' . $extension, 'missing');
            }
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $this->ok('PHP extension ' . $extension);
            } else {
                $this->warn('PHP extension ' . $extension, 'missing; Composer peut utiliser les sources');
            }
        }
    }

    private function checkCommands(): void
    {
        foreach (self::REQUIRED_COMMANDS as $command) {
            if ($this->commandExists($command)) {
                $this->ok('Command ' . $command);
            } else {
                $this->fail('Command ' . $command, 'not found in PATH');
            }
        }

        foreach (self::RECOMMENDED_COMMANDS as $command) {
            if ($this->commandExists($command)) {
                $this->ok('Command ' . $command);
            } else {
                $this->warn('Command ' . $command, 'not found in PATH');
            }
        }
    }

    private function checkApache(): void
    {
        if ($this->commandExists('apache2') || $this->commandExists('httpd')) {
            $this->ok('Apache command');
        } else {
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                $this->info('Apache command', 'non detecte via PATH; normal avec certains environnements XAMPP');
                return;
            }

            $this->warn('Apache command', 'apache2/httpd non detecte; ignorer si un autre serveur web est utilise');
        }

        if (!$this->commandExists('systemctl')) {
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                $this->info('Apache service', 'systemctl non disponible sous Windows');
                return;
            }

            $this->warn('Apache service', 'systemctl non disponible dans cet environnement');
            return;
        }

        if ($this->serviceIsActive('apache2') || $this->serviceIsActive('httpd')) {
            $this->ok('Apache service', 'active');
        } else {
            $this->warn('Apache service', 'apache2/httpd non actif ou non detecte');
        }
    }

    private function checkProjectFiles(): void
    {
        $this->checkFile('Fichier .env.example', $this->root . DIRECTORY_SEPARATOR . '.env.example', true);
        $this->checkDirectory('Dossier migrations', $this->root . DIRECTORY_SEPARATOR . 'migrations', true);

        $autoload = $this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($autoload)) {
            $this->ok('Dependances Composer', 'vendor/autoload.php present');
        } else {
            $this->warn('Dependances Composer', 'vendor/autoload.php absent; lancer composer install');
        }
    }

    private function checkLocalConfig(array $env): void
    {
        $envFile = $this->root . DIRECTORY_SEPARATOR . '.env';

        if (is_file($envFile)) {
            $this->ok('Local config .env', 'present');
        } else {
            $this->fail('Local config .env', 'absent; copier .env.example vers .env');
            $this->addAction('Creer .env avec `php scripts/setup.php --init-env`.');
            return;
        }

        foreach (self::REQUIRED_ENV as $key) {
            $value = $env[$key] ?? '';

            if (trim((string) $value) !== '') {
                $this->ok('.env value ' . $key);
            } else {
                $this->fail('.env value ' . $key, 'missing');
                $this->addAction('Completer la valeur `' . $key . '` dans .env.');
            }
        }
    }

    private function checkLogs(): void
    {
        $logs = $this->root . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($logs)) {
            $this->fail('logs directory', 'absent; creer le dossier logs/');
            $this->addAction('Creer les logs avec `php scripts/setup.php --init-logs`.');
            return;
        }

        if (is_writable($logs)) {
            $this->ok('logs directory', 'writable');
        } else {
            $this->fail('logs directory', 'not writable by current user');
            $this->addAction('Corriger les permissions de `logs/` pour l utilisateur qui lance les checks.');
        }
    }

    private function checkDatabase(array $env): ?PDO
    {
        foreach (['MSM_DB_HOST', 'MSM_DB_NAME', 'MSM_DB_USER'] as $key) {
            if (empty($env[$key])) {
                $this->fail('Database connection', 'configuration incomplete');
                $this->addAction('Generer les commandes SQL avec `php scripts/setup.php --db-sql`, puis renseigner .env.');
                return null;
            }
        }

        if (!extension_loaded('pdo_mysql')) {
            $this->fail('Database connection', 'extension pdo_mysql manquante');
            return null;
        }

        $host = $env['MSM_DB_HOST'];
        $port = $env['MSM_DB_PORT'] ?? '3306';
        $db = $env['MSM_DB_NAME'];
        $user = $env['MSM_DB_USER'];
        $pass = $env['MSM_DB_PASS'] ?? '';
        $charset = $env['MSM_DB_CHARSET'] ?? 'utf8mb4';

        try {
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$db;charset=$charset",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $this->ok('Database connection', $host . ':' . $port . '/' . $db);
            return $pdo;
        } catch (Throwable $e) {
            $this->fail('Database connection', $e->getMessage());
            $this->addAction('Verifier que MariaDB/MySQL est demarre, que la base existe et que les valeurs MSM_DB_* de .env sont correctes.');
            return null;
        }
    }

    private function checkMigrations(?PDO $pdo): void
    {
        $files = glob($this->root . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);
        $total = count($files);
        $this->ok('Migration files', (string) $total);

        if ($pdo === null) {
            $this->warn('Migrations appliquees', 'verification impossible sans base');
            $this->addAction('Une fois la base joignable, lancer `php scripts/setup.php --migrate`.');
            return;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'migrations_applied'");
            $exists = (bool) $stmt->fetchColumn();

            if (!$exists) {
                $this->warn('Migrations appliquees', 'table migrations_applied absente; lancer php apply_migrations.php');
                $this->addAction('Appliquer les migrations avec `php scripts/setup.php --migrate`.');
                return;
            }

            $applied = (int) $pdo->query('SELECT COUNT(*) FROM migrations_applied')->fetchColumn();
            if ($applied >= $total) {
                $this->ok('Migrations appliquees', $applied . '/' . $total);
            } else {
                $this->warn('Migrations appliquees', $applied . '/' . $total . '; lancer php apply_migrations.php');
                $this->addAction('Appliquer les migrations restantes avec `php scripts/setup.php --migrate`.');
            }
        } catch (Throwable $e) {
            $this->warn('Migrations appliquees', $e->getMessage());
        }
    }

    private function checkCurrentCrontab(): void
    {
        if (!$this->commandExists('crontab')) {
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                $this->info('Crontab', 'non disponible sous Windows');
                return;
            }

            $this->warn('Crontab', 'commande crontab non disponible');
            return;
        }

        $output = [];
        $code = 0;
        exec('crontab -l 2>&1', $output, $code);
        $content = implode("\n", $output);

        if ($code !== 0 && !str_contains(strtolower($content), 'no crontab')) {
            $this->warn('Crontab', trim($content) !== '' ? trim($content) : 'lecture impossible');
            return;
        }

        if (trim($content) === '' || str_contains(strtolower($content), 'no crontab')) {
            $this->warn('Crontab', 'aucune crontab utilisateur detectee');
            return;
        }

        if (str_contains($content, '/var/log/msm-check-')) {
            $this->warn('Crontab logs', 'ancienne redirection /var/log detectee; preferer logs/ dans le projet');
        }

        $missing = [];
        foreach (self::CHECKS as $check) {
            if (!str_contains($content, '/scripts/' . $check['script']) && !str_contains($content, '\\scripts\\' . $check['script'])) {
                $missing[] = $check['script'];
            }
        }

        if ($missing === []) {
            $this->ok('Crontab MSM', 'tous les scripts sont presents');
        } else {
            $this->warn('Crontab MSM', 'scripts absents: ' . implode(', ', $missing));
        }
    }

    private function checkSystemdTimers(): void
    {
        if (!$this->commandExists('systemctl')) {
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                $this->info('Systemd timers', 'systemctl non disponible sous Windows');
                return;
            }

            $this->info('Systemd timers', 'systemctl non disponible');
            return;
        }

        $output = [];
        $code = 0;
        exec("systemctl list-timers 'msm-*' --all --no-pager 2>&1", $output, $code);
        $content = implode("\n", $output);

        if ($code !== 0) {
            $this->warn('Systemd timers', trim($content) !== '' ? trim($content) : 'lecture impossible');
            return;
        }

        $missing = [];
        foreach (self::CHECKS as $check) {
            if (!str_contains($content, $check['unit'] . '.timer')) {
                $missing[] = $check['unit'] . '.timer';
            }
        }

        if ($missing === []) {
            $this->ok('Systemd timers MSM', 'tous les timers sont presents');
        } else {
            $this->info('Systemd timers MSM', 'timers absents: ' . implode(', ', $missing));
        }
    }

    private function checkCheckLogs(): void
    {
        $logs = $this->root . DIRECTORY_SEPARATOR . 'logs';

        foreach (self::CHECKS as $check) {
            $path = $logs . DIRECTORY_SEPARATOR . $check['log'];
            if (!is_file($path)) {
                $this->warn($check['name'] . ' log', 'absent: logs/' . $check['log']);
                $this->addAction('Creer les fichiers de logs avec `php scripts/setup.php --init-logs`.');
                continue;
            }

            $mtime = filemtime($path);
            if ($mtime === false) {
                $this->warn($check['name'] . ' log', 'date illisible');
                continue;
            }

            $this->ok($check['name'] . ' log', date('Y-m-d H:i:s', $mtime));
        }
    }

    private function checkGitStatus(): void
    {
        if (!$this->commandExists('git')) {
            $this->warn('Git status', 'git non disponible');
            return;
        }

        $output = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($this->root) . ' status --short 2>&1', $output, $code);

        if ($code !== 0) {
            $this->warn('Git status', trim(implode("\n", $output)));
            return;
        }

        if ($output === []) {
            $this->ok('Git status', 'working tree clean');
        } else {
            $this->warn('Git status', count($output) . ' changement(s) local(aux)');
        }
    }

    private function readPackageVersion(): string
    {
        $package = $this->root . DIRECTORY_SEPARATOR . 'package.json';
        if (!is_file($package)) {
            return 'unknown';
        }

        $json = json_decode((string) file_get_contents($package), true);
        return is_array($json) ? (string) ($json['version'] ?? 'unknown') : 'unknown';
    }

    private function readEnv(string $file = '.env'): array
    {
        $path = $this->root . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }

        return $env;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function escapeSqlString(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    private function checkFile(string $label, string $path, bool $required): void
    {
        if (is_file($path)) {
            $this->ok($label);
            return;
        }

        $required ? $this->fail($label, 'absent') : $this->warn($label, 'absent');
    }

    private function checkDirectory(string $label, string $path, bool $required): void
    {
        if (is_dir($path) && is_readable($path)) {
            $this->ok($label);
            return;
        }

        $required ? $this->fail($label, 'absent ou illisible') : $this->warn($label, 'absent ou illisible');
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $escaped = escapeshellarg($command);
        $check = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? 'where ' . $escaped
            : 'command -v ' . $escaped;

        $output = [];
        $code = 0;
        exec($check . ' 2>&1', $output, $code);

        return $code === 0;
    }

    private function serviceIsActive(string $service): bool
    {
        $output = [];
        $code = 0;
        exec('systemctl is-active ' . escapeshellarg($service) . ' 2>&1', $output, $code);
        return $code === 0 && trim(implode("\n", $output)) === 'active';
    }

    private function detectPhpBinary(): string
    {
        if (function_exists('exec') && stripos(PHP_OS_FAMILY, 'Windows') !== 0) {
            $output = [];
            $code = 0;
            exec('command -v php 2>/dev/null', $output, $code);
            if ($code === 0 && isset($output[0]) && trim($output[0]) !== '') {
                return trim($output[0]);
            }
        }

        return $this->pathForShell(PHP_BINARY);
    }

    private function pathForShell(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function detectDependencyInstallCommands(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return [];
        }

        $sudo = $this->isRootUser() ? '' : 'sudo ';

        if ($this->commandExists('apt-get')) {
            return [
                $sudo . 'apt-get update',
                $sudo . 'apt-get install -y apache2 mariadb-server mariadb-client php php-cli php-mysql php-mbstring php-xml php-curl php-zip unzip git composer',
                $sudo . 'systemctl enable --now apache2 mariadb',
            ];
        }

        if ($this->commandExists('dnf')) {
            return [
                $sudo . 'dnf install -y httpd mariadb-server mariadb php php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip unzip git',
                $sudo . 'systemctl enable --now httpd mariadb',
                'command -v composer >/dev/null 2>&1 || echo "Composer non detecte : installer Composer manuellement depuis https://getcomposer.org/download/"',
            ];
        }

        if ($this->commandExists('yum')) {
            return [
                $sudo . 'yum install -y httpd mariadb-server mariadb php php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip unzip git',
                $sudo . 'systemctl enable --now httpd mariadb',
                'command -v composer >/dev/null 2>&1 || echo "Composer non detecte : installer Composer manuellement depuis https://getcomposer.org/download/"',
            ];
        }

        return [];
    }

    private function addSystemDependencyFailureActions(string $command, int $code): void
    {
        if (str_contains($command, 'apt-get ') || str_contains($command, 'apt ')) {
            $sudo = $this->isRootUser() ? '' : 'sudo ';

            if ($code === 100 || str_contains($command, ' install ')) {
                $this->addAction('Le gestionnaire de paquets APT semble avoir des dependances cassees. Lancer `' . $sudo . 'apt-get --fix-broken install` puis relancer `php scripts/setup.php --install-deps --yes`.');
            }

            if (str_contains($command, 'update')) {
                $this->addAction('Si `apt-get update` signale des erreurs GPG ou InRelease, verifier aussi l espace disque avec `df -h` puis nettoyer les index APT si necessaire.');
            }

            $this->addAction('Tester ensuite manuellement `' . $sudo . 'apt-get update` puis `' . $sudo . 'apt-get install -y apache2 mariadb-server mariadb-client php php-cli php-mysql php-mbstring php-xml php-curl php-zip unzip git composer`.');
        }
    }

    private function isRootUser(): bool
    {
        return function_exists('posix_geteuid') && posix_geteuid() === 0;
    }

    private function printSummary(): int
    {
        $this->printRecommendedActions();

        if ($this->errors > 0) {
            $this->line('Result: setup has ' . $this->errors . ' FAIL item(s). Corriger avant production.');
            return 1;
        }

        if ($this->warnings > 0) {
            $this->line('Result: setup usable with ' . $this->warnings . ' WARN item(s) to review.');
            return 0;
        }

        $this->line('Result: setup looks ready.');
        return 0;
    }

    private function addAction(string $action): void
    {
        if (!in_array($action, $this->actions, true)) {
            $this->actions[] = $action;
        }
    }

    private function printRecommendedActions(): void
    {
        if ($this->actions === []) {
            return;
        }

        $this->line('');
        $this->line('Actions recommandees');
        $this->line('--------------------');

        foreach ($this->actions as $index => $action) {
            $this->line(($index + 1) . '. ' . $action);
        }

        $this->line('');
    }

    private function header(string $title): void
    {
        $this->line($title);
        $this->line(str_repeat('=', strlen($title)));
        $this->line('');
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function ok(string $label, string $detail = ''): void
    {
        $this->status('OK', $label, $detail);
    }

    private function warn(string $label, string $detail = ''): void
    {
        $this->warnings++;
        $this->status('WARN', $label, $detail);
    }

    private function info(string $label, string $detail = ''): void
    {
        $this->status('INFO', $label, $detail);
    }

    private function fail(string $label, string $detail = ''): void
    {
        $this->errors++;
        $this->status('FAIL', $label, $detail);
    }

    private function status(string $status, string $label, string $detail = ''): void
    {
        $line = '[' . $this->colorizeStatus($status) . '] ' . $label;
        if ($detail !== '') {
            $line .= ' - ' . $detail;
        }
        $this->line($line);
    }

    private function line(string $line): void
    {
        echo $line . PHP_EOL;
    }

    private function colorizeStatus(string $status): string
    {
        $colors = [
            'OK' => '32',
            'WARN' => '33',
            'FAIL' => '31',
            'INFO' => '36',
        ];

        if (!$this->colorsEnabled || !isset($colors[$status])) {
            return $status;
        }

        return $this->colorText($status, $colors[$status]);
    }

    private function colorText(string $text, string $color): string
    {
        if (!$this->colorsEnabled) {
            return $text;
        }

        return "\033[" . $color . 'm' . $text . "\033[0m";
    }

    private function detectColorSupport(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (PHP_SAPI !== 'cli') {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_vt100_support')) {
            @sapi_windows_vt100_support(STDOUT, true);
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDOUT);
        }

        return PHP_OS_FAMILY === 'Windows';
    }
}
