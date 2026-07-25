<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/version.php';

use MSM\UpdateChecker;

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if (basename($scriptDirectory) === 'pages') {
    $scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptDirectory)), '/');
}

$baseUrl = ($scriptDirectory === '' || $scriptDirectory === '.') ? '/' : $scriptDirectory . '/';
$currentPage = basename($scriptName);
$navItemClass = static function (array $pages) use ($currentPage): string {
    $baseClass = 'flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors';

    return in_array($currentPage, $pages, true)
        ? $baseClass . ' bg-white text-blue-700 shadow-sm'
        : $baseClass . ' text-blue-50 hover:bg-blue-600 hover:text-white';
};
$updateStatus = null;
$browserTitle = trim((string) ($settings->get('msm', 'browser_title') ?? ''));
if ($browserTitle === '') {
    $browserTitle = 'My Server Manager';
}

if (!empty($currentUser)) {
    try {
        $updateStatus = (new UpdateChecker($settings))->status(getVersionFromPackageJson());
    } catch (Throwable) {
        $updateStatus = null;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($browserTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link rel="icon" type="image/png" href="<?= $baseUrl ?>assets/favicon.png">
</head>
<body class="bg-gray-100">
<?php
if (defined('DEBUG') && DEBUG) {
    echo '<div class="bg-red-100 border border-red-400 text-red-800 text-sm font-bold px-4 py-2 mb-4 rounded shadow">
        MODE DEBUG ACTIVE : les erreurs PHP sont visibles a l ecran.
    </div>';
}

ob_implicit_flush(true);
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
<div class="flex h-screen">
    <aside class="w-60 shrink-0 overflow-y-auto bg-blue-700 px-4 py-5 text-white">
        <a href="<?= $baseUrl ?>index.php" class="mb-7 flex flex-col items-center gap-2">
            <div class="bg-white rounded-xl p-2 shadow-md">
                <img src="<?= $baseUrl ?>assets/logos/logo_msm.png" alt="Logo MSM" class="w-16 h-16">
            </div>
            <span class="text-2xl font-bold">MSM</span>
        </a>

        <nav class="space-y-5" aria-label="Navigation principale">
            <?php if ($authManager->userCan('dashboard')): ?>
            <section class="space-y-1">
                <div class="px-3 text-[11px] font-bold uppercase text-blue-200">Vue d'ensemble</div>
                <a href="<?= $baseUrl ?>index.php" class="<?= $navItemClass(['index.php']) ?>">
                    <i data-lucide="layout-dashboard" class="h-5 w-5 shrink-0"></i>
                    Dashboard
                </a>
            </section>
            <?php endif; ?>

            <?php if ($authManager->userCan('serveurs') || $authManager->userCan('supervision')): ?>
            <section class="space-y-1">
                <div class="px-3 text-[11px] font-bold uppercase text-blue-200">Infrastructure</div>
                <?php if ($authManager->userCan('serveurs')): ?>
                <a href="<?= $baseUrl ?>pages/serveurs.php" class="<?= $navItemClass(['serveurs.php', 'add-server.php', 'details-cible.php']) ?>">
                    <i data-lucide="server" class="h-5 w-5 shrink-0"></i>
                    Serveurs
                </a>
                <?php endif; ?>
                <?php if ($authManager->userCan('supervision')): ?>
                <a href="<?= $baseUrl ?>pages/supervision.php" class="<?= $navItemClass(['supervision.php']) ?>">
                    <i data-lucide="activity" class="h-5 w-5 shrink-0"></i>
                    Supervision
                </a>
                <a href="<?= $baseUrl ?>pages/securite-web.php" class="<?= $navItemClass(['securite-web.php']) ?>">
                    <i data-lucide="globe-2" class="h-5 w-5 shrink-0"></i>
                    Supervision URLs
                </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($authManager->userCan('patch_management') || $authManager->userCan('settings')): ?>
            <section class="space-y-1">
                <div class="px-3 text-[11px] font-bold uppercase text-blue-200">Exploitation</div>
                <?php if ($authManager->userCan('patch_management')): ?>
                <a href="<?= $baseUrl ?>pages/patch-management.php" class="<?= $navItemClass(['patch-management.php']) ?>">
                    <i data-lucide="package-check" class="h-5 w-5 shrink-0"></i>
                    Patch management
                </a>
                <?php endif; ?>
                <?php if ($authManager->userCan('settings')): ?>
                <a href="<?= $baseUrl ?>pages/os-lifecycle.php" class="<?= $navItemClass(['os-lifecycle.php']) ?>">
                    <i data-lucide="calendar-clock" class="h-5 w-5 shrink-0"></i>
                    Cycle de vie OS
                </a>
                <a href="<?= $baseUrl ?>pages/collectors.php" class="<?= $navItemClass(['collectors.php']) ?>">
                    <i data-lucide="workflow" class="h-5 w-5 shrink-0"></i>
                    Collecteurs
                </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($authManager->userCan('securite') || $authManager->userCan('alertes')): ?>
            <section class="space-y-1">
                <div class="px-3 text-[11px] font-bold uppercase text-blue-200">Securite et alertes</div>
                <?php if ($authManager->userCan('securite')): ?>
                <a href="<?= $baseUrl ?>pages/securite-serveurs.php" class="<?= $navItemClass(['securite-serveurs.php', 'details-securite.php']) ?>">
                    <i data-lucide="shield-check" class="h-5 w-5 shrink-0"></i>
                    Securite serveurs
                </a>
                <?php endif; ?>
                <?php if ($authManager->userCan('alertes')): ?>
                <a href="<?= $baseUrl ?>pages/alerts.php" class="<?= $navItemClass(['alerts.php']) ?>">
                    <i data-lucide="bell" class="h-5 w-5 shrink-0"></i>
                    Alertes
                </a>
                <a href="<?= $baseUrl ?>pages/alert-rules.php" class="<?= $navItemClass(['alert-rules.php']) ?>">
                    <i data-lucide="sliders-horizontal" class="h-5 w-5 shrink-0"></i>
                    Regles d'alertes
                </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($authManager->userCan('settings') || $authManager->userCan('diagnostic')): ?>
            <section class="space-y-1">
                <div class="px-3 text-[11px] font-bold uppercase text-blue-200">Administration</div>
                <?php if ($authManager->userCan('settings')): ?>
                <a href="<?= $baseUrl ?>pages/settings.php" class="<?= $navItemClass(['settings.php']) ?>">
                    <i data-lucide="settings" class="h-5 w-5 shrink-0"></i>
                    Parametres
                </a>
                <a href="<?= $baseUrl ?>pages/users.php" class="<?= $navItemClass(['users.php']) ?>">
                    <i data-lucide="users" class="h-5 w-5 shrink-0"></i>
                    Utilisateurs
                </a>
                <?php endif; ?>
                <?php if ($authManager->userCan('diagnostic')): ?>
                <a href="<?= $baseUrl ?>pages/diagnostic.php" class="<?= $navItemClass(['diagnostic.php']) ?>">
                    <i data-lucide="stethoscope" class="h-5 w-5 shrink-0"></i>
                    Diagnostic
                </a>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="flex-1 p-6 overflow-y-auto">
        <header class="flex items-center justify-between text-xl font-semibold mb-6">
            <span>My Server Manager</span>
            <?php if (!empty($currentUser)): ?>
                <div class="flex items-center gap-3 text-sm font-normal text-gray-600">
                    <span><?= htmlspecialchars($currentUser['display_name'] ?: $currentUser['username']) ?></span>
                    <a href="<?= $baseUrl ?>logout.php" class="inline-flex items-center gap-1 rounded border border-gray-300 px-3 py-1.5 hover:bg-gray-50">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        Deconnexion
                    </a>
                </div>
            <?php endif; ?>
        </header>
        <?php if (!empty($updateStatus['update_available'])): ?>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-blue-900 shadow-sm">
                <div class="text-sm">
                    My Server Manager v<?= htmlspecialchars($updateStatus['latest_version']) ?> est disponible.
                    <?php if (!empty($updateStatus['release_url'])): ?>
                        <a href="<?= htmlspecialchars($updateStatus['release_url']) ?>" target="_blank" rel="noopener" class="font-semibold underline">
                            Voir les informations de release
                        </a>.
                    <?php endif; ?>
                </div>
                <a href="https://github.com/grandpurs45/my-server-manager/blob/main/docs/UPDATE.md" target="_blank" rel="noopener" class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Guide de mise a jour
                </a>
            </div>
        <?php endif; ?>
        <?php if (!empty($currentUser) && (int) ($currentUser['password_must_change'] ?? 0) === 1): ?>
            <div class="mb-6 flex items-start gap-4 rounded-lg border-2 border-amber-400 bg-amber-100 px-5 py-4 text-amber-950 shadow-sm">
                <div class="mt-0.5 rounded-full bg-amber-500 p-2 text-white">
                    <i data-lucide="triangle-alert" class="h-5 w-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-base font-bold">Action requise : mot de passe initial a remplacer</div>
                    <p class="mt-1 text-sm">
                        Ce compte utilise encore le mot de passe initial ou un changement obligatoire est demande.
                        Modifiez-le depuis la gestion des utilisateurs.
                    </p>
                </div>
                <?php if ($authManager->userCan('settings')): ?>
                    <a href="<?= $baseUrl ?>pages/users.php" class="shrink-0 rounded bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">
                        Changer maintenant
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
