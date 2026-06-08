<?php
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
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/bootstrap.php';

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
            <a href="<?= $baseUrl ?>index.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="layout-dashboard" class="w-5 h-5 mr-2"></i>
                Dashboard
            </a>
            <a href="<?= $baseUrl ?>pages/serveurs.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="server" class="w-5 h-5 mr-2"></i>
                Serveurs
            </a>
            <a href="<?= $baseUrl ?>pages/supervision.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="activity" class="w-5 h-5 mr-2"></i>
                Supervision
            </a>
            <a href="<?= $baseUrl ?>pages/alerts.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="bell" class="w-5 h-5 mr-2"></i>
                Alertes
            </a>
            <a href="<?= $baseUrl ?>pages/alert-rules.php" class="flex items-center pl-4 text-sm hover:text-gray-200">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 mr-2"></i>
                Regles alertes
            </a>

            <div class="text-white uppercase text-sm font-bold mt-6 mb-2">Exploitation</div>
            <a href="<?= $baseUrl ?>pages/patch-management.php" class="flex items-center pl-4 hover:text-gray-200">
                <i data-lucide="package-check" class="w-5 h-5 mr-2"></i>
                Patch management
            </a>

            <div class="text-white uppercase text-sm font-bold mt-6 mb-2">Securite</div>
            <a href="<?= $baseUrl ?>pages/securite-serveurs.php" class="flex items-center pl-4 hover:text-gray-200">
                <i data-lucide="terminal-square" class="w-5 h-5 mr-2"></i>
                Serveurs
            </a>
            <a href="<?= $baseUrl ?>pages/securite-web.php" class="flex items-center pl-4 hover:text-gray-200">
                <i data-lucide="globe" class="w-5 h-5 mr-2"></i>
                Web
            </a>

            <a href="<?= $baseUrl ?>pages/settings.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="settings" class="w-5 h-5 mr-2"></i>
                Parametres
            </a>
            <a href="<?= $baseUrl ?>pages/diagnostic.php" class="flex items-center hover:text-gray-200">
                <i data-lucide="stethoscope" class="w-5 h-5 mr-2"></i>
                Diagnostic
            </a>
        </nav>
    </aside>

    <main class="flex-1 p-6 overflow-y-auto">
        <header class="text-xl font-semibold mb-6">My Server Manager</header>
