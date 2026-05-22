<?php
session_start();

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\SecurityAudit;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
$stmt->execute([$id]);
$serveur = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reboot') {
    msmRequireValidCsrf('details-securite.php?id=' . $id);

    if ($serveur && SecurityAudit::rebootServer($serveur)) {
        $_SESSION['success'] = 'Redemarrage du serveur declenche.';
        header('Location: securite-serveurs.php');
        exit;
    }

    $_SESSION['error'] = 'Echec du redemarrage. SSH ou droits manquants.';
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
    Retour a la liste des serveurs
</a>

<h1 class="text-2xl font-bold mb-4">Details securite - <?= htmlspecialchars($serveur['name']) ?></h1>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="text-red-600 font-bold mb-4"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Informations generales</h2>
        <p><strong>Nom :</strong> <?= htmlspecialchars($serveur['name']) ?></p>
        <p><strong>OS :</strong> <?= htmlspecialchars($serveur['os']) ?></p>
        <p><strong>Statut :</strong> <?= $serveur['status'] === 'up' ? 'UP' : 'DOWN' ?></p>
        <p><strong>Adresse :</strong> <?= htmlspecialchars($serveur['hostname']) ?>:<?= (int) $serveur['ssh_port'] ?></p>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Derniere verification</h2>
        <p><strong>SSH :</strong> <?= $serveur['ssh_status'] === 'success' ? 'OK' : 'Echec' ?></p>
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
            echo '<p class="text-gray-500 italic">Aucun port detecte.</p>';
        } else {
            echo '<ul class="text-sm">';
            foreach ($ports as $p) {
                echo '<li><strong>' . htmlspecialchars($p['proto']) . '</strong> sur <code>' . htmlspecialchars($p['addr'] . ':' . $p['port']) . '</code></li>';
            }
            echo '</ul>';
        }
        ?>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Utilisateurs systeme</h2>
        <p class="text-sm text-gray-500 italic">A implementer</p>
    </div>

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Mises a jour securite</h2>
        <?php
        $updates = SecurityAudit::getSecurityUpdates($serveur);
        $reboot = SecurityAudit::isRebootRequired($serveur);

        if (isset($updates['error'])) {
            echo '<p class="text-red-600">' . htmlspecialchars($updates['error']) . '</p>';
        } else {
            if ($reboot): ?>
                <div class="p-3 mb-4 border border-yellow-400 bg-yellow-100 text-yellow-800 rounded">
                    <strong>Ce serveur necessite un redemarrage.</strong>
                </div>
            <?php endif;

            $canSudo = SecurityAudit::canUseSudo($serveur);

            if ($reboot && $canSudo): ?>
                <form method="post" onsubmit="return confirm('Confirmer le redemarrage du serveur ?');">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="action" value="reboot">
                    <button type="submit"
                            class="mt-2 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded shadow">
                        Redemarrer maintenant
                    </button>
                </form>
            <?php elseif ($reboot && !$canSudo): ?>
                <p class="text-sm text-gray-500 italic">Redemarrage non disponible : droits sudo requis.</p>
            <?php endif;

            if (empty($updates['security'])) {
                echo '<p class="text-green-600">Aucune mise a jour de securite disponible</p>';
            } else {
                echo '<p class="text-orange-600 font-semibold">Mises a jour de securite disponibles :</p><ul class="text-sm mt-1">';
                foreach ($updates['security'] as $u) {
                    echo '<li><strong>' . htmlspecialchars($u['package']) . '</strong> -> ' . htmlspecialchars($u['version']) . '</li>';
                }
                echo '</ul>';
            }

            if (!empty($updates['normal'])) {
                echo '<p class="mt-4 text-blue-700 font-semibold">Mises a jour systeme non critiques :</p><ul class="text-sm mt-1">';
                foreach ($updates['normal'] as $u) {
                    echo '<li><strong>' . htmlspecialchars($u['package']) . '</strong> -> ' . htmlspecialchars($u['version']) . '</li>';
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
            echo '<p class="text-orange-600 font-semibold">UFW inactif</p>';
        } else {
            echo '<p class="text-green-600 font-semibold">UFW actif</p>';
            echo '<pre class="mt-2 text-xs bg-gray-100 p-2 rounded border border-gray-200 whitespace-pre-wrap">'
                . htmlspecialchars($ufw['raw']) . '</pre>';
        }
        ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
