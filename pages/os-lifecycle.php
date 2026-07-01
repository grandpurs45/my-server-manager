<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\EndOfLifeDateClient;
use MSM\OsLifecycleExternalSync;
use MSM\OsLifecycleRepository;

$repository = new OsLifecycleRepository($pdo);
$productsSetting = $settings->get('os_lifecycle', 'external_products');
$externalProducts = OsLifecycleExternalSync::parseProductsText($productsSetting);
$externalSync = new OsLifecycleExternalSync($repository, new EndOfLifeDateClient(), $externalProducts);

function msmOsLifecycleDate(?string $date): string
{
    if ($date === null || trim($date) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($date))->format('d/m/Y');
    } catch (Throwable) {
        return $date;
    }
}

function msmOsLifecycleSupportState(?string $date): array
{
    if ($date === null || trim($date) === '') {
        return ['unknown', 'Inconnu'];
    }

    try {
        $today = new DateTimeImmutable('today');
        $end = new DateTimeImmutable($date);
    } catch (Throwable) {
        return ['unknown', 'Inconnu'];
    }

    if ($end < $today) {
        return ['critical', 'Obsolete'];
    }

    if ($end <= $today->modify('+180 days')) {
        return ['warning', 'Bientot obsolete'];
    }

    return ['ok', 'Supporte'];
}

function msmOsLifecycleSortHeader(string $key, string $label, string $currentSort, string $currentDirection, bool $homelabOnly): string
{
    $nextDirection = ($currentSort === $key && $currentDirection === 'asc') ? 'desc' : 'asc';
    $query = http_build_query([
        'sort' => $key,
        'dir' => $nextDirection,
        'homelab_only' => $homelabOnly ? '1' : '0',
    ]);
    $indicator = $currentSort === $key ? ($currentDirection === 'asc' ? ' (asc)' : ' (desc)') : '';

    return '<a class="inline-flex items-center gap-1 hover:text-blue-700" href="?' . htmlspecialchars($query) . '">'
        . htmlspecialchars($label . $indicator)
        . '</a>';
}

