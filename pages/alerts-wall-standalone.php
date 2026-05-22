<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/alerts_helper.php';

$stmt = $pdo->query("SELECT * FROM servers ORDER BY name ASC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$alerts = msm_build_supervision_alerts($servers);
?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>MSM - Mur d'alertes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="MSM">
    <link rel="apple-touch-icon" href="/assets/logos/msm-192.png">
    <link rel="apple-touch-startup-image"
        media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)"
        href="/assets/logos/splash-1536x2048.png">
    <link rel="apple-touch-startup-image"
        media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: landscape)"
        href="/assets/logos/splash-2048x1536.png">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #020617;
            color: #e5e7eb;
            height: 100%;
        }
        body {
            overflow: hidden;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .scanlines {
            position: relative;
        }
        .scanlines::after {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: repeating-linear-gradient(
                to bottom,
                rgba(255,255,255,0.05) 0px,
                rgba(255,255,255,0.03) 1px,
                transparent 2px,
                transparent 4px
            );
            mix-blend-mode: overlay;
            opacity: 0.12;
        }
        .hud-card {
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .hud-card:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.35);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-black text-slate-100 flex flex-col">

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

<main class="scanlines flex-1 px-6 py-6 overflow-auto">
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
                <div class="hud-card border <?= $levelClasses ?> rounded-2xl p-4 shadow-lg relative overflow-hidden">
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

document.addEventListener('click', () => {
    const elem = document.documentElement;
    if (elem.requestFullscreen) {
        elem.requestFullscreen().catch(() => {});
    }
}, { once: true });
</script>

</body>
</html>
