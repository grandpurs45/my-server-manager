<?php
include 'includes/header.php';
require_once __DIR__ . '/includes/bootstrap.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-2">
        <i data-lucide="arrow-right" class="h-7 w-7 text-blue-500"></i>
        Bienvenue sur My Server Manager
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow">
            <div class="flex items-center mb-4">
                <i data-lucide="server" class="h-6 w-6 text-green-500"></i>
                <h2 class="text-lg font-semibold ml-2">Gestion des serveurs</h2>
            </div>
            <p class="text-gray-600">Ajoutez, modifiez ou supprimez vos serveurs Linux et Windows.</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow">
            <div class="flex items-center mb-4">
                <i data-lucide="activity" class="h-6 w-6 text-yellow-500"></i>
                <h2 class="text-lg font-semibold ml-2">Supervision</h2>
            </div>
            <p class="text-gray-600">Surveillez le statut, la latence, le disque et les derniers checks.</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow">
            <div class="flex items-center mb-4">
                <i data-lucide="shield-alert" class="h-6 w-6 text-red-500"></i>
                <h2 class="text-lg font-semibold ml-2">Securite</h2>
            </div>
            <p class="text-gray-600">Analyse des ports, du pare-feu, des mises a jour et des certificats.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
