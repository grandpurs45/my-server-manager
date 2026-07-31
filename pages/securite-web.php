<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory_options.php';

use MSM\AlertRepository;
use MSM\WebTargetRepository;

$repository = new WebTargetRepository($pdo);
$alertRepository = new AlertRepository($pdo);

function msmRedirectWebMonitoring(): never
{
    header('Location: securite-web.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    msmRequireValidCsrf('securite-web.php');
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $repository->create($_POST);
            $_SESSION['success'] = 'Cible URL ajoutee. Elle sera controlee au prochain passage du collecteur.';
        } elseif ($action === 'update') {
            $repository->update((int) ($_POST['target_id'] ?? 0), $_POST);
            $_SESSION['success'] = 'Cible URL mise a jour.';
        } elseif ($action === 'toggle') {
            $targetId = (int) ($_POST['target_id'] ?? 0);
            $enabled = ($_POST['enabled'] ?? '0') === '1';
            $repository->setEnabled($targetId, $enabled);
            $resolved = $enabled ? 0 : $alertRepository->resolveActiveAlertsForWebTarget(
                $targetId,
                'Alerte resolue automatiquement car la cible URL a ete desactivee.'
            );
            $_SESSION['success'] = $enabled
                ? 'Cible URL reactivee. Elle sera controlee au prochain passage du collecteur.'
                : 'Cible URL desactivee. ' . $resolved . ' alerte(s) active(s) resolue(s).';
        } elseif ($action === 'delete') {
            $repository->delete((int) ($_POST['target_id'] ?? 0));
            $_SESSION['success'] = 'Cible URL et son historique supprimes.';
        } else {
            throw new InvalidArgumentException('Action inconnue.');
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    msmRedirectWebMonitoring();
}

$targets = $repository->listTargets();
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editTarget = $editId > 0 ? $repository->find($editId) : null;
$environments = msmInventoryOptions($settings, 'environments');
$criticalities = msmInventoryOptions($settings, 'criticalities');
if ($editTarget !== null && !isset($environments[$editTarget['environment']])) {
    $environments[$editTarget['environment']] = $editTarget['environment'];
}
if ($editTarget !== null && !isset($criticalities[$editTarget['criticality']])) {
    $criticalities[$editTarget['criticality']] = $editTarget['criticality'];
}
$form = $editTarget ?? [
    'name' => '',
    'url' => '',
    'enabled' => 1,
    'environment' => array_key_first($environments) ?: 'production',
    'criticality' => 'medium',
    'interval_minutes' => 5,
    'timeout_seconds' => 10,
    'follow_redirects' => 1,
    'verify_tls' => 1,
    'expected_status_codes' => '200-399',
    'expected_content' => '',
    'failure_threshold' => 2,
];

$summary = ['total' => count($targets), 'enabled' => 0, 'up' => 0, 'down' => 0, 'tls_warning' => 0];
foreach ($targets as $target) {
    if ((int) $target['enabled'] !== 1) {
        continue;
    }

    $summary['enabled']++;
    if ($target['last_success'] !== null) {
        $summary[(int) $target['last_success'] === 1 ? 'up' : 'down']++;
    }
    if ($target['last_certificate_expiry_days'] !== null && (int) $target['last_certificate_expiry_days'] <= 30) {
        $summary['tls_warning']++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 p-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Supervision des URLs</h1>
            <p class="mt-1 text-sm text-slate-600">Disponibilite HTTP, performances, certificats TLS et contenu attendu.</p>
        </div>
        <a href="<?= $baseUrl ?>pages/collectors.php" class="inline-flex items-center gap-2 self-start rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
            <i data-lucide="workflow" class="h-4 w-4"></i>
            Etat du collecteur
        </a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <?php foreach ([
            ['Total', $summary['total'], 'globe-2', 'text-slate-900'],
            ['Actives', $summary['enabled'], 'circle-play', 'text-blue-700'],
            ['Disponibles', $summary['up'], 'circle-check', 'text-green-700'],
            ['En erreur', $summary['down'], 'circle-x', 'text-red-700'],
            ['TLS a surveiller', $summary['tls_warning'], 'shield-alert', 'text-amber-700'],
        ] as [$label, $value, $icon, $color]): ?>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between text-xs font-semibold uppercase text-slate-500">
                    <?= htmlspecialchars($label) ?>
                    <i data-lucide="<?= htmlspecialchars($icon) ?>" class="h-4 w-4"></i>
                </div>
                <div class="mt-2 text-3xl font-bold <?= $color ?>"><?= (int) $value ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <details class="rounded-lg border border-gray-200 bg-white shadow-sm" <?= $editTarget !== null ? 'open' : '' ?>>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
            <span class="text-lg font-semibold"><?= $editTarget !== null ? 'Modifier la cible URL' : 'Ajouter une cible URL' ?></span>
            <span class="inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white">
                <i data-lucide="<?= $editTarget !== null ? 'pencil' : 'plus' ?>" class="h-4 w-4"></i>
                <?= $editTarget !== null ? 'Edition' : 'Nouvelle URL' ?>
            </span>
        </summary>
        <form method="post" class="space-y-5 border-t border-gray-200 p-5">
            <?= msmCsrfField() ?>
            <input type="hidden" name="action" value="<?= $editTarget !== null ? 'update' : 'create' ?>">
            <?php if ($editTarget !== null): ?>
                <input type="hidden" name="target_id" value="<?= (int) $editTarget['id'] ?>">
            <?php endif; ?>

            <div class="grid gap-4 lg:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700 lg:col-span-1">Nom
                    <input name="name" required maxlength="150" value="<?= htmlspecialchars((string) $form['name']) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="Site MSM">
                </label>
                <label class="text-sm font-semibold text-slate-700 lg:col-span-3">URL
                    <input name="url" type="url" required maxlength="2048" value="<?= htmlspecialchars((string) $form['url']) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 font-mono text-sm" placeholder="https://msm.example.local/">
                </label>
                <label class="text-sm font-semibold text-slate-700">Environnement
                    <select name="environment" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        <?php foreach ($environments as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= (string) $form['environment'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Criticite
                    <select name="criticality" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        <?php foreach ($criticalities as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= (string) $form['criticality'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Intervalle (minutes)
                    <input name="interval_minutes" type="number" min="1" max="1440" required value="<?= (int) $form['interval_minutes'] ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="text-sm font-semibold text-slate-700">Timeout (secondes)
                    <input name="timeout_seconds" type="number" min="1" max="60" required value="<?= (int) $form['timeout_seconds'] ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="text-sm font-semibold text-slate-700">Codes HTTP acceptes
                    <input name="expected_status_codes" required value="<?= htmlspecialchars((string) $form['expected_status_codes']) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 font-mono" placeholder="200-399,401">
                </label>
                <label class="text-sm font-semibold text-slate-700 lg:col-span-2">Contenu attendu <span class="font-normal text-slate-400">(facultatif)</span>
                    <input name="expected_content" maxlength="255" value="<?= htmlspecialchars((string) ($form['expected_content'] ?? '')) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="My Server Manager">
                </label>
                <label class="text-sm font-semibold text-slate-700">Echecs avant alerte
                    <input name="failure_threshold" type="number" min="1" max="10" required value="<?= (int) $form['failure_threshold'] ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
            </div>

            <div class="flex flex-wrap gap-5">
                <?php foreach ([
                    ['enabled', 'Cible active'],
                    ['follow_redirects', 'Suivre les redirections'],
                    ['verify_tls', 'Verifier le certificat TLS'],
                ] as [$field, $label]): ?>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="<?= $field ?>" value="1" <?= !empty($form[$field]) ? 'checked' : '' ?> class="rounded border-gray-300">
                        <?= htmlspecialchars($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Enregistrer
                </button>
                <?php if ($editTarget !== null): ?>
                    <a href="securite-web.php" class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </details>

    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">URLs supervisees</h2>
                <p class="text-xs text-slate-500">Les controles sont executes uniquement par <code>scripts/check-web.php</code>.</p>
            </div>
            <div class="relative w-full sm:w-80">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                <input id="web-target-search" type="search" placeholder="Rechercher une URL" class="w-full rounded border border-gray-300 py-2 pl-9 pr-3 text-sm">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Cible</th>
                        <th class="px-5 py-3">Etat</th>
                        <th class="px-5 py-3">HTTP</th>
                        <th class="px-5 py-3">Duree</th>
                        <th class="px-5 py-3">Certificat</th>
                        <th class="px-5 py-3">Dernier check</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="web-target-rows" class="divide-y divide-gray-200">
                    <?php foreach ($targets as $target):
                        $enabled = (int) $target['enabled'] === 1;
                        $hasResult = $target['last_success'] !== null;
                        $up = $hasResult && (int) $target['last_success'] === 1;
                        $search = mb_strtolower(trim($target['name'] . ' ' . $target['url'] . ' ' . $target['environment']));
                    ?>
                        <tr data-web-target-row data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" class="align-top">
                            <td class="px-5 py-4">
                                <a href="<?= htmlspecialchars((string) $target['url']) ?>" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 hover:underline"><?= htmlspecialchars((string) $target['name']) ?></a>
                                <div class="mt-1 max-w-md break-all font-mono text-xs text-slate-500"><?= htmlspecialchars((string) $target['url']) ?></div>
                                <div class="mt-2 flex flex-wrap gap-1 text-xs">
                                    <span class="rounded bg-slate-100 px-2 py-1 text-slate-600"><?= htmlspecialchars((string) ($environments[$target['environment']] ?? $target['environment'])) ?></span>
                                    <span class="rounded bg-slate-100 px-2 py-1 text-slate-600"><?= htmlspecialchars((string) ($criticalities[$target['criticality']] ?? $target['criticality'])) ?></span>
                                    <span class="rounded bg-slate-100 px-2 py-1 text-slate-600"><?= (int) $target['interval_minutes'] ?> min</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <?php if (!$enabled): ?>
                                    <span class="rounded bg-slate-100 px-2 py-1 font-semibold text-slate-600">Desactivee</span>
                                <?php elseif (!$hasResult): ?>
                                    <span class="rounded bg-blue-100 px-2 py-1 font-semibold text-blue-700">En attente</span>
                                <?php elseif ($up): ?>
                                    <span class="rounded bg-green-100 px-2 py-1 font-semibold text-green-700">Disponible</span>
                                <?php else: ?>
                                    <span class="rounded bg-red-100 px-2 py-1 font-semibold text-red-700">En erreur</span>
                                    <div class="mt-2 max-w-xs text-xs text-red-600"><?= htmlspecialchars((string) ($target['last_error_message'] ?? 'Echec du controle.')) ?></div>
                                <?php endif; ?>
                                <?php if ((int) $target['consecutive_failures'] > 0): ?>
                                    <div class="mt-2 text-xs text-slate-500"><?= (int) $target['consecutive_failures'] ?> / <?= (int) $target['failure_threshold'] ?> echec(s)</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 font-mono"><?= $target['last_http_status'] !== null ? (int) $target['last_http_status'] : '-' ?></td>
                            <td class="px-5 py-4"><?= $target['last_total_ms'] !== null ? number_format((float) $target['last_total_ms'], 0, ',', ' ') . ' ms' : '-' ?></td>
                            <td class="px-5 py-4">
                                <?php if ($target['last_certificate_expiry_days'] !== null): ?>
                                    <span class="font-semibold <?= (int) $target['last_certificate_expiry_days'] <= 30 ? 'text-amber-700' : 'text-green-700' ?>"><?= (int) $target['last_certificate_expiry_days'] ?> j</span>
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <?= htmlspecialchars(msmDisplayDate($target['result_checked_at'])) ?>
                                <?php if ($target['next_check_at'] !== null && $enabled): ?>
                                    <div class="mt-1 text-xs text-slate-400">Prochain : <?= htmlspecialchars(msmDisplayDate($target['next_check_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="?edit=<?= (int) $target['id'] ?>" class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-300 text-blue-700 hover:bg-blue-50" title="Modifier"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                                    <form method="post">
                                        <?= msmCsrfField() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                                        <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
                                        <button class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-300 <?= $enabled ? 'text-amber-700' : 'text-green-700' ?> hover:bg-gray-50" title="<?= $enabled ? 'Desactiver' : 'Activer' ?>"><i data-lucide="<?= $enabled ? 'pause' : 'play' ?>" class="h-4 w-4"></i></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Supprimer cette cible URL et son historique ?');">
                                        <?= msmCsrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                                        <button class="inline-flex h-9 w-9 items-center justify-center rounded border border-red-200 text-red-700 hover:bg-red-50" title="Supprimer"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($targets === []): ?>
                        <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">Aucune URL configuree.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
document.getElementById('web-target-search')?.addEventListener('input', function () {
    const query = this.value.trim().toLocaleLowerCase('fr');
    document.querySelectorAll('[data-web-target-row]').forEach((row) => {
        row.classList.toggle('hidden', query !== '' && !row.dataset.search.includes(query));
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
