<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/SetupAssistant.php';

$settingsSchema = require __DIR__ . '/../config/settings-schema.php';
$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$logsDirectory = $root . DIRECTORY_SEPARATOR . 'logs';
$checks = SetupAssistant::checks();

function msmCollectorsPhpBinary(): string
{
    if (PHP_OS_FAMILY !== 'Windows' && is_file('/usr/bin/php')) {
        return '/usr/bin/php';
    }

    return PHP_BINARY !== '' ? PHP_BINARY : 'php';
}

function msmCollectorsPath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function msmCollectorsCronLine(array $check, string $root, string $phpBinary): string
{
    $root = msmCollectorsPath($root);
    $logs = $root . '/logs';

    return sprintf(
        '%s %s %s/scripts/%s >> %s/%s 2>&1',
        $check['cron'],
        $phpBinary,
        $root,
        $check['script'],
        $logs,
        $check['log']
    );
}

function msmCollectorsIntervalLabel(array $check, \MSM\SettingsManager $settings, array $schema): string
{
    $category = (string) ($check['settings_category'] ?? '');
    $key = (string) ($check['interval_key'] ?? '');
    $unit = (string) ($check['interval_unit'] ?? '');
    if ($category === '' || $key === '') {
        return '-';
    }

    $value = $settings->get($category, $key);
    if ($value === null || $value === '') {
        $value = $schema[$category][$key]['default'] ?? null;
    }

    if ($value === null || $value === '') {
        return '-';
    }

    return $unit === 'hours'
        ? ((int) $value) . ' h'
        : ((int) $value) . ' min';
}

function msmCollectorsDuration(int $seconds): string
{
    if ($seconds < 120) {
        return $seconds . ' s';
    }

    $minutes = intdiv($seconds, 60);
    if ($minutes < 120) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return $remainingMinutes > 0 ? $hours . ' h ' . $remainingMinutes . ' min' : $hours . ' h';
}

function msmCollectorsStatusBadge(string $status): string
{
    return match ($status) {
        'ok' => '<span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">OK</span>',
        'running' => '<span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">En cours</span>',
        'warning' => '<span class="rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Ancien</span>',
        'critical' => '<span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Erreur</span>',
        default => '<span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>',
    };
}

function msmCollectorsCheckState(array $check, string $root, string $logsDirectory, \MSM\SettingsManager $settings): array
{
    $scriptPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $check['script'];
    $logPath = $logsDirectory . DIRECTORY_SEPARATOR . $check['log'];
    $category = (string) ($check['settings_category'] ?? '');
    $lastStatus = $category !== '' ? (string) ($settings->get($category, 'check_last_status') ?? '') : '';
    $lastMessage = $category !== '' ? (string) ($settings->get($category, 'check_last_message') ?? '') : '';
    $lastAttempt = $category !== '' ? $settings->get($category, 'check_last_attempt_at') : null;
    $lastRun = $category !== '' ? $settings->get($category, 'check_last_run_at') : null;
    $lastFinished = $category !== '' ? $settings->get($category, 'check_last_finished_at') : null;

    $state = 'ok';
    $reason = 'Operationnel';
    if (!is_file($scriptPath)) {
        $state = 'critical';
        $reason = 'Script absent';
    } elseif ($lastStatus === 'running') {
        $state = 'running';
        $reason = 'Execution en cours';
    } elseif (!is_file($logPath)) {
        $state = 'warning';
        $reason = 'Log absent';
    } elseif ($lastStatus === 'error') {
        $state = 'critical';
        $reason = 'Derniere execution en erreur';
    } else {
        $mtime = filemtime($logPath);
        $staleAfterMinutes = (int) ($check['log_stale_after_minutes'] ?? 0);
        $lastAttemptAgeSeconds = msmCollectorsDateAgeSeconds($lastAttempt);
        if ($staleAfterMinutes > 0 && $lastAttemptAgeSeconds !== null && $lastAttemptAgeSeconds > $staleAfterMinutes * 60) {
            $state = 'warning';
            $reason = 'Derniere tentative ancienne de ' . msmCollectorsDuration($lastAttemptAgeSeconds);
        } elseif ($mtime !== false && $staleAfterMinutes > 0 && $lastAttemptAgeSeconds === null) {
            $logAgeSeconds = time() - $mtime;
            if ($logAgeSeconds > $staleAfterMinutes * 60) {
                $state = 'warning';
                $reason = 'Log ancien de ' . msmCollectorsDuration($logAgeSeconds);
            }
        }
    }

    return [
        'state' => $state,
        'reason' => $reason,
        'script_path' => $scriptPath,
        'script_exists' => is_file($scriptPath),
        'log_path' => $logPath,
        'log_exists' => is_file($logPath),
        'log_mtime' => is_file($logPath) ? filemtime($logPath) : false,
        'last_status' => $lastStatus !== '' ? $lastStatus : 'unknown',
        'last_message' => $lastMessage,
        'last_attempt' => $lastAttempt,
        'last_run' => $lastRun,
        'last_finished' => $lastFinished,
    ];
}

