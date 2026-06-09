<?php
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\SecurityStatusRepository;

$repository = new SecurityStatusRepository($pdo);
$serveurs = $repository->getOverview();
$excludedCount = $repository->countDisabledTargets();

function msmSecurityStatusBadge(?string $status): string
{
    return msmStatusBadge(msmStatusStateFromSecurity($status), msmStatusLabelFromSecurity($status));
}

function msmFirewallBadge(?string $status): string
{
    return match ($status) {
        'actif' => msmStatusBadge('ok', 'OK'),
        'inactif' => msmStatusBadge('critical', 'Critical'),
        'not_installed' => msmStatusBadge('warning', 'Warning'),
        default => msmStatusBadge('unknown', 'Unknown'),
    };
}

function msmSecurityDate(?string $date): string
{
    return $date !== null && $date !== '' ? $date : 'Jamais';
}

$summary = [
    'targets' => count($serveurs),
    'critical' => 0,
    'warning' => 0,
    'exposed_ports' => 0,
    'firewall_warnings' => 0,
    'errors' => 0,
];

foreach ($serveurs as $srv) {
    $statusState = msmStatusStateFromSecurity($srv['security_status'] ?? null);
    if ($statusState === 'critical') {
        $summary['critical']++;
    } elseif ($statusState === 'warning') {
        $summary['warning']++;
    }

    $summary['exposed_ports'] += (int) ($srv['exposed_ports_count'] ?? 0);

    if (in_array(($srv['firewall_status'] ?? null), ['inactif', 'not_installed', null, ''], true)) {
        $summary['firewall_warnings']++;
    }

    if (!empty($srv['error_message'])) {
        $summary['errors']++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Securite operationnelle</h1>
        <p class="mt-1 text-sm text-slate-600">
            Derniers controles stockes en base : ports ouverts, exposition reseau, pare-feu et erreurs de collecte.
            Aucun check SSH n'est lance depuis cette page.
        </p>
        <p class="mt-1 text-xs text-slate-500">
            Script a planifier : <code>php scripts/check-security.php</code>
        </p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Cibles actives</div>
            <div class="mt-2 text-2xl font-bold text-slate-900"><?= (int) $summary['targets'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Critical</div>
            <div class="mt-2 text-2xl font-bold text-red-700"><?= (int) $summary['critical'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Warning</div>
            <div class="mt-2 text-2xl font-bold text-yellow-700"><?= (int) $summary['warning'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Ports exposes</div>
            <div class="mt-2 text-2xl font-bold text-red-700"><?= (int) $summary['exposed_ports'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Pare-feu a verifier</div>
            <div class="mt-2 text-2xl font-bold text-yellow-700"><?= (int) $summary['firewall_warnings'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Exclues</div>
            <div class="mt-2 text-2xl font-bold text-slate-700"><?= (int) $excludedCount ?></div>
        </div>
    </div>

    <?php if ($excludedCount > 0): ?>
        <div class="mb-4 rounded border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
            <?= (int) $excludedCount ?> cible<?= $excludedCount > 1 ? 's' : '' ?>
            exclue<?= $excludedCount > 1 ? 's' : '' ?> du module securite depuis les options serveur.
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-100 text-left text-sm text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-semibold">Cible</th>
                    <th class="px-4 py-3 font-semibold">Statut</th>
                    <th class="px-4 py-3 font-semibold">Exposition</th>
                    <th class="px-4 py-3 font-semibold">Pare-feu</th>
                    <th class="px-4 py-3 font-semibold">Dernier check</th>
                    <th class="px-4 py-3 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
            <?php if (empty($serveurs)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-6 text-center text-gray-500">
                        Aucune cible n'est activee pour l'analyse securite.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($serveurs as $srv): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <a href="<?= $baseUrl ?>pages/details-cible.php?id=<?= (int) $srv['id'] ?>"
                               class="font-semibold text-blue-700 hover:underline">
                                <?= htmlspecialchars($srv['name']) ?>
                            </a>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($srv['hostname']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($srv['os'] ?: 'OS inconnu') ?></div>
                        </td>
                        <td class="px-4 py-3"><?= msmSecurityStatusBadge($srv['security_status'] ?? null) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <div>
                                <span class="font-semibold text-slate-900"><?= (int) ($srv['open_ports_count'] ?? 0) ?></span>
                                <span class="text-slate-500">port(s) ouvert(s)</span>
                            </div>
                            <?php if ((int) ($srv['exposed_ports_count'] ?? 0) > 0): ?>
                                <div class="mt-1 font-semibold text-red-700">
                                    <?= (int) $srv['exposed_ports_count'] ?> expose<?= (int) $srv['exposed_ports_count'] > 1 ? 's' : '' ?>
                                </div>
                            <?php else: ?>
                                <div class="mt-1 text-xs text-green-700">Aucun port public detecte</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?= msmFirewallBadge($srv['firewall_status'] ?? null) ?></td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?= htmlspecialchars(msmSecurityDate($srv['checked_at'] ?? null)) ?>
                            <?php if (!empty($srv['error_message'])): ?>
                                <div class="mt-1 max-w-md text-xs text-red-600"><?= htmlspecialchars($srv['error_message']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="<?= $baseUrl ?>pages/details-securite.php?id=<?= (int) $srv['id'] ?>"
                               class="text-sm font-semibold text-blue-700 hover:underline">
                                Voir details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
