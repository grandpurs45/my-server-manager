<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\AlertRepository;

$repository = new AlertRepository($pdo);
$allowedSeverities = ['critical', 'warning', 'info'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    msmRequireValidCsrf('alert-rules.php');

    $ruleKey = trim((string) ($_POST['rule_key'] ?? ''));
    $enabled = isset($_POST['enabled']);
    $severity = (string) ($_POST['severity'] ?? 'warning');
    $thresholdRaw = trim((string) ($_POST['threshold_value'] ?? ''));

    if (!in_array($severity, $allowedSeverities, true)) {
        $severity = 'warning';
    }

    $thresholdValue = $thresholdRaw === '' ? null : max(1, (int) $thresholdRaw);

    if ($ruleKey !== '') {
        $repository->updateRule($ruleKey, $enabled, $severity, $thresholdValue);
        $_SESSION['success'] = 'Regle d alerte mise a jour.';
    }

    header('Location: alert-rules.php');
    exit;
}

$rules = $repository->getRules();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Regles d'alertes</h1>
            <p class="mt-1 text-sm text-slate-600">
                Parametrage minimal des regles globales utilisees par <code>scripts/check-alerts.php</code>.
            </p>
        </div>
        <a href="<?= $baseUrl ?>pages/alerts.php"
           class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
            <i data-lucide="bell" class="h-4 w-4"></i>
            Voir les alertes
        </a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Regle</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Source</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Active</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Severite</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Seuil</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!$rules): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucune regle d'alerte trouvee. Appliquer les migrations.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rules as $rule): ?>
                    <tr class="hover:bg-slate-50">
                        <form method="post">
                            <?= msmCsrfField() ?>
                            <input type="hidden" name="rule_key" value="<?= htmlspecialchars($rule['rule_key']) ?>">
                            <td class="px-4 py-3 align-top">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($rule['name']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($rule['rule_key']) ?></div>
                            </td>
                            <td class="px-4 py-3 align-top text-sm text-slate-700">
                                <?= htmlspecialchars($rule['source']) ?>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <input type="checkbox" name="enabled" value="1" <?= !empty($rule['enabled']) ? 'checked' : '' ?>>
                                    Active
                                </label>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <select name="severity" class="rounded border border-gray-300 px-3 py-2 text-sm">
                                    <?php foreach ($allowedSeverities as $severity): ?>
                                        <option value="<?= $severity ?>" <?= ($rule['severity'] ?? '') === $severity ? 'selected' : '' ?>>
                                            <?= ucfirst($severity) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <input type="number"
                                       name="threshold_value"
                                       min="1"
                                       value="<?= htmlspecialchars((string) ($rule['threshold_value'] ?? '')) ?>"
                                       class="w-28 rounded border border-gray-300 px-3 py-2 text-sm"
                                       placeholder="-">
                                <?php if (($rule['rule_key'] ?? '') === 'stale_supervision_check'): ?>
                                    <div class="mt-1 text-xs text-slate-500">minutes</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    Enregistrer
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