function msmCollectorsDateAgeSeconds(?string $date): ?int
{
    if ($date === null || trim($date) === '') {
        return null;
    }

    try {
        return time() - (new DateTimeImmutable($date))->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}

$phpBinary = msmCollectorsPhpBinary();
$rows = [];
$summary = ['ok' => 0, 'warning' => 0, 'critical' => 0, 'unknown' => 0];

foreach ($checks as $check) {
    $state = msmCollectorsCheckState($check, $root, $logsDirectory, $settings);
    $stateKey = $state['state'] ?? 'unknown';
    $summary[$stateKey] = ($summary[$stateKey] ?? 0) + 1;
    $rows[] = [
        'check' => $check,
        'state' => $state,
        'interval' => msmCollectorsIntervalLabel($check, $settings, $settingsSchema),
        'cron_line' => msmCollectorsCronLine($check, $root, $phpBinary),
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Collecteurs / Checks</h1>
        <p class="mt-1 text-sm text-slate-600">Vue de controle des scripts planifies, logs et intervalles internes MSM.</p>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">OK</div>
            <div class="mt-2 text-2xl font-bold text-green-700"><?= (int) $summary['ok'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Anciens</div>
            <div class="mt-2 text-2xl font-bold text-yellow-700"><?= (int) $summary['warning'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Erreurs</div>
            <div class="mt-2 text-2xl font-bold text-red-700"><?= (int) $summary['critical'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Chemin projet</div>
            <div class="mt-2 break-all text-sm font-semibold text-slate-800"><?= htmlspecialchars(msmCollectorsPath($root)) ?></div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Collecteur</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Dernieres executions</th>
                    <th class="px-4 py-3">Ordonnancement</th>
                    <th class="px-4 py-3">Commande</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($rows as $row):
                    $check = $row['check'];
                    $state = $row['state'];
                ?>
                    <tr class="align-top">
                        <td class="px-4 py-4">
                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($check['name']) ?></div>
                            <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($check['script']) ?></div>
                            <div class="mt-2 text-xs <?= $state['script_exists'] ? 'text-green-700' : 'text-red-700' ?>">
                                <?= $state['script_exists'] ? 'Script present' : 'Script absent' ?>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <?= msmCollectorsStatusBadge($state['state']) ?>
                            <div class="mt-2 text-xs text-slate-600"><?= htmlspecialchars($state['reason']) ?></div>
                            <div class="mt-2 text-xs text-slate-500">Etat script : <?= htmlspecialchars($state['last_status']) ?></div>
                            <?php if ($state['last_message'] !== ''): ?>
                                <div class="mt-2 max-w-xs rounded bg-slate-50 px-2 py-1 text-xs text-slate-700"><?= htmlspecialchars($state['last_message']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-xs text-slate-700">
                            <div>Derniere tentative : <span class="font-semibold"><?= htmlspecialchars(msmDisplayDate($state['last_attempt'])) ?></span></div>
                            <div>Dernier resultat : <span class="font-semibold"><?= htmlspecialchars(msmDisplayDate($state['last_run'])) ?></span></div>
                            <div>Fin execution : <span class="font-semibold"><?= htmlspecialchars(msmDisplayDate($state['last_finished'])) ?></span></div>
                            <div class="mt-2">
                                Log :
                                <?php if ($state['log_exists'] && $state['log_mtime'] !== false): ?>
                                    <span class="font-semibold"><?= htmlspecialchars(msmDisplayDate(date('Y-m-d H:i:s', (int) $state['log_mtime']))) ?></span>
                                <?php else: ?>
                                    <span class="font-semibold text-yellow-700">Absent</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-xs text-slate-700">
                            <div>Cron conseille : <span class="font-mono font-semibold"><?= htmlspecialchars($check['cron']) ?></span></div>
                            <div>Intervalle interne : <span class="font-semibold"><?= htmlspecialchars($row['interval']) ?></span></div>
                            <div class="mt-2 break-all text-slate-500"><?= htmlspecialchars(msmCollectorsPath($state['log_path'])) ?></div>
                        </td>
                        <td class="px-4 py-4">
                            <pre class="max-w-xl overflow-x-auto rounded bg-slate-900 p-3 text-xs text-slate-100"><code><?= htmlspecialchars($row['cron_line']) ?></code></pre>
                            <button type="button"
                                    class="copy-cron mt-2 inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-50"
                                    data-cron="<?= htmlspecialchars($row['cron_line']) ?>">
                                <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                Copier
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.copy-cron').forEach((button) => {
    button.addEventListener('click', async () => {
        const value = button.getAttribute('data-cron') || '';
        try {
            await navigator.clipboard.writeText(value);
            button.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
            button.textContent = 'Copie';
        } catch (error) {
            button.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
            button.textContent = 'Copie impossible';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
