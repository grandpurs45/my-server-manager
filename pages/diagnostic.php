<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

function diagnosticStatus(bool|string $status): string {
    if (is_bool($status)) {
        $status = $status ? 'ok' : 'ko';
    }

    $status = strtolower($status);
    $class = match ($status) {
        'ok' => 'text-green-700 bg-green-100',
        'warn' => 'text-amber-700 bg-amber-100',
        default => 'text-red-700 bg-red-100',
    };
    $text = match ($status) {
        'ok' => 'OK',
        'warn' => 'WARN',
        default => 'KO',
    };

    return '<span class="inline-flex px-2 py-1 rounded text-xs font-semibold ' . $class . '">' . $text . '</span>';
}

function diagnosticRow(string $label, string $value, bool|string|null $ok = null): string {
    $status = $ok === null ? '' : diagnosticStatus($ok);

    return '<tr class="border-t">'
        . '<td class="p-3 font-medium text-gray-700">' . htmlspecialchars($label) . '</td>'
        . '<td class="p-3 text-gray-900">' . htmlspecialchars($value) . '</td>'
        . '<td class="p-3">' . $status . '</td>'
        . '</tr>';
}

$dbNow = 'indisponible';
$dbGlobalTimezone = 'indisponible';
$dbSessionTimezone = 'indisponible';
$lastCheck = 'Jamais';
$dbOk = false;

try {
    $dbNow = (string) $pdo->query('SELECT NOW()')->fetchColumn();
    $dbGlobalTimezone = (string) $pdo->query('SELECT @@global.time_zone')->fetchColumn();
    $dbSessionTimezone = (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn();
    $lastCheckRaw = $pdo->query('SELECT MAX(last_check) FROM servers')->fetchColumn();
    $lastCheck = $lastCheckRaw ? (string) $lastCheckRaw : 'Jamais';
    $dbOk = true;
} catch (Throwable $e) {
    error_log('MSM diagnostic database check failed: ' . $e->getMessage());
}

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$vendorAutoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$logsPath = $projectRoot . DIRECTORY_SEPARATOR . 'logs';
$migrationsPath = $projectRoot . DIRECTORY_SEPARATOR . 'migrations';
$legacyKeyPath = $projectRoot . DIRECTORY_SEPARATOR . 'msm_secret.key';
$secretConfigured = !empty(msmEnv('MSM_SECRET_KEY')) || is_readable($legacyKeyPath);

$expectedLogFiles = [
    'check-servers.log',
    'check-patches.log',
    'check-os-lifecycle.log',
    'check-security.log',
    'check-alerts.log',
];
$logsDetails = [];
$logsStatus = 'ko';

if (!is_dir($logsPath)) {
    $logsDetails[] = 'dossier absent';
} else {
    $logsDetails[] = 'dossier present';

    if (is_readable($logsPath)) {
        $logsDetails[] = 'lisible';
    } else {
        $logsDetails[] = 'non lisible';
    }

    if (is_writable($logsPath)) {
        $logsDetails[] = 'inscriptible par le serveur web';
    } else {
        $logsDetails[] = 'non inscriptible par le serveur web';
    }

    $missingLogFiles = [];
    foreach ($expectedLogFiles as $logFile) {
        if (!is_file($logsPath . DIRECTORY_SEPARATOR . $logFile)) {
            $missingLogFiles[] = $logFile;
        }
    }

    if ($missingLogFiles === []) {
        $logsDetails[] = 'fichiers check-*.log presents';
        $logsStatus = is_writable($logsPath) ? 'ok' : 'warn';
    } else {
        $logsDetails[] = 'fichiers manquants: ' . implode(', ', $missingLogFiles);
        $logsStatus = 'warn';
    }
}
?>

<div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Diagnostic systeme</h1>

    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <table class="w-full table-auto">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="p-3">Controle</th>
                    <th class="p-3">Valeur</th>
                    <th class="p-3">Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php
                echo diagnosticRow('Version MSM', getVersionFromPackageJson(), getVersionFromPackageJson() !== 'unknown');
                echo diagnosticRow('Version PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>='));
                echo diagnosticRow('Timezone PHP', date_default_timezone_get(), true);
                echo diagnosticRow('Heure PHP', date('Y-m-d H:i:s'), true);
                echo diagnosticRow('Connexion MariaDB', $dbOk ? 'Connectee' : 'Erreur', $dbOk);
                echo diagnosticRow('Heure MariaDB', $dbNow, $dbOk);
                echo diagnosticRow('Timezone MariaDB globale', $dbGlobalTimezone, $dbOk);
                echo diagnosticRow('Timezone MariaDB session', $dbSessionTimezone, $dbOk);
                echo diagnosticRow('Dernier check serveur', $lastCheck, $lastCheck !== 'Jamais');
                echo diagnosticRow('Fichier .env', $envPath, is_readable($envPath));
                echo diagnosticRow('Cle de chiffrement', $secretConfigured ? 'Configuree' : 'Manquante', $secretConfigured);
                echo diagnosticRow('Autoload Composer', $vendorAutoloadPath, is_readable($vendorAutoloadPath));
                echo diagnosticRow('Dossier logs', $logsPath . ' (' . implode(' ; ', $logsDetails) . ')', $logsStatus);
                echo diagnosticRow('Dossier migrations', $migrationsPath, is_dir($migrationsPath) && is_readable($migrationsPath));
                ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
