<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\SecurityStatusRepository;

$repository = new SecurityStatusRepository($pdo);
$serveurs = $repository->getOverview();
$excludedCount = $repository->countDisabledTargets();

function msmSecurityStatusBadge(?string $status): string
{
    return match ($status) {
        'ok' => '<span class="inline-flex rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">OK</span>',
        'warning' => '<span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">A verifier</span>',
        'error' => '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Erreur</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Jamais verifie</span>',
    };
}

function msmFirewallBadge(?string $status): string
{
    return match ($status) {
        'actif' => '<span class="inline-flex rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Actif</span>',
        'inactif' => '<span class="inline-flex rounded bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800">Inactif</span>',
        'not_installed' => '<span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Non installe</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>',
    };
}
?>

<h1 class="text-2xl font-bold mb-6">Securite des serveurs</h1>

<div class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
    <p class="text-sm text-slate-600">
        Cette vue affiche les derniers controles securite stockes en base. Aucun check SSH n'est lance depuis la page.
    </p>
    <p class="mt-2 text-sm text-slate-500">
        Script a planifier : <code>php scripts/check-security.php</code>.
    </p>
    <?php if ($excludedCount > 0): ?>
        <p class="mt-2 text-sm text-gray-500">
            <?= $excludedCount ?> cible<?= $excludedCount > 1 ? 's' : '' ?> exclue<?= $excludedCount > 1 ? 's' : '' ?> du module securite.
        </p>
    <?php endif; ?>
</div>

<div class="overflow-x-auto bg-white shadow rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-blue-700 text-white">
        <tr>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Nom</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">OS</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Statut</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Pare-feu</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Ports</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Dernier check</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Action</th>
        </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
        <?php if (empty($serveurs)): ?>
            <tr>
                <td colspan="7" class="px-6 py-6 text-center text-gray-500">
                    Aucune cible n'est activee pour l'analyse securite.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($serveurs as $srv): ?>
                <tr>
                    <td class="px-6 py-4 font-medium">
                        <?= htmlspecialchars($srv['name']) ?>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars($srv['hostname']) ?></div>
                    </td>
                    <td class="px-6 py-4"><?= htmlspecialchars($srv['os'] ?: 'OS inconnu') ?></td>
                    <td class="px-6 py-4"><?= msmSecurityStatusBadge($srv['security_status'] ?? null) ?></td>
                    <td class="px-6 py-4"><?= msmFirewallBadge($srv['firewall_status'] ?? null) ?></td>
                    <td class="px-6 py-4 text-sm">
                        <span class="font-semibold text-slate-900"><?= (int) ($srv['open_ports_count'] ?? 0) ?></span>
                        <span class="text-slate-500">ouverts</span>
                        <?php if ((int) ($srv['exposed_ports_count'] ?? 0) > 0): ?>
                            <div class="mt-1 text-xs font-semibold text-red-700">
                                <?= (int) $srv['exposed_ports_count'] ?> expose<?= (int) $srv['exposed_ports_count'] > 1 ? 's' : '' ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">
                        <?= htmlspecialchars($srv['checked_at'] ?? 'Jamais') ?>
                        <?php if (!empty($srv['error_message'])): ?>
                            <div class="mt-1 text-xs text-red-600"><?= htmlspecialchars($srv['error_message']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <a href="<?= $baseUrl ?>pages/details-securite.php?id=<?= (int) $srv['id'] ?>" class="text-blue-600 hover:underline text-sm">Voir details</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
