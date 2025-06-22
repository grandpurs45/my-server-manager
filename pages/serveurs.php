<?php
session_start(); // pour stocker le message

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $os = trim($_POST['os'] ?? '');

    if (empty($name) || empty($ip) || empty($os)) {
        $_SESSION['error'] = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $_SESSION['error'] = "Adresse IP invalide.";
    } else {
        // Vérifier si l'IP existe déjà
        $check = $pdo->prepare("SELECT COUNT(*) FROM servers WHERE ip_address = :ip");
        $check->execute([':ip' => $ip]);
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Cette adresse IP est déjà enregistrée.";
        } else {
            // Ajout en base si tout est OK
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
  <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
    <h2 class="text-xl font-semibold mb-4">Ajouter un serveur</h2>
    <form action="serveurs.php" method="post">
      <div class="mb-4">
        <label class="block font-medium mb-1" for="name">Nom du serveur</label>
        <input type="text" id="name" name="name" required class="w-full border-gray-300 rounded px-3 py-2 border focus:outline-none focus:ring focus:border-blue-300" />
      </div>
      <div class="mb-4">
        <label class="block font-medium mb-1" for="ip">Adresse IP</label>
        <input type="text" id="ip" name="ip" required class="w-full border-gray-300 rounded px-3 py-2 border focus:outline-none focus:ring focus:border-blue-300" />
      </div>
      <div class="mb-4">
        <label class="block font-medium mb-1" for="os">Système d'exploitation</label>
        <input type="text" id="os" name="os" required class="w-full border-gray-300 rounded px-3 py-2 border focus:outline-none focus:ring focus:border-blue-300" />
      </div>
      <div class="flex justify-end">
        <button type="button" onclick="toggleModal(false)" class="mr-2 px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Annuler</button>
        <button type="submit" name="add_server" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Ajouter</button>
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
                <th class="p-3">Dernier check</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($servers as $server): ?>
            <tr class="border-t">
                <td class="p-3"><?= htmlspecialchars($server['name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($server['ip_address']) ?></td>
                <td class="p-3"><?= htmlspecialchars($server['os']) ?></td>
                <td class="p-3">
                    <?php if ($server['status'] === 'up'): ?>
                        <span class="text-green-600 font-semibold"><i class="fa-solid fa-circle-check"></i> UP</span>
                    <?php else: ?>
                        <span class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> DOWN</span>
                    <?php endif; ?>
                </td>
                <td class="p-3"><?= htmlspecialchars($server['last_check']) ?></td>
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
</script>