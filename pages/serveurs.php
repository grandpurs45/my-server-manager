<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/csrf.php';

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
        $_SESSION['error'] = 'Serveur introuvable.';
        header('Location: serveurs.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    msmRequireValidCsrf('serveurs.php');

    $id = (int) $_POST['delete_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM server_metrics WHERE server_id = :id");
        $stmt->execute([':id' => $id]);

        $stmt = $pdo->prepare("DELETE FROM servers WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $_SESSION['success'] = $stmt->rowCount() > 0
            ? 'Serveur et metriques supprimes avec succes.'
            : "Le serveur n'a pas ete trouve.";

        header('Location: serveurs.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erreur lors de la suppression : ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_mode'] ?? '') === 'edit') {
    msmRequireValidCsrf('serveurs.php');

    $id = (int) $_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $sshUser = trim($_POST['ssh_user'] ?? '');
    $sshPasswordInput = $_POST['ssh_password'] ?? '';
    $sshPort = isset($_POST['ssh_port']) && is_numeric($_POST['ssh_port']) ? (int) $_POST['ssh_port'] : 22;
    $sshEnabled = isset($_POST['ssh_enabled']) ? 1 : 0;

    if (!$name || !$hostname || ($sshEnabled && !$sshUser)) {
        $_SESSION['error'] = 'Les champs nom, hote et utilisateur SSH sont obligatoires si SSH est active.';
    } else {
        try {
            if ($sshEnabled) {
                if ($sshPasswordInput !== '') {
                    $sshPassword = encrypt($sshPasswordInput);
                    $os = SSHUtils::detectOS($hostname, $sshPort, $sshUser, $sshPasswordInput);
                } else {
                    $stmt = $pdo->prepare("SELECT ssh_password FROM servers WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sshPassword = $existing['ssh_password'] ?? '';
                    $os = SSHUtils::detectOS($hostname, $sshPort, $sshUser, decrypt($sshPassword));
                }

                $sshStatus = ($os === null) ? 'fail' : 'success';

                if ($os === null) {
                    $os = 'OS inconnu';
                    $_SESSION['error'] = "Connexion SSH impossible. Donnees mises a jour sans detection d'OS.";
                } else {
                    $_SESSION['success'] = 'Serveur modifie avec succes.';
                }
            } else {
                $stmt = $pdo->prepare("SELECT os, ssh_password FROM servers WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                $os = $existing['os'] ?? 'OS inconnu';
                $sshPassword = $existing['ssh_password'] ?? '';
                $sshStatus = 'fail';
                $_SESSION['success'] = 'Serveur modifie sans tentative SSH.';
            }

            $stmt = $pdo->prepare("
                UPDATE servers SET
                    name = :name,
                    hostname = :hostname,
                    ssh_port = :ssh_port,
                    ssh_user = :ssh_user,
                    ssh_password = :ssh_password,
                    os = :os,
                    ssh_status = :ssh_status,
                    ssh_enabled = :ssh_enabled
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':hostname' => $hostname,
                ':ssh_port' => $sshPort,
                ':ssh_user' => $sshUser,
                ':ssh_password' => $sshPassword,
                ':os' => $os,
                ':ssh_status' => $sshStatus,
                ':ssh_enabled' => $sshEnabled,
                ':id' => $id,
            ]);
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }

    header('Location: serveurs.php');
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

$server = $editMode ? $editData : null;
include __DIR__ . '/../includes/server-modal.php';
?>

<main class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Gestion des serveurs</h1>
        <button onclick="resetForm(); toggleModal(true)" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
            Ajouter un serveur
        </button>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="flex items-center bg-red-100 text-red-700 p-3 rounded mb-4">
            <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
            <?= htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="flex items-center bg-green-100 text-green-700 p-3 rounded mb-4">
            <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
            <?= htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

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
                                <span><?= htmlspecialchars($server['os'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td class="p-3">
                            <?php if (($server['status'] ?? 'unknown') === 'up'): ?>
                                <span class="inline-flex items-center gap-1 text-green-700 bg-green-100 px-2 py-1 rounded text-sm" title="Ping reussi">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i> UP
                                </span>
                            <?php elseif (($server['status'] ?? 'unknown') === 'down'): ?>
                                <span class="inline-flex items-center gap-1 text-red-700 bg-red-100 px-2 py-1 rounded text-sm" title="Hote injoignable">
                                    <i data-lucide="x-circle" class="w-4 h-4"></i> DOWN
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 px-2 py-1 rounded text-sm" title="Statut inconnu">
                                    <i data-lucide="help-circle" class="w-4 h-4"></i> -
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3">
                            <?php if (empty($server['ssh_enabled'])): ?>
                                <span class="text-gray-400 bg-gray-100 px-2 py-1 rounded text-sm">SSH desactive</span>
                            <?php elseif (($server['ssh_status'] ?? '') === 'success'): ?>
                                <span class="inline-flex items-center gap-1 text-green-700 bg-green-100 px-2 py-1 rounded text-sm" title="Connexion SSH reussie">
                                    <i data-lucide="terminal" class="w-4 h-4"></i> SSH OK
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-red-700 bg-red-100 px-2 py-1 rounded text-sm" title="Echec de la connexion SSH">
                                    <i data-lucide="alert-octagon" class="w-4 h-4"></i> Echec SSH
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3"><?= $server['last_check'] ? htmlspecialchars($server['last_check']) : 'Jamais' ?></td>
                        <td class="px-4 py-2">
                            <a href="serveurs.php?edit=<?= (int) $server['id'] ?>" class="text-blue-600 hover:underline flex items-center gap-1 mb-2">
                                <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
                            </a>
                            <form method="POST" action="serveurs.php" onsubmit="return confirm('Confirmer la suppression ?');">
                                <?= msmCsrfField() ?>
                                <input type="hidden" name="delete_id" value="<?= (int) $server['id'] ?>">
                                <button type="submit" class="text-red-600 hover:underline flex items-center gap-1">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i> Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-gray-500 py-4">Aucun serveur enregistre.</td>
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
