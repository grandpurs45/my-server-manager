<?php

require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\Serveur;
use MSM\SecurityAudit;

// Récupération de l'ID depuis l'URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
$stmt->execute([$id]);
$serveur = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'reboot') {
    $success = SecurityAudit::rebootServer($serveur);
    if ($success) {
        $_SESSION['success'] = "Redémarrage du serveur déclenché.";
        header("Location: securite-serveurs.php");
        exit;
    } else {
        echo '<div class="text-red-600 font-bold mb-4">❌ Échec du redémarrage (SSH ou droits manquants).</div>';
    }
}

require_once __DIR__ . '/../includes/header.php';

if (!$serveur) {
    echo '<div class="text-red-600 font-bold">Serveur introuvable.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<a href="<?= $baseUrl ?>pages/securite-serveurs.php"
   class="inline-block mb-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded shadow">
    ← Retour à la liste des serveurs
</a>

<h1 class="text-2xl font-bold mb-4">Détails de la sécurité – <?= htmlspecialchars($serveur['name']) ?></h1>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Informations générales</h2>
        <p><strong>Nom :</strong> <?= htmlspecialchars($serveur['name']) ?></p>
        <p><strong>OS :</strong> <?= htmlspecialchars($serveur['os']) ?></p>
        <p><strong>Statut :</strong> <?= $serveur['status'] === 'up' ? '🟢 UP' : '🔴 DOWN' ?></p>
        <p><strong>Adresse :</strong> <?= htmlspecialchars($serveur['hostname']) ?>:<?= $serveur['ssh_port'] ?></p>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Dernière vérification</h2>
        <p><strong>SSH :</strong> <?= $serveur['ssh_status'] === 'success' ? '🟢 OK' : '🔴 Échec' ?></p>
        <p><strong>Date :</strong> <?= htmlspecialchars($serveur['last_check']) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Ports ouverts</h2>
         <?php
        $ports = SecurityAudit::getOpenPorts($serveur);

        if (isset($ports['error'])) {
            echo '<p class="text-red-600">' . htmlspecialchars($ports['error']) . '</p>';
        } elseif (empty($ports)) {
            echo '<p class="text-gray-500 italic">Aucun port détecté.</p>';
        } else {
            echo '<ul class="text-sm">';
            foreach ($ports as $p) {
                echo "<li>🔓 <strong>{$p['proto']}</strong> sur <code>{$p['addr']}:{$p['port']}</code></li>";
            }
            echo '</ul>';
        }
        ?>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Utilisateurs système</h2>
        <p class="text-sm text-gray-500 italic">🚧 À implémenter</p>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Mises à jour sécurité</h2>
       <?php
        $updates = SecurityAudit::getSecurityUpdates($serveur);
        $reboot = SecurityAudit::isRebootRequired($serveur);

        if (isset($updates['error'])) {
            echo '<p class="text-red-600">' . htmlspecialchars($updates['error']) . '</p>';
        } else {
            if ($reboot): ?>
                <div class="p-3 mb-4 border border-yellow-400 bg-yellow-100 text-yellow-800 rounded">
                    ⚠️ <strong>Ce serveur nécessite un redémarrage.</strong>
                </div>
            <?php endif;

            $canSudo = SecurityAudit::canUseSudo($serveur);

            if ($reboot && $canSudo): ?>
                <form method="post" onsubmit="return confirm('Confirmer le redémarrage du serveur ?');">
                    <input type="hidden" name="action" value="reboot">
                    <button type="submit"
                            class="mt-2 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded shadow">
                        🔁 Redémarrer maintenant
                    </button>
                </form>
            <?php elseif ($reboot && !$canSudo): ?>
                <p class="text-sm text-gray-500 italic">🔒 Redémarrage non disponible : droits sudo requis.</p>
            <?php endif;


            if (empty($updates['security'])) {
                echo '<p class="text-green-600">✅ Aucune mise à jour de sécurité disponible</p>';
            } else {
                echo '<p class="text-orange-600 font-semibold">🛡️ Mises à jour de sécurité disponibles :</p><ul class="text-sm mt-1">';
                foreach ($updates['security'] as $u) {
                    echo "<li>🔐 <strong>{$u['package']}</strong> → {$u['version']}</li>";
                }
                echo '</ul>';
            }

            if (!empty($updates['normal'])) {
                echo '<p class="mt-4 text-blue-700 font-semibold">📦 Mises à jour système non critiques :</p><ul class="text-sm mt-1">';
                foreach ($updates['normal'] as $u) {
                    echo "<li>🔁 <strong>{$u['package']}</strong> → {$u['version']}</li>";
                }
                echo '</ul>';
            }
        }
        ?>

    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Pare-feu / UFW</h2>
        <?php
        $ufw = SecurityAudit::getFirewallStatus($serveur);

        if (isset($ufw['error'])) {
            echo '<p class="text-red-600">' . htmlspecialchars($ufw['error']) . '</p>';
        } elseif ($ufw['status'] === 'inactif') {
            echo '<p class="text-orange-600 font-semibold">🔓 UFW inactif</p>';
        } else {
            echo '<p class="text-green-600 font-semibold">🛡️ UFW actif</p>';
            echo '<pre class="mt-2 text-xs bg-gray-100 p-2 rounded border border-gray-200 whitespace-pre-wrap">'
                . htmlspecialchars($ufw['raw']) . '</pre>';
        }
        ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
