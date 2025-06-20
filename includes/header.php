<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>My Server Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-blue-700 text-white p-4 space-y-6">
            <div class="text-2xl font-bold">MSM</div>
            <nav class="space-y-2">
                <a href="index.php" class="block hover:bg-blue-600 p-2 rounded">Dashboard</a>
                <a href="#" class="block hover:bg-blue-600 p-2 rounded">Serveurs</a>
                <a href="#" class="block hover:bg-blue-600 p-2 rounded">Supervision</a>
                <a href="#" class="block hover:bg-blue-600 p-2 rounded">Sécurité</a>
                <a href="#" class="block hover:bg-blue-600 p-2 rounded">Alertes</a>
                <a href="#" class="block hover:bg-blue-600 p-2 rounded">Paramètres</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="bg-white shadow p-4">
                <h1 class="text-xl font-semibold">My Server Manager</h1>
            </header>

            <!-- Content -->
            <main class="flex-1 p-6">