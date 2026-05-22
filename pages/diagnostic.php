<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

function diagnosticStatus(bool $ok): string {
    $class = $ok ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100';
    $text = $ok ? 'OK' : 'KO';

    return '<span class="inline-flex px-2 py-1 rounded text-xs font-semibold ' . $class . '">' . $text . '</span>';
}

function diagnosticRow(string $label, string $value, ?bool $ok = null): string {
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
                echo diagnosticRow('Dossier logs', $logsPath, is_dir($logsPath) && is_writable($logsPath));
                echo diagnosticRow('Dossier migrations', $migrationsPath, is_dir($migrationsPath) && is_readable($migrationsPath));
                ?>
            </tbody>
        </table>
    </div>

    <p class="text-sm text-gray-500">
        Cette page ne doit pas afficher de secret. Elle sert a diagnostiquer rapidement la configuration locale et les ecarts de temps entre PHP et MariaDB.
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
