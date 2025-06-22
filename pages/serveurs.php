<?php
session_start(); // pour stocker le message

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

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Serveur supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Le serveur n'a pas été trouvé.";
        }

        header("Location: serveurs.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $os = getRemoteOS($ip) ?: 'unknown';
    $mode = $_POST['form_mode'] ?? 'add';

    if (empty($name) || empty($ip) || empty($os)) {
        $_SESSION['error'] = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $_SESSION['error'] = "Adresse IP invalide.";
    } else {
        if ($mode === 'edit') {
            $id = (int) $_POST['id'];
            $stmt = $pdo->prepare("UPDATE servers SET name = :name, ip_address = :ip, os = :os WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':ip' => $ip,
                ':os' => $os,
                ':id' => $id
            ]);
            $_SESSION['success'] = "Serveur modifié avec succès.";
            header("Location: serveurs.php");
            exit;
        } else {
            // Vérifier doublon IP
            $check = $pdo->prepare("SELECT COUNT(*) FROM servers WHERE ip_address = :ip");
            $check->execute([':ip' => $ip]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['error'] = "Cette adresse IP est déjà enregistrée.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO servers (name, ip_address, os) VALUES (:name, :ip, :os)");
                    $stmt->execute([
                        ':name' => $name,
                        ':ip' => $ip,
                        ':os' => $os
                    ]);
                    $_SESSION['success'] = "Serveur ajouté avec succès.";
                    header("Location: serveurs.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de l'ajout du serveur : " . $e->getMessage();
                }
            }
        }
    }
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['error'])): ?>
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
<?php unset($_SESSION['success']); endif;

// Requête pour récupérer les serveurs
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

<!-- Modale -->
<div id="modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="relative bg-white rounded-xl shadow-lg w-full max-w-md p-6">
    <button onclick="window.location.href='serveurs.php'"
            class="absolute top-4 right-6 text-gray-400 hover:text-gray-600 text-2xl font-bold focus:outline-none"
            aria-label="Fermer">
        &times;
    </button>
    <h2 class="text-xl font-semibold mb-4">
        <?= $editMode ? 'Modifier un serveur' : 'Ajouter un serveur' ?>
    </h2>
        <form method="POST" action="serveurs.php" class="space-y-4 mb-8">
            <input type="hidden" name="form_mode" value="<?= $editMode ? 'edit' : 'add' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <?php endif; ?>

            <div>
                <label for="name" class="block mb-1 font-semibold">Nom du serveur</label>
                <input type="text" id="name" name="name" required
                    value="<?= $editMode ? htmlspecialchars($editData['name']) : '' ?>"
                    class="w-full border border-gray-300 p-2 rounded">
            </div>

            <div>
                <label for="ip" class="block mb-1 font-semibold">Adresse IP</label>
                <input type="text" id="ip" name="ip" required
                    value="<?= $editMode ? htmlspecialchars($editData['ip_address']) : '' ?>"
                    class="w-full border border-gray-300 p-2 rounded">
            </div>

            <div>
                <label for="os" class="block mb-1 font-semibold">Système d’exploitation</label>
                <input type="text" id="os" name="os"
                        value="<?= $editMode ? htmlspecialchars($editData['os']) : 'Auto détecté à l’ajout' ?>"
                        class="w-full border border-gray-300 p-2 rounded bg-gray-100 text-gray-600"
                        readonly>
            </div>

            <button type="submit"
                    class="bg-<?= $editMode ? 'blue' : 'green' ?>-600 text-white px-4 py-2 rounded hover:bg-<?= $editMode ? 'blue' : 'green' ?>-700">
                <?= $editMode ? 'Modifier le serveur' : 'Ajouter le serveur' ?>
            </button>
            <button type="button" onclick="window.location.href='serveurs.php'"
                    class="ml-2 bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">
                Annuler
            </button>
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
                <th class="p-3">Dernier check</th>
                <th class="px-4 py-2 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($servers as $server):
            $status = isHostUp($server['ip_address']) ? 'up' : 'down';
            ?>
            <tr class="border-t">
                <td class="p-3"><?= htmlspecialchars($server['name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($server['ip_address']) ?></td>
                <td class="p-3"><?= htmlspecialchars($server['os']) ?></td>
                <td class="p-3">
                    <?php if ($status === 'up'): ?>
                        <span class="text-green-600 font-semibold"><i class="fa-solid fa-circle-check"></i> UP</span>
                    <?php else: ?>
                        <span class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> DOWN</span>
                    <?php endif; ?>
                </td>
                <td class="p-3"><?= htmlspecialchars($server['last_check']) ?></td>
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
        </tbody>
    </table>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
  function toggleModal(show) {
    document.getElementById('modal').classList.toggle('hidden', !show);
  }

  // 👉 Ouvre la modale automatiquement si on est en mode édition
  <?php if ($editMode): ?>
    window.addEventListener('DOMContentLoaded', () => toggleModal(true));
  <?php endif; ?>
</script>