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

    function toggleModal(show) {
        console.log("toggleModal called with show =", show); // DEBUG
        document.getElementById('modal').classList.toggle('hidden', !show);

        if (!show && window.location.search.includes('edit=')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url.pathname);
        }
    }

    function resetForm() {
            const fields = ['name', 'hostname', 'port', 'ssh_user', 'ssh_password', 'id', 'form_mode'];
            fields.forEach(id => {
                const el = document.getElementsByName(id)[0];
                if (el) {
                    if (el.type === 'hidden' || el.type === 'text' || el.type === 'password' || el.type === 'number') {
                        el.value = '';
                    }
                }
            });

            // Remet les valeurs par défaut
            const portInput = document.getElementsByName('port')[0];
            if (portInput) portInput.value = 22;
    }
</script>