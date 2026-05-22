<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\Serveur;

$serveurs = Serveur::getAll($pdo);
?>

<h1 class="text-2xl font-bold mb-6">Securite des serveurs</h1>

<div class="mb-4">
    <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
        Lancer une analyse securite complete
    </button>
</div>

<div class="overflow-x-auto bg-white shadow rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-blue-700 text-white">
        <tr>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Nom</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">OS</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Statut</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Vulnerabilites</th>
            <th class="px-6 py-3 text-left text-sm font-bold uppercase">Action</th>
        </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($serveurs as $srv): ?>
            <tr>
                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($srv['name']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($srv['os']) ?></td>
                <td class="px-6 py-4">
                    <?php if ($srv['status'] === 'up'): ?>
                        <span class="text-green-600 font-semibold">UP</span>
                    <?php else: ?>
                        <span class="text-red-600 font-semibold">DOWN</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">Audit a venir</span>
                </td>
                <td class="px-6 py-4">
                    <a href="<?= $baseUrl ?>pages/details-securite.php?id=<?= (int) $srv['id'] ?>" class="text-blue-600 hover:underline text-sm">Voir details</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
