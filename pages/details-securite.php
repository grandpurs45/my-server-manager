<?php
session_start();

require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\SecurityAudit;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
$stmt->execute([$id]);
$serveur = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

if (!$serveur) {
    echo '<div class="text-red-600 font-bold">Serveur introuvable.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (empty($serveur['security_enabled'])) {
    ?>
    <a href="<?= $baseUrl ?>pages/securite-serveurs.php"
       class="inline-block mb-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded shadow">
        Retour a la liste des serveurs
    </a>

    <div class="rounded border border-slate-200 bg-white p-6 shadow">
        <h1 class="text-2xl font-bold mb-3">Analyse securite desactivee - <?= htmlspecialchars($serveur['name']) ?></h1>
        <p class="text-sm text-slate-600 mb-4">
            Cette cible n'est pas incluse dans le module securite. Aucun controle SSH ou systeme n'a ete lance.
        </p>
        <a href="<?= $baseUrl ?>pages/serveurs.php?edit=<?= (int) $serveur['id'] ?>"
           class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Activer dans les options du serveur
        </a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<a href="<?= $baseUrl ?>pages/securite-serveurs.php"
   class="inline-block mb-6 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded shadow">
    Retour a la liste des serveurs
</a>

<h1 class="text-2xl font-bold mb-4">Details securite - <?= htmlspecialchars($serveur['name']) ?></h1>

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

    <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-2">Patch Management</h2>
        <p class="text-sm text-slate-600">
            Les mises a jour, reboots requis et upgrades OS sont maintenant suivis dans le module dedie.
        </p>
        <a href="<?= $baseUrl ?>pages/patch-management.php"
           class="mt-3 inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <i data-lucide="package-check" class="w-4 h-4"></i>
            Ouvrir Patch Management
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
