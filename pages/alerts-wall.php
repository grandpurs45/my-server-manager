<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/alerts_helper.php';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT * FROM servers ORDER BY name ASC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$alerts = msm_build_supervision_alerts($servers);
?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-950 to-black text-slate-100 flex flex-col rounded-lg overflow-hidden">
    <header class="px-6 py-4 border-b border-slate-700/60 bg-slate-900/80 backdrop-blur flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-emerald-400 animate-pulse shadow-lg shadow-emerald-500/40"></div>
            <div>
                <h1 class="text-xl font-semibold tracking-widest uppercase text-slate-100">
                    MSM - Mur d'alertes
                </h1>
                <p class="text-xs text-slate-400 tracking-widest uppercase">
                    Supervision temps reel
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
            <div class="h-full flex flex-col items-center justify-center gap-6">
                <div class="text-center">
                    <p class="text-2xl font-semibold text-emerald-300">
                        Aucune alerte active
                    </p>
                    <p class="text-sm text-slate-400 mt-2">
                        Tous les systemes sont operationnels.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($alerts as $alert):
                    $server = $alert['server'];
                    $level = $alert['level'];

                    $levelClasses = match ($level) {
                        'critical' => 'border-red-500/70 bg-red-900/30 shadow-red-500/40',
                        'warning' => 'border-amber-400/70 bg-amber-900/20 shadow-amber-400/40',
                        default => 'border-sky-500/70 bg-sky-900/20 shadow-sky-500/40',
                    };

                    $badgeClasses = match ($level) {
                        'critical' => 'bg-red-500/90 text-white',
                        'warning' => 'bg-amber-400 text-slate-900',
                        default => 'bg-sky-400 text-slate-900',
                    };
                ?>
                    <div class="border <?= $levelClasses ?> rounded-2xl p-4 shadow-lg relative overflow-hidden">
                        <div class="flex justify-between items-start relative z-10">
                            <div>
                                <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full <?= $badgeClasses ?> tracking-wide uppercase">
                                    <?= htmlspecialchars(strtoupper($alert['reason'])) ?>
                                </span>
                                <h2 class="text-lg font-semibold mt-2">
                                    <?= htmlspecialchars($server['name']) ?>
                                </h2>
                                <p class="text-xs text-slate-400 font-mono">
                                    <?= htmlspecialchars($server['hostname']) ?>
                                </p>
                            </div>
                            <div class="text-right text-xs text-slate-400">
                                <?php if (!empty($server['last_check'])): ?>
                                    <div>Dernier check :</div>
                                    <div class="font-mono"><?= htmlspecialchars($server['last_check']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-slate-200 relative z-10">
                            <?= htmlspecialchars($alert['message']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
function updateClock() {
    const el = document.getElementById('wall-clock');
    if (!el) return;
    const now = new Date();
    const pad = (n) => n.toString().padStart(2, '0');
    el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}
setInterval(updateClock, 1000);
updateClock();

setTimeout(() => {
    window.location.reload();
}, 30000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