function msmOsLifecycleSortValue(array $reference, string $sort): int|string
{
    [$state] = msmOsLifecycleSupportState($reference['support_ends_at'] ?? null);

    return match ($sort) {
        'os' => strtolower((string) ($reference['os_family'] ?? '') . ' ' . (string) ($reference['os_version'] ?? '')),
        'support_ends_at' => !empty($reference['support_ends_at']) ? strtotime((string) $reference['support_ends_at']) ?: PHP_INT_MAX : PHP_INT_MAX,
        'status' => match ($state) {
            'critical' => 0,
            'warning' => 1,
            'ok' => 2,
            default => 3,
        },
        'servers_count' => (int) ($reference['servers_count'] ?? 0),
        'upgrade' => strtolower((string) ($reference['upgrade_target_version'] ?? '')),
        'source' => strtolower((string) ($reference['source'] ?? '')),
        'updated_at' => !empty($reference['updated_at']) ? strtotime((string) $reference['updated_at']) ?: 0 : 0,
        default => strtolower((string) ($reference['os_family'] ?? '') . ' ' . (string) ($reference['os_version'] ?? '')),
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!msmCsrfIsValid($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton de securite invalide. Merci de reessayer.');
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'save_reference') {
            $repository->saveReference([
                'os_family' => $_POST['os_family'] ?? '',
                'os_version' => $_POST['os_version'] ?? '',
                'os_codename' => $_POST['os_codename'] ?? '',
                'support_ends_at' => $_POST['support_ends_at'] ?? '',
                'upgrade_target_version' => $_POST['upgrade_target_version'] ?? '',
                'upgrade_target_label' => $_POST['upgrade_target_label'] ?? '',
                'source' => $_POST['source'] ?? '',
                'notes' => $_POST['notes'] ?? '',
            ]);
            $_SESSION['success'] = 'Reference OS enregistree.';
        } elseif ($action === 'delete_reference') {
            $id = (int) ($_POST['reference_id'] ?? 0);
            if ($id <= 0 || !$repository->deleteReference($id)) {
                throw new RuntimeException('Reference OS introuvable.');
            }

            $_SESSION['success'] = 'Reference OS supprimee.';
        } elseif ($action === 'save_external_family') {
            $family = OsLifecycleExternalSync::normalizeFamily((string) ($_POST['os_family'] ?? ''));
            $product = OsLifecycleExternalSync::normalizeProduct((string) ($_POST['eol_product'] ?? ''));

            if ($family === null || $product === null) {
                throw new RuntimeException('Famille ou produit endoflife.date invalide.');
            }

            $externalProducts[$family] = $product;
            $settings->set('os_lifecycle', 'external_products', OsLifecycleExternalSync::productsToText($externalProducts));
            $_SESSION['success'] = 'Famille synchronisable enregistree.';
        } elseif ($action === 'delete_external_family') {
            $family = OsLifecycleExternalSync::normalizeFamily((string) ($_POST['os_family'] ?? ''));
            if ($family === null || !isset($externalProducts[$family])) {
                throw new RuntimeException('Famille synchronisable introuvable.');
            }

            unset($externalProducts[$family]);
            $settings->set('os_lifecycle', 'external_products', OsLifecycleExternalSync::productsToText($externalProducts));
            $_SESSION['success'] = 'Famille synchronisable supprimee.';
        } elseif ($action === 'sync_external') {
            $family = trim((string) ($_POST['sync_family'] ?? ''));
            $summary = $externalSync->sync($family !== '' ? $family : null);
            $parts = [];
            foreach ($summary as $osFamily => $count) {
                $parts[] = $osFamily . ': ' . $count;
            }
            $_SESSION['success'] = 'Synchronisation endoflife.date terminee (' . implode(', ', $parts) . ').';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: os-lifecycle.php');
    exit;
}

$allowedSorts = ['os', 'support_ends_at', 'status', 'servers_count', 'upgrade', 'source', 'updated_at'];
$sort = in_array($_GET['sort'] ?? '', $allowedSorts, true) ? (string) $_GET['sort'] : 'os';
$direction = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
$homelabPreferenceKey = 'msm_os_lifecycle_homelab_only_' . (int) ($currentUser['id'] ?? 0);
if (array_key_exists('homelab_only', $_GET)) {
    $homelabOnly = ($_GET['homelab_only'] ?? '') === '1';
    $_SESSION[$homelabPreferenceKey] = $homelabOnly ? '1' : '0';
} else {
    $homelabOnly = ($_SESSION[$homelabPreferenceKey] ?? '0') === '1';
}

$detectedFamilies = $repository->getDetectedOsFamilies();
$references = $repository->getReferencesWithUsage();
if ($homelabOnly) {
    $references = array_values(array_filter(
        $references,
        fn (array $reference): bool => (int) ($reference['servers_count'] ?? 0) > 0
    ));
}
usort($references, function (array $a, array $b) use ($sort, $direction): int {
    $valueA = msmOsLifecycleSortValue($a, $sort);
    $valueB = msmOsLifecycleSortValue($b, $sort);
    $compare = is_string($valueA) || is_string($valueB)
        ? strcasecmp((string) $valueA, (string) $valueB)
        : $valueA <=> $valueB;

    return $direction === 'desc' ? -$compare : $compare;
});
$supportedProducts = $externalProducts;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Cycle de vie OS</h1>
        <p class="mt-1 text-sm text-slate-600">
            Referentiel local des dates de fin de support utilise par les checks OS lifecycle et les alertes.
        </p>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Synchronisation externe</h2>
        <p class="mt-1 text-sm text-slate-600">
            MSM importe les cycles depuis endoflife.date, puis conserve les donnees en base locale.
            <a href="https://endoflife.date/api/all.json" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 hover:underline">
                Voir la liste des produits API
            </a>
            <span class="text-slate-400">-</span>
            <a href="https://endoflife.date/docs/api" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 hover:underline">
                Documentation API
            </a>
        </p>
        <form method="post" class="mt-4 flex flex-col gap-3 md:flex-row md:items-end">
            <?= msmCsrfField() ?>
            <input type="hidden" name="action" value="sync_external">
            <label class="text-sm font-semibold text-slate-700">
                Famille
                <select name="sync_family" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <option value="">Toutes les familles supportees</option>
                    <?php foreach ($supportedProducts as $family => $product): ?>
                        <option value="<?= htmlspecialchars($family) ?>">
                            <?= htmlspecialchars($family . ' (' . $product . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Synchroniser
            </button>
        </form>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="rounded border border-gray-200 p-4">
                <h3 class="font-semibold text-slate-900">Familles synchronisables</h3>
                <div class="mt-3 divide-y divide-gray-100">
                    <?php foreach ($supportedProducts as $family => $product): ?>
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($family) ?></div>
                                <div class="text-xs text-slate-500">endoflife.date/api/<?= htmlspecialchars($product) ?>.json</div>
                            </div>
                            <form method="post" onsubmit="return confirm('Supprimer cette famille synchronisable ?');">
                                <?= msmCsrfField() ?>
                                <input type="hidden" name="action" value="delete_external_family">
                                <input type="hidden" name="os_family" value="<?= htmlspecialchars($family) ?>">
                                <button type="submit" class="rounded border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="post" class="rounded border border-gray-200 p-4">
                <?= msmCsrfField() ?>
                <input type="hidden" name="action" value="save_external_family">
                <h3 class="font-semibold text-slate-900">Ajouter une famille</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">
                        Famille MSM
                        <input name="os_family" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="alpine">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">
                        Produit endoflife.date
                        <input name="eol_product" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="alpine">
                    </label>
                </div>
                <button type="submit" class="mt-3 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Ajouter la famille
                </button>
            </form>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Ajouter ou modifier une reference</h2>
        <form method="post" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-4">
            <?= msmCsrfField() ?>
            <input type="hidden" name="action" value="save_reference">
            <label class="text-sm font-semibold text-slate-700">
                Famille OS
                <input name="os_family" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="ubuntu">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Version
                <input name="os_version" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="24.04">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Codename
                <input name="os_codename" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="noble">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Fin de support
                <input type="date" name="support_ends_at" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Upgrade cible
                <input name="upgrade_target_version" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="26.04">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Libelle upgrade
                <input name="upgrade_target_label" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="Ubuntu 26.04 LTS">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Source
                <input name="source" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="endoflife.date/ubuntu">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Notes
                <input name="notes" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <div class="lg:col-span-4">
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Enregistrer
                </button>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">References OS</h2>
                <p class="mt-1 text-sm text-slate-600">
                    <?= count($references) ?> reference<?= count($references) > 1 ? 's' : '' ?> affichee<?= count($references) > 1 ? 's' : '' ?>.
                    <?php if ($detectedFamilies !== []): ?>
                        Familles detectees : <?= htmlspecialchars(implode(', ', $detectedFamilies)) ?>.
                    <?php endif; ?>
                </p>
            </div>
            <form method="get" class="flex items-center gap-2 text-sm">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($direction) ?>">
                <input type="hidden" name="homelab_only" value="0">
                <label class="inline-flex items-center gap-2 font-semibold text-slate-700">
                    <input type="checkbox" name="homelab_only" value="1" <?= $homelabOnly ? 'checked' : '' ?> class="rounded border-gray-300">
                    Uniquement les OS detectes dans le homelab
                </label>
                <button type="submit" class="rounded border border-gray-300 px-3 py-1.5 font-semibold text-slate-700 hover:bg-gray-50">
                    Appliquer
                </button>
            </form>
        </div>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('os', 'OS', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('servers_count', 'Serveurs', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('support_ends_at', 'Fin support', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('status', 'Statut calcule', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('upgrade', 'Upgrade', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('source', 'Source', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3"><?= msmOsLifecycleSortHeader('updated_at', 'Derniere MAJ', $sort, $direction, $homelabOnly) ?></th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($references === []): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucune reference OS pour ces filtres.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($references as $reference): ?>
                    <?php [$state, $label] = msmOsLifecycleSupportState($reference['support_ends_at'] ?? null); ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">
                                <?= htmlspecialchars($reference['os_family']) ?> <?= htmlspecialchars($reference['os_version']) ?>
                            </div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($reference['os_codename'] ?? '-') ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                <?= (int) ($reference['servers_count'] ?? 0) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-900">
                            <?= htmlspecialchars(msmOsLifecycleDate($reference['support_ends_at'] ?? null)) ?>
                        </td>
                        <td class="px-4 py-3"><?= msmStatusBadge($state, $label) ?></td>
                        <td class="px-4 py-3">
                            <?php if (!empty($reference['upgrade_target_version'])): ?>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-blue-700"><?= htmlspecialchars($reference['upgrade_target_version']) ?></span>
                                    <?php if (($reference['upgrade_source'] ?? '') === 'auto'): ?>
                                        <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">auto</span>
                                    <?php elseif (($reference['upgrade_source'] ?? '') === 'manual'): ?>
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">manuel</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($reference['upgrade_target_label'] ?? '') ?></div>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-slate-700"><?= htmlspecialchars($reference['source'] ?? '-') ?></div>
                            <?php if (!empty($reference['notes'])): ?>
                                <div class="mt-1 max-w-lg text-xs text-slate-500"><?= htmlspecialchars($reference['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <?= htmlspecialchars(msmDisplayDate($reference['updated_at'] ?? null, '-')) ?>
                        </td>
                        <td class="px-4 py-3">
                            <form method="post" onsubmit="return confirm('Supprimer cette reference OS ?');">
                                <?= msmCsrfField() ?>
                                <input type="hidden" name="action" value="delete_reference">
                                <input type="hidden" name="reference_id" value="<?= (int) $reference['id'] ?>">
                                <button type="submit" class="rounded border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                    Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
