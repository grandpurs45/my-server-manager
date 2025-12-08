<?php
$baseUrl = '/'; // Toujours à la racine du projet, que tu sois en /index.php ou /pages/*
?>
<!-- includes/header.php -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>My Server Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <!-- PWA standard -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">

    <!-- Spécifique Apple / iPad -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="MSM">
    <link rel="apple-touch-icon" href="/assets/logos/msm-192.png">

</head>
<body class="bg-gray-100">
<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\SettingsManager;

if (defined('DEBUG') && DEBUG) {
     echo '<div class="bg-red-100 border border-red-400 text-red-800 text-sm font-bold px-4 py-2 mb-4 rounded shadow">
        ⚠️ MODE DEBUG ACTIVÉ : les erreurs PHP sont visibles à l’écran.
    </div>';
}
ob_implicit_flush(true);
ob_end_flush();
?>
<div class="flex h-screen">
    <!-- Sidebar -->
    <aside class="w-60 bg-blue-700 text-white p-6">
        <a href="<?= $baseUrl ?>index.php" class="flex flex-col items-center gap-2 mb-10">
                    <div class="bg-white rounded-xl p-2 shadow-md">
                        <img src="<?= $baseUrl ?>assets/logos/logo_msm.png" alt="Logo MSM" class="w-16 h-16">
                    </div>
                    <span class="text-2xl font-bold">MSM</span>
        </a>
        <nav class="space-y-4">
            <a href="<?= $baseUrl ?>index.php" class="flex items-center hover:text-gray-200">
                <!-- Dashboard -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11 0h7v7h-7v-7zm0-11h7v7h-7V3z"/>
                </svg>
                Dashboard
            </a>
            <a href="<?= $baseUrl ?>pages/serveurs.php" class="flex items-center hover:text-gray-200">
                <!-- Serveurs -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 4h16v4H4V4zm0 6h16v4H4v-4zm0 6h16v4H4v-4z"/>
                </svg>
                Serveurs
            </a>
            <a href="<?= $baseUrl ?>pages/supervision.php" class="flex items-center hover:text-gray-200">
                <!-- Supervision -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 20V10m0 0L8 14m4-4l4 4"/>
                </svg>
                Supervision
            </a>
            <a href="#" class="flex items-center hover:text-gray-200">
                <!-- Alertes -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Alertes
            </a>
            <!-- Sécurité (menu parent, non cliquable) -->
            <div class="text-white uppercase text-sm font-bold mt-6 mb-2">Sécurité</div>
            <a href="<?= $baseUrl ?>pages/securite-serveurs.php" class="flex items-center pl-4 hover:text-gray-200">
                <!-- Icône terminal/server -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
                Serveurs
            </a>
            <a href="<?= $baseUrl ?>pages/securite-web.php" class="flex items-center pl-4 hover:text-gray-200">
                <!-- Icône globe -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 3c4.97 0 9 4.03 9 9s-4.03 9-9 9-9-4.03-9-9 4.03-9 9-9zm0 0c2.5 1.5 4 5 4 9s-1.5 7.5-4 9"/>
                </svg>
                Web
            </a>
            <a href="<?= $baseUrl ?>pages/settings.php" class="flex items-center hover:text-gray-200">
                <!-- Paramètres -->
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                Paramètres
            </a>
        </nav>
    </aside>

    <!-- Main -->
    <main class="flex-1 p-6">
        <header class="text-xl font-semibold mb-6">My Server Manager</header>
