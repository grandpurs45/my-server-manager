<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/functions.php';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if (basename($scriptDirectory) === 'pages') {
    $scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptDirectory)), '/');
}

$baseUrl = ($scriptDirectory === '' || $scriptDirectory === '.') ? '/' : $scriptDirectory . '/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>My Server Manager</title>
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
        <?php if (!empty($currentUser) && (int) ($currentUser['password_must_change'] ?? 0) === 1): ?>
            <div class="mb-5 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Le mot de passe de ce compte est encore le mot de passe initial ou doit etre remplace. Allez dans Parametres > Utilisateurs pour le changer.
            </div>
        <?php endif; ?>
