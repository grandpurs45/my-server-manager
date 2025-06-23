<?php
$formAction = $editMode ? 'serveurs.php' : '/pages/add-server.php';
?>
<!-- Modale d'ajout/modification -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-xl p-6 relative">
        <button onclick="toggleModal(false)" class="absolute top-2 right-2 text-gray-600 hover:text-black">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h2 id="modal-title" class="text-xl font-bold mb-4">
            <?= $editMode ? '✏️ Modifier un serveur' : '➕ Ajouter un serveur' ?>
        </h2>

        <form action="<?= $formAction ?>" method="post" class="space-y-4">
            <input type="hidden" name="form_mode" id="form-mode" value="<?= $editMode ? 'edit' : 'add' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="id" id="server-id" value="<?= $editData['id'] ?>">
            <?php endif; ?>

            <div>
                <label class="block font-medium mb-1" for="name">Nom du serveur</label>
                <input type="text" id="server-name" name="name"
                       value="<?= htmlspecialchars($editData['name'] ?? '') ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
            </div>

            <div>
                <label class="block font-medium mb-1" for="hostname">Adresse IP / Nom d’hôte</label>
                <input type="text" id="server-ip" name="hostname"
                       value="<?= htmlspecialchars($editData['hostname'] ?? '') ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
            </div>

            <div>
                <label class="block font-medium mb-1" for="port">Port SSH</label>
                <input type="number" id="server-port" name="port"
                       value="<?= htmlspecialchars($editData['port'] ?? 22) ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
            </div>

            <div>
                <label class="block font-medium mb-1" for="ssh_user">Utilisateur SSH</label>
                <input type="text" id="server-user" name="ssh_user"
                       value="<?= htmlspecialchars($editData['ssh_user'] ?? '') ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
            </div>

            <div>
                <label class="block font-medium mb-1" for="ssh_password">Mot de passe SSH</label>
                <input type="password" id="server-password" name="ssh_password"
                       value="<?= htmlspecialchars($editData['ssh_password'] ?? '') ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300"/>
            </div>

            <div class="text-right pt-2 flex justify-end gap-2">
                <button type="button" onclick="toggleModal(false)"
                        class="bg-gray-300 hover:bg-gray-400 text-black font-semibold py-2 px-4 rounded">
                    Annuler
                </button>
                <button type="submit"
                        id="submit-button"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                    <?= $editMode ? '💾 Enregistrer les modifications' : '💾 Ajouter le serveur' ?>
                </button>
            </div>
        </form>
    </div>
</div>