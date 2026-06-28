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
    <aside class="w-60 bg-blue-700 text-white p-6">
        <a href="<?= $baseUrl ?>index.php" class="flex flex-col items-center gap-2 mb-10">
            <div class="bg-white rounded-xl p-2 shadow-md">
                <img src="<?= $baseUrl ?>assets/logos/logo_msm.png" alt="Logo MSM" class="w-16 h-16">
            </div>
            <span class="text-2xl font-bold">MSM</span>
        </a>

        <nav class="space-y-4">
            <?php if ($authManager->userCan('dashboard')): ?>
            <a href="<?= $baseUrl ?>index.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="layout-dashboard" class="w-5 h-5 mr-2"></i>
                Dashboard
            </a>
            <?php endif; ?>
            <?php if ($authManager->userCan('serveurs')): ?>
            <a href="<?= $baseUrl ?>pages/serveurs.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="server" class="w-5 h-5 mr-2"></i>
                Serveurs
            </a>
            <?php endif; ?>
            <?php if ($authManager->userCan('supervision')): ?>
            <a href="<?= $baseUrl ?>pages/supervision.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="activity" class="w-5 h-5 mr-2"></i>
                Supervision
            </a>
            <?php endif; ?>
            <?php if ($authManager->userCan('alertes')): ?>
            <a href="<?= $baseUrl ?>pages/alerts.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="bell" class="w-5 h-5 mr-2"></i>
                Alertes
            </a>
            <a href="<?= $baseUrl ?>pages/alert-rules.php" class="flex items-center pl-4 text-sm hover:text-gray-200">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 mr-2"></i>
                Regles alertes
            </a>
            <?php endif; ?>

            <?php if ($authManager->userCan('patch_management')): ?>
            <div class="text-white uppercase text-sm font-bold mt-6 mb-2">Exploitation</div>
            <a href="<?= $baseUrl ?>pages/patch-management.php" class="flex items-center pl-4 hover:text-gray-200">
                <i data-lucide="package-check" class="w-5 h-5 mr-2"></i>
                Patch management
            </a>
            <?php endif; ?>

            <?php if ($authManager->userCan('securite')): ?>
            <div class="text-white uppercase text-sm font-bold mt-6 mb-2">Securite</div>
            <a href="<?= $baseUrl ?>pages/securite-serveurs.php" class="flex items-center pl-4 hover:text-gray-200">
                <i data-lucide="terminal-square" class="w-5 h-5 mr-2"></i>
                Serveurs
            </a>
            <a href="<?= $baseUrl ?>pages/securite-web.php" class="flex items-center pl-4 hover:text-gray-200">
                <i data-lucide="globe" class="w-5 h-5 mr-2"></i>
                Web
            </a>
            <?php endif; ?>

            <?php if ($authManager->userCan('settings')): ?>
            <a href="<?= $baseUrl ?>pages/settings.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="settings" class="w-5 h-5 mr-2"></i>
                Parametres
            </a>
            <a href="<?= $baseUrl ?>pages/collectors.php" class="flex items-center pl-4 text-sm hover:text-gray-200">
                <i data-lucide="workflow" class="w-4 h-4 mr-2"></i>
                Collecteurs
            </a>
            <a href="<?= $baseUrl ?>pages/users.php" class="flex items-center pl-4 text-sm hover:text-gray-200">
                <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                Utilisateurs
            </a>
            <?php endif; ?>
            <?php if ($authManager->userCan('diagnostic')): ?>
            <a href="<?= $baseUrl ?>pages/diagnostic.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="stethoscope" class="w-5 h-5 mr-2"></i>
                Diagnostic
            </a>
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
