<?php

class UpdateAssistant
{
    private string $root;
    private bool $colorsEnabled;
    private int $warnings = 0;
    private int $errors = 0;
    private array $actions = [];
    private ?string $databaseDumpCommand = null;

    public function __construct(string $root)
    {
        $resolved = realpath($root);
        $this->root = $resolved !== false ? $resolved : rtrim($root, DIRECTORY_SEPARATOR);
        $this->colorsEnabled = $this->detectColorSupport();
    }

    public function runCheck(?string $target = null, ?string $backupDir = null): int
    {
        $this->title('MSM update assistant');
        $this->line('Mode : verification uniquement, aucune modification ne sera effectuee.');

        if ($target !== null && !preg_match('/^v\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $target)) {
            $this->fail('Version cible', 'format invalide: ' . $target);
            $this->addAction('Utiliser un tag au format `v1.7.0`.');
            return $this->finish();
        }

        $this->section('Projet');
        $this->checkProject();
        $currentVersion = $this->readPackageVersion();
        $this->ok('Version MSM', $currentVersion);
        $this->info('Dossier MSM', $this->root);

        $this->section('Depot Git');
        $gitReady = $this->checkGit($target);

        $this->section('Systeme');
        $this->checkSystem();

        $this->section('Configuration et base');
        $env = $this->readEnv();
        $databaseReady = $this->checkDatabase($env);

        $this->section('Sauvegardes');
        $backupDir = $this->absolutePath($backupDir ?? $this->defaultBackupDirectory());
        $backupReady = $this->checkBackupReadiness($backupDir, $env, $databaseReady);

        $this->section('Plan de mise a jour');
        $this->printPlan($target, $backupDir, $gitReady, $databaseReady, $backupReady);

        return $this->finish();
    }

    public function runApply(string $target, ?string $backupDir = null, bool $yes = false): int
    {
        if (!preg_match('/^v\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $target)) {
            fwrite(STDERR, "Version cible invalide. Utiliser --target=v1.7.0.\n");
            return 2;
        }

        $currentVersion = $this->readPackageVersion();
        $targetVersion = ltrim($target, 'v');
        if ($currentVersion !== 'unknown' && version_compare($targetVersion, $currentVersion, '<=')) {
            fwrite(
                STDERR,
                'La cible ' . $target . ' doit etre superieure a la version actuelle v' . $currentVersion . ".\n"
            );
            return 2;
        }

        $backupDir = $this->absolutePath($backupDir ?? $this->defaultBackupDirectory());
        $checkCode = $this->runCheck($target, $backupDir);
        if ($checkCode !== 0) {
            $this->line('');
            $this->line('Mise a jour annulee : corriger les erreurs de prevalidation.');
            return $checkCode;
        }

        $this->section('Confirmation');
        $this->line('Cible       : ' . $target);
        $this->line('Projet      : ' . $this->root);
        $this->line('Sauvegardes : ' . $backupDir);

        if (!$yes && !$this->confirm('Continuer la mise a jour ? [y/N] ')) {
            $this->line('Mise a jour annulee par l utilisateur.');
            return 0;
        }

        $env = $this->readEnv();
        $previousRef = $this->gitOutput('rev-parse HEAD') ?? 'unknown';
        $previousDescription = $this->gitOutput('describe --tags --always') ?? $previousRef;
        $sessionDir = $backupDir
            . DIRECTORY_SEPARATOR
            . date('Ymd-His')
            . '-'
            . preg_replace('/[^0-9A-Za-z._-]/', '_', $target);

        $this->section('Sauvegardes');
        if (!$this->createSecureDirectory($sessionDir)) {
            $this->fail('Dossier de sauvegarde', 'creation impossible: ' . $sessionDir);
            return 1;
        }
        $this->ok('Dossier de sauvegarde', $sessionDir);

        $logPath = $sessionDir . DIRECTORY_SEPARATOR . 'update.log';
        $this->writeLog($logPath, 'MSM update started');
        $this->writeLog($logPath, 'Project: ' . $this->root);
        $this->writeLog($logPath, 'Source: ' . $previousDescription . ' (' . $previousRef . ')');
        $this->writeLog($logPath, 'Target: ' . $target);

        if (!$this->backupEnvironment($sessionDir, $logPath)) {
            return $this->abortApply('Sauvegarde .env impossible.', $sessionDir, $previousRef);
        }

        if (!$this->backupDatabase($sessionDir, $env, $logPath)) {
            return $this->abortApply('Sauvegarde de la base impossible.', $sessionDir, $previousRef);
        }

        if (!$this->backupRuntimeReport($sessionDir, $logPath)) {
            return $this->abortApply('Creation du rapport avant mise a jour impossible.', $sessionDir, $previousRef);
        }

        $this->section('Application');
        if (!$this->runStep(
            'Recuperation des tags Git',
            'git -C ' . escapeshellarg($this->root) . ' fetch --tags --prune',
            $logPath
        )) {
            return $this->abortApply('Echec de recuperation Git.', $sessionDir, $previousRef);
        }

        [$targetCode] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root)
            . ' rev-parse --verify --quiet '
            . escapeshellarg('refs/tags/' . $target)
        );
        if ($targetCode !== 0) {
            return $this->abortApply('Le tag cible ' . $target . ' est introuvable.', $sessionDir, $previousRef);
        }

        if (!$this->runStep(
            'Selection de ' . $target,
            'git -C ' . escapeshellarg($this->root) . ' checkout --detach ' . escapeshellarg($target),
            $logPath
        )) {
            return $this->abortApply('Echec du checkout Git.', $sessionDir, $previousRef);
        }

        if (!$this->runStep(
            'Dependances Composer',
            'composer --working-dir=' . escapeshellarg($this->root)
            . ' install --no-dev --optimize-autoloader --no-interaction',
            $logPath
        )) {
            return $this->abortApply('Echec de Composer.', $sessionDir, $previousRef);
        }

        if (!$this->runStep(
            'Migrations MSM',
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . DIRECTORY_SEPARATOR . 'apply_migrations.php'),
            $logPath
        )) {
            return $this->abortApply('Echec des migrations.', $sessionDir, $previousRef);
        }

