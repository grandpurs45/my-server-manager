<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

// On récupère tous les serveurs
$stmt = $pdo->query("SELECT * FROM servers ORDER BY name ASC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$alerts = [];

// Construction des "alertes" à partir de l'état des serveurs
foreach ($servers as $server) {
    $serverAlerts = [];

    // 1) Serveur DOWN (ping KO)
    if ($server['status'] !== 'up') {
        $serverAlerts[] = [
            'level'   => 'critical',
            'reason'  => 'Ping KO',
            'message' => 'Serveur injoignable'
        ];
    }

    // 2) SSH KO alors qu’il est activé
    if (
        isset($server['ssh_enabled'], $server['ssh_ok'])
        && (int)$server['ssh_enabled'] === 1
        && (int)$server['ssh_ok'] === 0
    ) {
        $serverAlerts[] = [
            'level'   => 'warning',
            'reason'  => 'SSH KO',
            'message' => 'Connexion SSH impossible'
        ];
    }

    // 3) Latence élevée (par ex. > 100 ms) pour un serveur UP
    if (
        $server['status'] === 'up'
        && isset($server['latency'])
        && $server['latency'] !== null
        && (int)$server['latency'] > 100
    ) {
        $serverAlerts[] = [
            'level'   => 'warning',
            'reason'  => 'Latence élevée',
            'message' => 'Latence ' . (int)$server['latency'] . ' ms'
        ];
    }

    foreach ($serverAlerts as $alert) {
        $alerts[] = [
            'server' => $server,
            'level'  => $alert['level'],
            'reason' => $alert['reason'],
            'message'=> $alert['message'],
        ];
    }
}
?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-950 to-black text-slate-100 flex flex-col">
    <!-- Bandeau style "pont du vaisseau" -->
    <header class="px-6 py-4 border-b border-slate-700/60 bg-slate-900/80 backdrop-blur flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-emerald-400 animate-pulse shadow-lg shadow-emerald-500/40"></div>
            <div>
                <h1 class="text-xl font-semibold tracking-widest uppercase text-slate-100">
                    MSM • Mur d'alertes
                </h1>
                <p class="text-xs text-slate-400 tracking-widest uppercase">
                    Supervision temps réel
                </p>
            </div>
        </div>
        <div class="text-right text-xs text-slate-400">
            <div id="wall-clock" class="font-mono"></div>
            <div class="mt-1">
                Auto-refresh toutes les <span class="font-semibold text-slate-200">30s</span>
            </div>
        </div>
    </header>

    <main class="flex-1 px-6 py-6">
        <?php if (empty($alerts)): ?>
            <!-- Mode "tout va bien" -->
            <div class="h-full flex flex-col items-center justify-center gap-6">
                <div class="text-6xl">✅</div>
                <div class="text-center">
                    <p class="text-2xl font-semibold text-emerald-300">
                        Aucune alerte active
                    </p>
                    <p class="text-sm text-slate-400 mt-2">
                        Tous les systèmes sont opérationnels. <span class="italic">Le vaisseau est en régime nominal.</span>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- Grille d’alertes -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($alerts as $alert): 
                    $server = $alert['server'];
                    $level  = $alert['level'];

                    $levelClasses = match ($level) {
                        'critical' => 'border-red-500/70 bg-red-900/30 shadow-red-500/40',
                        'warning'  => 'border-amber-400/70 bg-amber-900/20 shadow-amber-400/40',
                        default    => 'border-sky-500/70 bg-sky-900/20 shadow-sky-500/40',
                    };

                    $badgeClasses = match ($level) {
                        'critical' => 'bg-red-500/90 text-white',
                        'warning'  => 'bg-amber-400 text-slate-900',
                        default    => 'bg-sky-400 text-slate-900',
                    };
                ?>
                    <div class="border <?= $levelClasses ?> rounded-2xl p-4 shadow-lg relative overflow-hidden">
                        <!-- décor "sci-fi" -->
                        <div class="pointer-events-none absolute inset-0 opacity-20">
                            <div class="absolute -right-10 -top-10 w-32 h-32 border border-slate-500/40 rounded-full"></div>
                            <div class="absolute right-6 bottom-4 w-24 h-24 border border-slate-500/30 rounded-xl rotate-6"></div>
                        </div>

                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full <?= $badgeClasses ?> tracking-wide uppercase">
                                        <?= htmlspecialchars(strtoupper($alert['reason'])) ?>
                                    </span>
                                </div>
                                <h2 class="text-lg font-semibold">
                                    <?= htmlspecialchars($server['name']) ?>
                                </h2>
                                <p class="text-xs text-slate-400 font-mono">
                                    <?= htmlspecialchars($server['hostname']) ?>
                                </p>
                            </div>
                            <div class="text-right text-xs text-slate-400">
                                <?php if (!empty($server['last_check'])): ?>
                                    <div>Dernier check :</div>
                                    <div class="font-mono">
                                        <?= htmlspecialchars($server['last_check']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($server['latency']) && $server['status'] === 'up'): ?>
                                    <div class="mt-1">
                                        Latence :
                                        <span class="font-mono">
                                            <?= (int)$server['latency'] ?> ms
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-slate-200 relative z-10">
                            <?= htmlspecialchars($alert['message']) ?>
                        </p>

                        <div class="mt-4 flex items-center justify-between text-[11px] text-slate-400 relative z-10">
                            <span class="font-mono">
                                ID #<?= (int)$server['id'] ?>
                            </span>
                            <span class="tracking-widest uppercase">
                                Supervision
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
// Horloge en haut à droite
function updateClock() {
    const el = document.getElementById('wall-clock');
    if (!el) return;
    const now = new Date();
    const pad = (n) => n.toString().padStart(2, '0');
    el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}
setInterval(updateClock, 1000);
updateClock();

// Auto-refresh toutes les 30s
setTimeout(() => {
    window.location.reload();
}, 30000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
