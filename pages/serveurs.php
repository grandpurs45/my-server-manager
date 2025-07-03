<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\SettingsManager;
use MSM\SSHUtils;

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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_mode']) && $_POST['form_mode'] === 'edit') {
    $id = (int) $_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $port = (int) ($_POST['port'] ?? 22);
    $ssh_user = trim($_POST['ssh_user'] ?? '');
    $ssh_password = trim($_POST['ssh_password'] ?? '');

    if (!$name || !$hostname || !$ssh_user || !$ssh_password) {
        $_SESSION['error'] = "Tous les champs sont obligatoires.";
    } else {
        try {
            $os = SSHUtils::detectOS($hostname, $port, $ssh_user, $ssh_password);
            $ssh_status = ($os === null) ? 'fail' : 'success';
            if ($os === null) {
                $os = 'OS inconnu';
                $_SESSION['error'] = "Connexion SSH impossible. Données mises à jour sans détection d’OS.";
            } else {
                $_SESSION['success'] = "Serveur modifié avec succès.";
            }

            $stmt = $pdo->prepare("
                UPDATE servers SET 
                    name = :name,
                    hostname = :hostname,
                    port = :port,
                    ssh_user = :ssh_user,
                    ssh_password = :ssh_password,
                    os = :os,
                    ssh_status = :ssh_status
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':hostname' => $hostname,
                ':port' => $port,
                ':ssh_user' => $ssh_user,
                ':ssh_password' => $ssh_password,
                ':os' => $os,
                ':ssh_status' => $ssh_status,
                ':id' => $id
            ]);

            $_SESSION['success'] = "Serveur modifié avec succès.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la modification : " . $e->getMessage();
        }
    }

    header("Location: serveurs.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT * FROM servers ORDER BY id DESC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getOSLogo(string $osName): string {
    $osName = strtolower($osName);
    return match (true) {
        str_contains($osName, 'debian') => '/assets/logos/debian.png',
        str_contains($osName, 'ubuntu') => '/assets/logos/ubuntu.png',
        str_contains($osName, 'windows') => '/assets/logos/windows.png',
        default => '/assets/logos/unknown.png',
    };
}

include __DIR__ . '/../includes/server-modal.php';
?>

<main class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Gestion des serveurs</h1>
        <button onclick="resetForm(); toggleModal(true)" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
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
                        <td class="p-3"><?= htmlspecialchars($server['hostname'] ?? '') ?></td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <img src="<?= getOSLogo($server['os'] ?? '') ?>" alt="Logo OS" class="w-5 h-5">
                                <span><?= htmlspecialchars($server['os'] ?? '—') ?></span>
                            </div>
                        </td>
                        <td class="p-3">
                            <?php
                                 $status = $server['status'] ?? 'unknown';
                                    if ($status === 'up') {
                                        echo '<span class="inline-flex items-center gap-1 text-green-700 bg-green-100 px-2 py-1 rounded text-sm" title="Ping réussi">
                                                <i data-lucide="check-circle" class="w-4 h-4"></i> UP
                                            </span>';
                                    } elseif ($status === 'down') {
                                        echo '<span class="inline-flex items-center gap-1 text-red-700 bg-red-100 px-2 py-1 rounded text-sm" title="Hôte injoignable">
                                                <i data-lucide="x-circle" class="w-4 h-4"></i> DOWN
                                            </span>';
                                    } else {
                                        echo '<span class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 px-2 py-1 rounded text-sm" title="Statut inconnu">
                                                <i data-lucide="help-circle" class="w-4 h-4"></i> —
                                            </span>';
                                    }
                            ?>
                        </td>
                        <td class="p-3">
                            <?php
                                 $ssh = $server['ssh_status'] ?? 'fail';
                                if ($ssh === 'success') {
                                    echo '<span class="inline-flex items-center gap-1 text-green-700 bg-green-100 px-2 py-1 rounded text-sm" title="Connexion SSH réussie">
                                            <i data-lucide="terminal" class="w-4 h-4"></i> SSH OK
                                        </span>';
                                } else {
                                    echo '<span class="inline-flex items-center gap-1 text-red-700 bg-red-100 px-2 py-1 rounded text-sm" title="Échec de la connexion SSH">
                                            <i data-lucide="alert-octagon" class="w-4 h-4"></i> Échec SSH
                                        </span>';
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
                    <td colspan="7" class="text-center text-gray-500 py-4">Aucun serveur enregistré.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>
<?php if ($editMode): ?>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        toggleModal(true);
    });
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