        if (!$this->runStep(
            'Initialisation des logs',
            escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($this->root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'setup.php')
            . ' --init-logs',
            $logPath
        )) {
            return $this->abortApply('Echec de l initialisation des logs.', $sessionDir, $previousRef);
        }

        $this->captureSchedulingRecommendations($sessionDir, $logPath);

        $this->section('Verification finale');
        $this->runOperationalChecks($logPath);

        if (!$this->runStep(
            'Controle post-update',
            escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($this->root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'update-check.php'),
            $logPath
        )) {
            return $this->abortApply('Le controle post-update signale un echec.', $sessionDir, $previousRef);
        }

        $finalRef = $this->gitOutput('describe --tags --always') ?? $target;
        $this->writeLog($logPath, 'MSM update completed: ' . $finalRef);
        $this->ok('Mise a jour', $finalRef);
        $this->info('Journal', $logPath);
        $this->info('Sauvegardes', $sessionDir);
        $this->line('');
        $this->line('Verifier les avertissements du controle final et actualiser la crontab ou les timers si necessaire.');

        return 0;
    }

    private function checkProject(): void
    {
        $required = [
            'package.json',
            'composer.json',
            'apply_migrations.php',
            'scripts/update-check.php',
        ];

        foreach ($required as $file) {
            $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (is_file($path) && is_readable($path)) {
                $this->ok($file);
            } else {
                $this->fail($file, 'absent ou illisible');
            }
        }

        $gitDir = $this->root . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir)) {
            $this->ok('Depot Git', '.git present');
        } else {
            $this->fail('Depot Git', '.git absent');
            $this->addAction('Executer la mise a jour depuis la racine du clone Git MSM.');
        }
    }

    private function checkGit(?string $target): bool
    {
        if (!$this->commandExists('git')) {
            $this->fail('Commande git', 'introuvable dans PATH');
            return false;
        }

        $this->ok('Commande git');

        [$code, $output] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root) . ' rev-parse --is-inside-work-tree'
        );
        if ($code !== 0 || trim($output) !== 'true') {
            $this->fail('Depot Git', $output !== '' ? $output : 'depot invalide');
            return false;
        }

        [$branchCode, $branch] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root) . ' branch --show-current'
        );
        if ($branchCode !== 0) {
            $this->warn('Branche Git', $branch);
        } elseif (trim($branch) === '') {
            $this->warn('Branche Git', 'HEAD detachee');
        } else {
            $this->ok('Branche Git', trim($branch));
        }

        [$refCode, $ref] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root) . ' describe --tags --always'
        );
        $refCode === 0
            ? $this->ok('Revision Git', trim($ref))
            : $this->warn('Revision Git', $ref);

        [$statusCode, $trackedChanges] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root) . ' status --porcelain --untracked-files=no'
        );
        if ($statusCode !== 0) {
            $this->fail('Fichiers versionnes', $trackedChanges);
            return false;
        }

        if (trim($trackedChanges) !== '') {
            $count = count(array_filter(explode("\n", trim($trackedChanges))));
            $this->fail('Fichiers versionnes', $count . ' modification(s) locale(s)');
            $this->addAction('Committer, restaurer ou mettre de cote les modifications versionnees avant la mise a jour.');
            return false;
        }

        $this->ok('Fichiers versionnes', 'aucune modification locale');

        [$untrackedCode, $untracked] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root) . ' status --porcelain --untracked-files=normal'
        );
        if ($untrackedCode === 0) {
            $untrackedLines = array_filter(
                explode("\n", trim($untracked)),
                static fn(string $line): bool => str_starts_with($line, '??')
            );
            if ($untrackedLines !== []) {
                $this->warn('Fichiers non versionnes', count($untrackedLines) . ' fichier(s); ils ne seront pas supprimes');
            } else {
                $this->ok('Fichiers non versionnes', 'aucun');
            }
        }

        if ($target !== null) {
            [$targetCode] = $this->runCommand(
                'git -C ' . escapeshellarg($this->root)
                . ' rev-parse --verify --quiet '
                . escapeshellarg('refs/tags/' . $target)
            );
            if ($targetCode === 0) {
                $this->ok('Version cible locale', $target);
            } else {
                $this->warn('Version cible locale', $target . ' absente; un `git fetch --tags` sera necessaire');
            }
        } else {
            $this->info('Version cible', 'non fournie; utiliser --target=vX.Y.Z pour figer la release');
        }

        return true;
    }

    private function checkSystem(): void
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->ok('PHP version', PHP_VERSION);
        } else {
            $this->fail('PHP version', PHP_VERSION . ' detecte, PHP 8.0+ requis');
        }

        foreach (['pdo_mysql', 'openssl', 'mbstring'] as $extension) {
            extension_loaded($extension)
                ? $this->ok('Extension PHP ' . $extension)
                : $this->fail('Extension PHP ' . $extension, 'manquante');
        }

        if ($this->commandExists('composer')) {
            $this->ok('Commande composer');
        } else {
            $this->fail('Commande composer', 'introuvable dans PATH');
            $this->addAction('Installer `composer` avant de lancer une mise a jour automatisee.');
        }

        foreach (['mysqldump', 'mariadb-dump'] as $command) {
            if ($this->commandExists($command)) {
                $this->databaseDumpCommand = $command;
                break;
            }
        }
        if ($this->databaseDumpCommand !== null) {
            $this->ok('Commande dump SQL', $this->databaseDumpCommand);
        } else {
            $this->fail('Commande dump SQL', 'mysqldump ou mariadb-dump introuvable dans PATH');
            $this->addAction('Installer le client MariaDB/MySQL avant de lancer une mise a jour automatisee.');
        }

        $freeBytes = disk_free_space($this->root);
        if ($freeBytes === false) {
            $this->warn('Espace disque', 'mesure impossible');
            return;
        }

        $freeMb = (int) floor($freeBytes / 1024 / 1024);
        if ($freeMb >= 1024) {
            $this->ok('Espace disque', $freeMb . ' MB disponibles');
        } elseif ($freeMb >= 256) {
            $this->warn('Espace disque', $freeMb . ' MB disponibles; 1024 MB recommandes pour la mise a jour');
        } else {
            $this->fail('Espace disque', $freeMb . ' MB disponibles; espace insuffisant');
        }
    }

    private function checkDatabase(array $env): bool
    {
        $envPath = $this->root . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            $this->fail('Fichier .env', 'absent ou illisible');
            return false;
        }
        $this->ok('Fichier .env', 'present et lisible');

        foreach (['MSM_DB_HOST', 'MSM_DB_NAME', 'MSM_DB_USER'] as $key) {
            if (trim((string) ($env[$key] ?? '')) === '') {
                $this->fail('.env ' . $key, 'valeur manquante');
                return false;
            }
        }

        if (!extension_loaded('pdo_mysql')) {
            return false;
        }

        $host = (string) $env['MSM_DB_HOST'];
        $port = (string) ($env['MSM_DB_PORT'] ?? '3306');
        $db = (string) $env['MSM_DB_NAME'];
        $user = (string) $env['MSM_DB_USER'];
        $pass = (string) ($env['MSM_DB_PASS'] ?? '');
        $charset = (string) ($env['MSM_DB_CHARSET'] ?? 'utf8mb4');

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
            $serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $this->ok('Connexion base', $host . ':' . $port . '/' . $db);
            $this->ok('Version base', $serverVersion);
            return true;
        } catch (Throwable $e) {
            $this->fail('Connexion base', $e->getMessage());
            $this->addAction('Corriger les valeurs MSM_DB_* ou demarrer MariaDB/MySQL avant la mise a jour.');
            return false;
        }
    }

    private function checkBackupReadiness(
        string $backupDir,
        array $env,
        bool $databaseReady
    ): bool {
        $this->info('Dossier de sauvegarde', $backupDir);

        if ($this->pathIsInsideProject($backupDir)) {
            $this->fail('Emplacement sauvegarde', 'le dossier ne doit pas etre place dans le repertoire web MSM');
            $this->addAction('Choisir un dossier externe avec `--backup-dir=/chemin/securise`.');
            return false;
        }

        $existingParent = $this->nearestExistingParent($backupDir);
        if ($existingParent === null || !is_writable($existingParent)) {
            $this->fail('Emplacement sauvegarde', 'parent absent ou non accessible en ecriture');
            $this->addAction('Creer le dossier de sauvegarde hors du web et donner l ecriture a l utilisateur de deploiement.');
            return false;
        }
        $this->ok('Emplacement sauvegarde', 'creation possible depuis ' . $existingParent);

        $envPath = $this->root . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($envPath)) {
            $this->fail('Sauvegarde .env', 'source illisible');
            return false;
        }
        $this->ok('Sauvegarde .env', 'source lisible');

        $dbName = trim((string) ($env['MSM_DB_NAME'] ?? ''));
        if ($databaseReady && $this->databaseDumpCommand !== null && $dbName !== '') {
            $this->ok('Sauvegarde base', 'possible pour ' . $dbName . ' avec ' . $this->databaseDumpCommand);
            return true;
        }

        $this->fail('Sauvegarde base', 'base ou outil de dump SQL indisponible');
        return false;
    }

    private function printPlan(
        ?string $target,
        string $backupDir,
        bool $gitReady,
        bool $databaseReady,
        bool $backupReady
    ): void {
        $steps = [
            'Creer un dossier horodate dans ' . $backupDir . '.',
            'Sauvegarder la base MariaDB/MySQL sans afficher le mot de passe.',
            'Copier .env et enregistrer la revision Git, la version MSM et l ordonnancement courant.',
            'Recuperer les tags Git et selectionner ' . ($target ?? 'la release cible confirmee par l utilisateur') . '.',
            'Executer composer install --no-dev --optimize-autoloader.',
            'Appliquer les migrations MSM.',
            'Initialiser les nouveaux fichiers de logs sans ecraser les fichiers existants.',
            'Comparer cron/systemd avec les checks attendus et proposer les ajouts manquants.',
            'Relancer les checks principaux, executer scripts/update-check.php et afficher le bilan final.',
        ];

        foreach ($steps as $index => $step) {
            $this->line(($index + 1) . '. ' . $step);
        }

        if ($gitReady && $databaseReady && $backupReady && $this->errors === 0) {
            $this->ok('Prevalidation', 'instance prete pour le mode --apply');
        } else {
            $this->fail('Prevalidation', 'conditions non reunies pour appliquer une mise a jour');
        }

        $this->info('Mode --apply', 'disponible avec une cible explicite: --apply --target=vX.Y.Z');
    }

    private function finish(): int
    {
        $this->section('Resultat');
        if ($this->errors > 0) {
            $this->line('Result: update check has ' . $this->errors . ' FAIL item(s) and ' . $this->warnings . ' WARN item(s).');
        } elseif ($this->warnings > 0) {
            $this->line('Result: update check passed with ' . $this->warnings . ' WARN item(s).');
        } else {
            $this->line('Result: update check passed.');
        }

        if ($this->actions !== []) {
            $this->line('');
            $this->line('Actions recommandees :');
            foreach (array_values(array_unique($this->actions)) as $action) {
                $this->line('- ' . $action);
            }
        }

        return $this->errors > 0 ? 1 : 0;
    }

    private function readPackageVersion(): string
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'package.json';
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($json) ? (string) ($json['version'] ?? 'unknown') : 'unknown';
    }

    private function backupEnvironment(string $sessionDir, string $logPath): bool
    {
        $source = $this->root . DIRECTORY_SEPARATOR . '.env';
        $target = $sessionDir . DIRECTORY_SEPARATOR . 'env.backup';
        if (!copy($source, $target)) {
            $this->fail('Sauvegarde .env', 'copie impossible');
            return false;
        }

        @chmod($target, 0600);
        $this->writeLog($logPath, 'Environment backup: ' . $target);
        $this->ok('Sauvegarde .env', $target);
        return true;
    }

    private function backupDatabase(string $sessionDir, array $env, string $logPath): bool
    {
        if ($this->databaseDumpCommand === null) {
            $this->fail('Sauvegarde base', 'outil de dump SQL indisponible');
            return false;
        }

        $required = ['MSM_DB_HOST', 'MSM_DB_NAME', 'MSM_DB_USER'];
        foreach ($required as $key) {
            if (trim((string) ($env[$key] ?? '')) === '') {
                $this->fail('Sauvegarde base', 'configuration ' . $key . ' manquante');
                return false;
            }
        }

        $configPath = $sessionDir . DIRECTORY_SEPARATOR . '.db-client.cnf';
        $dumpPath = $sessionDir . DIRECTORY_SEPARATOR . 'database.sql';
        $config = "[client]\n"
            . 'host=' . $this->quoteOptionValue((string) $env['MSM_DB_HOST']) . "\n"
            . 'port=' . $this->quoteOptionValue((string) ($env['MSM_DB_PORT'] ?? '3306')) . "\n"
            . 'user=' . $this->quoteOptionValue((string) $env['MSM_DB_USER']) . "\n"
            . 'password=' . $this->quoteOptionValue((string) ($env['MSM_DB_PASS'] ?? '')) . "\n";

        if (file_put_contents($configPath, $config, LOCK_EX) === false) {
            $this->fail('Sauvegarde base', 'fichier temporaire de connexion impossible');
            return false;
        }
        @chmod($configPath, 0600);

        $command = $this->databaseDumpCommand
            . ' --defaults-extra-file=' . escapeshellarg($configPath)
            . ' --single-transaction --quick --lock-tables=false'
            . ' --result-file=' . escapeshellarg($dumpPath)
            . ' -- ' . escapeshellarg((string) $env['MSM_DB_NAME']);

        $success = $this->runStep('Sauvegarde base', $command, $logPath);
        @unlink($configPath);

        if (!$success || !is_file($dumpPath) || filesize($dumpPath) === 0) {
            @unlink($dumpPath);
            $this->fail('Sauvegarde base', 'dump absent ou vide');
            return false;
        }

        @chmod($dumpPath, 0600);
        $this->writeLog($logPath, 'Database backup: ' . $dumpPath);
        $this->ok('Fichier SQL', $dumpPath . ' (' . filesize($dumpPath) . ' octets)');
        return true;
    }

    private function backupRuntimeReport(string $sessionDir, string $logPath): bool
    {
        $reportPath = $sessionDir . DIRECTORY_SEPARATOR . 'runtime-report.txt';
        $lines = [
            'MSM update runtime report',
            'Generated: ' . date(DATE_ATOM),
            'Project: ' . $this->root,
            'PHP: ' . PHP_VERSION,
            'PHP binary: ' . PHP_BINARY,
            'MSM version: ' . $this->readPackageVersion(),
            'Git ref: ' . ($this->gitOutput('describe --tags --always') ?? 'unknown'),
            'Git commit: ' . ($this->gitOutput('rev-parse HEAD') ?? 'unknown'),
        ];

        if ($this->commandExists('crontab')) {
            [$code, $cron] = $this->runCommand('crontab -l');
            $lines[] = '';
            $lines[] = 'Crontab:';
            $lines[] = $code === 0 ? $cron : 'unavailable';
        }

        if ($this->commandExists('systemctl')) {
            [, $timers] = $this->runCommand("systemctl list-timers 'msm-*' --all --no-pager");
            $lines[] = '';
            $lines[] = 'Systemd timers:';
            $lines[] = $timers !== '' ? $timers : 'none';
        }

        if (file_put_contents($reportPath, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
            $this->fail('Rapport runtime', 'ecriture impossible');
            return false;
        }

        $this->writeLog($logPath, 'Runtime report: ' . $reportPath);
        $this->ok('Rapport runtime', $reportPath);
        return true;
    }

    private function captureSchedulingRecommendations(string $sessionDir, string $logPath): void
    {
        $setupScript = $this->root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'setup.php';
        $commands = [
            'cron-recommended.txt' => '--cron',
            'systemd-recommended.txt' => '--systemd',
        ];

        foreach ($commands as $filename => $option) {
            [$code, $output] = $this->runCommand(
                escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($setupScript) . ' ' . $option
            );
            $path = $sessionDir . DIRECTORY_SEPARATOR . $filename;
            if ($code === 0 && file_put_contents($path, $output . PHP_EOL, LOCK_EX) !== false) {
                $this->writeLog($logPath, 'Scheduling recommendation: ' . $path);
                $this->ok('Proposition ' . ltrim($option, '-'), $path);
            } else {
                $this->warn('Proposition ' . ltrim($option, '-'), 'generation impossible');
                $this->writeLog($logPath, 'WARN scheduling recommendation failed: ' . $option);
            }
        }
    }

    private function runOperationalChecks(string $logPath): void
    {
        $checks = [
            'Supervision' => 'check-servers.php',
            'Patch Management' => 'check-patches.php',
            'Cycle de vie OS' => 'check-os-lifecycle.php',
            'Securite' => 'check-security.php',
            'Sante materielle' => 'check-hardware-health.php',
            'Alerting' => 'check-alerts.php',
        ];

        foreach ($checks as $label => $script) {
            $path = $this->root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $script;
            if (!is_file($path)) {
                $this->warn('Check ' . $label, $script . ' absent');
                $this->writeLog($logPath, 'WARN missing operational check: ' . $script);
                continue;
            }

            [$code, $output] = $this->runCommand(
                escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' --force'
            );
            if ($output !== '') {
                foreach (explode("\n", $output) as $line) {
                    $this->writeLog($logPath, '[' . $script . '] ' . $line);
                }
            }

            if ($code === 0) {
                $this->ok('Check ' . $label);
                $this->writeLog($logPath, 'OK operational check: ' . $script);
            } else {
                $this->warn('Check ' . $label, 'code ' . $code . '; consulter update.log');
                $this->writeLog($logPath, 'WARN operational check failed: ' . $script . ' exit=' . $code);
            }
        }
    }

    private function runStep(string $label, string $command, string $logPath): bool
    {
        $this->writeLog($logPath, 'START ' . $label);
        [$code, $output] = $this->runCommand($command);

        if ($output !== '') {
            foreach (explode("\n", $output) as $line) {
                $this->line('  ' . $line);
                $this->writeLog($logPath, $line);
            }
        }

        if ($code !== 0) {
            $this->writeLog($logPath, 'FAIL ' . $label . ' exit=' . $code);
            $this->fail($label, 'code ' . $code);
            return false;
        }

        $this->writeLog($logPath, 'OK ' . $label);
        $this->ok($label);
        return true;
    }

    private function abortApply(string $reason, string $sessionDir, string $previousRef): int
    {
        $this->line('');
        $this->fail('Mise a jour interrompue', $reason);
        $this->line('Aucune restauration automatique n a ete executee.');
        $this->line('Sauvegardes : ' . $sessionDir);
        $this->line('Revision precedente : ' . $previousRef);
        $this->line('Retour code guide : git -C ' . $this->root . ' checkout --detach ' . $previousRef);
        $this->line('Ne restaurer la base qu apres diagnostic et validation manuelle.');
        return 1;
    }

    private function createSecureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return is_writable($path);
        }

        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            return false;
        }
        @chmod($path, 0700);
        return is_writable($path);
    }

    private function quoteOptionValue(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function gitOutput(string $arguments): ?string
    {
        [$code, $output] = $this->runCommand(
            'git -C ' . escapeshellarg($this->root) . ' ' . $arguments
        );
        return $code === 0 && $output !== '' ? $output : null;
    }

    private function confirm(string $prompt): bool
    {
        $this->line('');
        echo $prompt;
        $answer = fgets(STDIN);
        if ($answer === false) {
            return false;
        }

        return in_array(strtolower(trim($answer)), ['y', 'yes', 'o', 'oui'], true);
    }

    private function writeLog(string $path, string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        @chmod($path, 0600);
    }

    private function readEnv(): array
    {
        $path = $this->root . DIRECTORY_SEPARATOR . '.env';
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

    private function defaultBackupDirectory(): string
    {
        $home = getenv(PHP_OS_FAMILY === 'Windows' ? 'USERPROFILE' : 'HOME');
        if (is_string($home) && trim($home) !== '') {
            return rtrim($home, '\\/') . DIRECTORY_SEPARATOR . '.msm' . DIRECTORY_SEPARATOR . 'backups';
        }

        return dirname($this->root) . DIRECTORY_SEPARATOR . 'msm-backups';
    }

    private function absolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->root . DIRECTORY_SEPARATOR . $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private function pathIsInsideProject(string $path): bool
    {
        $normalizedRoot = $this->normalizePath($this->root);
        $normalizedPath = $this->normalizePath($path);
        return $normalizedPath === $normalizedRoot
            || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }

    private function nearestExistingParent(string $path): ?string
    {
        $candidate = $path;
        while (!is_dir($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate) {
                return null;
            }
            $candidate = $parent;
        }

        return $candidate;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(strtolower(str_replace('\\', '/', $path)), '/');
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $check = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($command)
            : 'command -v ' . escapeshellarg($command);
        [$code] = $this->runCommand($check);
        return $code === 0;
    }

    private function runCommand(string $command): array
    {
        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);
        return [$code, trim(implode("\n", $output))];
    }

    private function addAction(string $action): void
    {
        $this->actions[] = $action;
    }

    private function title(string $title): void
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

        return "\033[" . $colors[$status] . 'm' . $status . "\033[0m";
    }

    private function detectColorSupport(): bool
    {
        if (getenv('NO_COLOR') !== false || PHP_SAPI !== 'cli') {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_vt100_support')) {
            @sapi_windows_vt100_support(STDOUT, true);
        }

        return function_exists('stream_isatty') ? @stream_isatty(STDOUT) : PHP_OS_FAMILY === 'Windows';
    }
}
