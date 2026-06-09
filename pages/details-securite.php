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
    echo '<div class="rounded border border-red-200 bg-red-50 p-4 font-semibold text-red-700">Serveur introuvable.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (empty($serveur['security_enabled'])) {
    ?>
    <div class="p-6">
        <a href="<?= $baseUrl ?>pages/securite-serveurs.php"
           class="mb-6 inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Retour securite
        </a>

        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">Analyse securite desactivee</h1>
            <p class="mt-2 text-sm text-slate-600">
                <?= htmlspecialchars($serveur['name']) ?> n'est pas incluse dans le module securite.
            </p>
            <a href="<?= $baseUrl ?>pages/serveurs.php?edit=<?= (int) $serveur['id'] ?>"
               class="mt-4 inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="settings" class="h-4 w-4"></i>
                Modifier la cible
            </a>
        </div>
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
        'actif' => msmStatusBadge('ok', 'OK'),
        'inactif' => msmStatusBadge('critical', 'Critical'),
        'not_installed' => msmStatusBadge('warning', 'Warning'),
        default => msmStatusBadge('unknown', 'Unknown'),
    };
}

function msmSecurityExposureBadge(string $exposure): string
{
    return match ($exposure) {
        'public' => msmStatusBadge('critical', 'Public'),
        'local' => msmStatusBadge('neutral', 'Local'),
        'bound' => msmStatusBadge('info', 'Adresse liee'),
        default => msmStatusBadge('unknown', 'Unknown'),
    };
}
?>

<div class="p-6">
    <div class="mb-6">
        <a href="<?= $baseUrl ?>pages/securite-serveurs.php"
           class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Retour securite
        </a>
        <h1 class="text-2xl font-bold text-slate-900">Securite - <?= htmlspecialchars($serveur['name']) ?></h1>
        <p class="mt-1 text-sm text-slate-600">
            <?= htmlspecialchars($serveur['hostname']) ?> · <?= htmlspecialchars($serveur['os'] ?: 'OS inconnu') ?>
        </p>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Statut securite</div>
            <div class="mt-3"><?= msmSecurityDetailStatusBadge($latestSecurityCheck['status'] ?? null) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Ports ouverts</div>
            <div class="mt-2 text-2xl font-bold text-slate-900"><?= (int) ($latestSecurityCheck['open_ports_count'] ?? 0) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Ports publics</div>
            <div class="mt-2 text-2xl font-bold text-red-700"><?= (int) ($latestSecurityCheck['exposed_ports_count'] ?? 0) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Pare-feu</div>
            <div class="mt-3"><?= msmSecurityDetailFirewallBadge($latestSecurityCheck['firewall_status'] ?? null) ?></div>
        </div>
    </div>

    <?php if (!$latestSecurityCheck): ?>
        <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
            Aucun controle securite enregistre pour cette cible.
        </div>
    <?php else: ?>
        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Dernier controle</h2>
            <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div>
                    <dt class="text-slate-500">Date</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($latestSecurityCheck['checked_at'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Duree</dt>
                    <dd class="font-semibold text-slate-900"><?= isset($latestSecurityCheck['duration_ms']) ? (int) $latestSecurityCheck['duration_ms'] . ' ms' : '-' ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Source</dt>
                    <dd class="font-mono text-slate-900">scripts/check-security.php</dd>
                </div>
            </dl>

            <?php if (!empty($latestSecurityCheck['error_message'])): ?>
                <p class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    <?= htmlspecialchars($latestSecurityCheck['error_message']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <section class="xl:col-span-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Ports ouverts</h2>
                <?php if (!$ports): ?>
                    <p class="text-sm italic text-slate-500">Aucun port detecte.</p>
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
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Pare-feu</h2>
                <div><?= msmSecurityDetailFirewallBadge($latestSecurityCheck['firewall_status'] ?? null) ?></div>
                <p class="mt-3 text-sm text-slate-600">
                    Le statut pare-feu vient du dernier check securite stocke. MSM ne lance aucune commande depuis cette page.
                </p>
                <?php if (($latestSecurityCheck['firewall_status'] ?? null) === 'not_installed'): ?>
                    <p class="mt-3 rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                        UFW n'est pas installe ou pas detecte. Ce n'est pas toujours une erreur si un autre pare-feu est utilise.
                    </p>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
