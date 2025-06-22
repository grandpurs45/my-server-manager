<?php include 'includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-2">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
      <path d="M5 12h14M12 5l7 7-7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Bienvenue sur My Server Manager
  </h1>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-2xl shadow">
      <div class="flex items-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M3 10h18M3 14h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h2 class="text-lg font-semibold ml-2">Gestion des serveurs</h2>
      </div>
      <p class="text-gray-600">Ajoutez, modifiez ou supprimez vos serveurs Linux.</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
      <div class="flex items-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <circle cx="12" cy="12" r="10" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="12" y1="8" x2="12" y2="12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="12" y1="16" x2="12" y2="16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h2 class="text-lg font-semibold ml-2">Supervision</h2>
      </div>
      <p class="text-gray-600">Surveillez l’espace disque, la mémoire, les services…</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
      <div class="flex items-center mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M12 9v2m0 4h.01M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h2 class="text-lg font-semibold ml-2">Sécurité</h2>
      </div>
      <p class="text-gray-600">Analyse des headers HTTP, politiques de sécurité, etc.</p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>