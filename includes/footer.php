<?php
require_once __DIR__ . '/version.php';
$version = getVersionFromPackageJson();
?>

<footer class="text-center text-sm text-gray-400 mt-8">
    <p>My Server Manager – Version <?= htmlspecialchars($version) ?>. Tous droits réservés.</p>
</footer>

<script>
    lucide.createIcons();
    function hideLoading() {
    const spinner = document.getElementById('loading');
    if (spinner) {
      spinner.remove(); // supprime complètement le div du DOM
    }
  }

  // Exécute dès que le DOM est prêt
  document.addEventListener('DOMContentLoaded', hideLoading);

  // Sécurité : supprime au bout de 5 secondes max si jamais tout bloque
  setTimeout(hideLoading, 5000);
</script>