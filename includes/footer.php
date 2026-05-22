<?php
require_once __DIR__ . '/version.php';
$version = getVersionFromPackageJson();
?>

        <footer class="text-center text-sm text-gray-400 mt-8">
            <p>My Server Manager - Version <?= htmlspecialchars($version) ?>. Tous droits reserves.</p>
        </footer>
    </main>
</div>

<script>
    lucide.createIcons();

    function toggleModal(show) {
        const modal = document.getElementById('modal');
        if (!modal) return;

        modal.classList.toggle('hidden', !show);

        if (!show && window.location.search.includes('edit=')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url.pathname);
        }
    }

    function resetForm() {
        const fields = ['name', 'hostname', 'ssh_port', 'ssh_user', 'ssh_password', 'id', 'form_mode'];
        fields.forEach(name => {
            const el = document.getElementsByName(name)[0];
            if (el && ['hidden', 'text', 'password', 'number'].includes(el.type)) {
                el.value = '';
            }
        });

        const portInput = document.getElementsByName('ssh_port')[0];
        if (portInput) portInput.value = 22;
    }
</script>
</body>
</html>
