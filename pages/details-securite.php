<?php
session_start();

require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\SecurityStatusRepository;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
$stmt->execute([$id]);
$serveur = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

if (!$serveur) {
    echo '<div class="text-red-600 font-bold">Serveur introuvable.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (empty($serveur['security_enabled'])) {
    ?>
    <a href="<?= $baseUrl ?>pages/securite-serveurs.php"
       class="inline-block mb-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded shadow">
        Retour a la liste des serveurs
    </a>

    <div class="rounded border border-slate-200 bg-white p-6 shadow">
        <h1 class="text-2xl font-bold mb-3">Analyse securite desactivee - <?= htmlspecialchars($serveur['name']) ?></h1>
        <p class="text-sm text-slate-600 mb-4">
            Cette cible n'est pas incluse dans le module securite. Aucun controle SSH ou systeme n'a ete lance.
        </p>
        <a href="<?= $baseUrl ?>pages/serveurs.php?edit=<?= (int) $serveur['id'] ?>"
           class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Activer dans les options du serveur
        </a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$repository = new SecurityStatusRepository($pdo);
$latestSecurityCheck = $repository->getLatestForServer((int) $serveur['id']);
$ports = $latestSecurityCheck ? $repository->getPortsForCheck((int) $latestSecurityCheck['id']) : [];

function msmSecurityDetailStatusBadge(?string $status): string
{
    return msmStatusBadge(msmStatusStateFromSecurity($status), msmStatusLabelFromSecurity($status));
}

function msmSecurityDetailFirewallBadge(?string $status): string
{
    return match ($status) {
        'actif' => '<span class="inline-flex rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Actif</span>',
        'inactif' => '<span class="inline-flex rounded bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800">Inactif</span>',
        'not_installed' => '<span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Non installe</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>',
    };
}

function msmSecurityExposureBadge(string $exposure): string
{
    return match ($exposure) {
        'public' => '<span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Expose</span>',
        'local' => '<span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Local</span>',
        'bound' => '<span class="rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">Adresse liee</span>',
        default => '<span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>',
    };
}
?>

<a href="<?= $baseUrl ?>pages/securite-serveurs.php"
   class="inline-block mb-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded shadow">
    Retour a la liste des serveurs
</a>

<h1 class="text-2xl font-bold mb-4">Details securite - <?= htmlspecialchars($serveur['name']) ?></h1>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Informations generales</h2>
        <p><strong>Nom :</strong> <?= htmlspecialchars($serveur['name']) ?></p>
        <p><strong>OS :</strong> <?= htmlspecialchars($serveur['os'] ?: 'OS inconnu') ?></p>
        <p><strong>Statut :</strong> <?= $serveur['status'] === 'up' ? 'UP' : 'DOWN' ?></p>
        <p><strong>Adresse :</strong> <?= htmlspecialchars($serveur['hostname']) ?>:<?= (int) $serveur['ssh_port'] ?></p>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Dernier controle securite</h2>
        <?php if (!$latestSecurityCheck): ?>
            <p class="text-sm italic text-gray-500">Aucun controle securite enregistre.</p>
        <?php else: ?>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Statut</span>
                    <?= msmSecurityDetailStatusBadge($latestSecurityCheck['status'] ?? null) ?>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Pare-feu</span>
                    <?= msmSecurityDetailFirewallBadge($latestSecurityCheck['firewall_status'] ?? null) ?>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Date</span>
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($latestSecurityCheck['checked_at'] ?? '-') ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Ports exposes</span>
                    <span class="font-semibold text-red-700"><?= (int) ($latestSecurityCheck['exposed_ports_count'] ?? 0) ?></span>
                </div>
            </div>
            <?php if (!empty($latestSecurityCheck['error_message'])): ?>
                <p class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">
                    <?= htmlspecialchars($latestSecurityCheck['error_message']) ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Ports ouverts</h2>
        <?php if (!$latestSecurityCheck): ?>
            <p class="text-sm italic text-gray-500">Aucun controle securite enregistre.</p>
        <?php elseif (!$ports): ?>
            <p class="text-gray-500 italic">Aucun port detecte.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded border border-gray-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Protocole</th>
                            <th class="px-3 py-2 font-semibold">Adresse</th>
                            <th class="px-3 py-2 font-semibold">Port</th>
                            <th class="px-3 py-2 font-semibold">Exposition</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($ports as $port): ?>
                            <tr class="<?= ($port['exposure'] ?? '') === 'public' ? 'bg-red-50' : '' ?>">
                                <td class="px-3 py-2 font-semibold"><?= htmlspecialchars($port['protocol']) ?></td>
                                <td class="px-3 py-2 font-mono"><?= htmlspecialchars($port['address']) ?></td>
                                <td class="px-3 py-2 font-mono"><?= (int) $port['port'] ?></td>
                                <td class="px-3 py-2"><?= msmSecurityExposureBadge($port['exposure'] ?? 'unknown') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Utilisateurs systeme</h2>
        <p class="text-sm text-gray-500 italic">A implementer</p>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Pare-feu / UFW</h2>
        <?php if (!$latestSecurityCheck): ?>
            <p class="text-sm italic text-gray-500">Aucun controle securite enregistre.</p>
        <?php else: ?>
            <?= msmSecurityDetailFirewallBadge($latestSecurityCheck['firewall_status'] ?? null) ?>
            <p class="mt-2 text-sm text-slate-600">
                Le controle pare-feu est stocke depuis le dernier passage de <code>scripts/check-security.php</code>.
            </p>
        <?php endif; ?>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Patch Management</h2>
        <p class="text-sm text-slate-600">
            Les mises a jour, reboots requis et upgrades OS sont suivis dans le module dedie.
        </p>
        <a href="<?= $baseUrl ?>pages/patch-management.php"
           class="mt-3 inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <i data-lucide="package-check" class="w-4 h-4"></i>
            Ouvrir Patch Management
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
