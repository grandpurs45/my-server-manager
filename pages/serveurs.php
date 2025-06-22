<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$editMode = false;
$editData = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($editData) {
        $editMode = true;
    } else {
        $_SESSION['error'] = "Serveur introuvable.";
        header("Location: serveurs.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM servers WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $_SESSION['success'] = $stmt->rowCount() > 0
            ? "Serveur supprimé avec succès."
            : "Le serveur n'a pas été trouvé.";

        header("Location: serveurs.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
}


require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT * FROM servers ORDER BY id DESC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Gestion des serveurs</h1>
        <button onclick="toggleModal(true)" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
            ➕ Ajouter un serveur
        </button>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="flex items-center bg-red-100 text-red-700 p-3 rounded mb-4">
            <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="flex items-center bg-green-100 text-green-700 p-3 rounded mb-4">
            <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

    <!-- Modale d'ajout/modification -->
    <!-- ... (modale intacte) ... -->
     <!-- Modale d'ajout de serveur -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-xl p-6 relative">
        <button onclick="toggleModal(false)" class="absolute top-2 right-2 text-gray-600 hover:text-black">
        <i data-lucide="x" class="w-5 h-5"></i>
        </button>
        
        <h2 class="text-xl font-bold mb-4">
            <?= $editMode ? '✏️ Modifier un serveur' : '➕ Ajouter un serveur' ?>
        </h2>

        <form action="<?= $editMode ? 'serveurs.php' : '/pages/add-server.php' ?>" method="post" class="space-y-4">
            <?php if ($editMode): ?>
                <input type="hidden" name="form_mode" value="edit">
                <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>
        <div>
            <label class="block font-medium mb-1" for="name">Nom du serveur</label>
            <input type="text" id="name" name="name"
                    value="<?= htmlspecialchars($editData['name'] ?? '') ?>"
                    required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
        </div>

        <div>
            <label class="block font-medium mb-1" for="hostname">Adresse IP / Nom d’hôte</label>
            <input type="text" id="hostname" name="hostname"
                value="<?= htmlspecialchars($editData['hostname'] ?? '') ?>"
                required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
        </div>

        <div>
            <label class="block font-medium mb-1" for="port">Port SSH</label>
            <input type="number" id="port" name="port"
                value="<?= htmlspecialchars($editData['port'] ?? 22) ?>"
                required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
        </div>

        <div>
            <label class="block font-medium mb-1" for="ssh_user">Utilisateur SSH</label>
            <input type="text" id="ssh_user" name="ssh_user"
                value="<?= htmlspecialchars($editData['ssh_user'] ?? '') ?>"
                required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
        </div>

        <div>
            <label class="block font-medium mb-1" for="ssh_password">Mot de passe SSH</label>
            <input type="password" id="ssh_password" name="ssh_password"
                value="<?= htmlspecialchars($editData['ssh_password'] ?? '') ?>"
                required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
        </div>
        <div class="text-right pt-2 flex justify-end gap-2">
            <button type="button" onclick="toggleModal(false)" class="bg-gray-300 hover:bg-gray-400 text-black font-semibold py-2 px-4 rounded">
                Annuler
            </button>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                <?= $editMode ? '💾 Enregistrer les modifications' : '💾 Ajouter le serveur' ?>
            </button>
        </div>
        </form>
    </div>
    </div>

    <table class="w-full table-auto border border-gray-200 rounded-lg overflow-hidden shadow">
        <thead class="bg-gray-100 text-left">
            <tr>
                <th class="p-3">Nom</th>
                <th class="p-3">Adresse IP</th>
                <th class="p-3">OS</th>
                <th class="p-3">Statut</th>
                <th class="p-3">SSH</th>
                <th class="p-3">Dernier check</th>
                <th class="px-4 py-2 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($servers)): ?>
                <?php foreach ($servers as $server): ?>
                    <tr class="border-t">
                        <td class="p-3"><?= htmlspecialchars($server['name'] ?? '') ?></td>
                        <td class="p-3"><?= htmlspecialchars($server['ip_address'] ?? '') ?></td>
                        <td class="p-3"><?= htmlspecialchars($server['os'] ?? '—') ?></td>
                        <td class="p-3" id="status-<?= $server['id'] ?>">
                            <div class="text-gray-400 italic flex items-center gap-1">
                                <i data-lucide="loader-2" class="animate-spin w-4 h-4"></i> En attente...
                            </div>
                        </td>
                        <td class="p-3">
                            <?php
                                $sshStatus = $server['ssh_status'] ?? 'fail';
                                if ($sshStatus === 'success') {
                                    echo '<span class="text-green-600 font-semibold bg-green-100 px-2 py-1 rounded text-sm">SSH OK</span>';
                                } else {
                                    echo '<span class="text-red-600 font-semibold bg-red-100 px-2 py-1 rounded text-sm">Échec SSH</span>';
                                }
                            ?>
                        </td>
                        <td class="p-3"><?= $server['last_check'] ? htmlspecialchars($server['last_check']) : 'Jamais' ?></td>
                        <td class="px-4 py-2">
                            <a href="serveurs.php?edit=<?= $server['id'] ?>" class="text-blue-600 hover:underline flex items-center gap-1 mb-2">
                                <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
                            </a>
                            <form method="POST" action="serveurs.php" onsubmit="return confirm('Confirmer la suppression ?');">
                                <input type="hidden" name="delete_id" value="<?= $server['id'] ?>">
                                <button type="submit" class="text-red-600 hover:underline flex items-center gap-1">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i> Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center text-gray-500 py-4">Aucun serveur enregistré.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function toggleModal(show) {
    document.getElementById('modal').classList.toggle('hidden', !show);

    if (!show && window.location.search.includes('edit=')) {
        // Nettoie l’URL pour virer ?edit=xxx
        const url = new URL(window.location.href);
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.pathname);
    }
}

<?php if ($editMode): ?>
window.addEventListener('DOMContentLoaded', () => toggleModal(true));
<?php endif; ?>

fetch("/api/ping-status.php")
  .then(res => res.json())
  .then(data => {
    for (const [id, status] of Object.entries(data)) {
      const cell = document.getElementById(`status-${id}`);
      if (cell) {
        cell.innerHTML = status === 'up'
          ? '<span class="text-green-600 font-semibold"><i class="fa-solid fa-circle-check"></i> UP</span>'
          : '<span class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> DOWN</span>';
      }
    }
  })
  .catch(error => console.error("Erreur fetch statut:", error));
</script>
