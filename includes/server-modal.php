<?php
$formAction = $editMode ? 'serveurs.php' : $baseUrl . 'pages/add-server.php';
?>
<div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6 relative">
        <button type="button" onclick="toggleModal(false)" class="absolute top-2 right-2 text-gray-600 hover:text-black">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h2 id="modal-title" class="text-xl font-bold mb-4">
            <?= $editMode ? 'Modifier un serveur' : 'Ajouter un serveur' ?>
        </h2>

        <form action="<?= $formAction ?>" method="post" class="space-y-4">
            <?= msmCsrfField() ?>
            <input type="hidden" name="form_mode" id="form-mode" value="<?= $editMode ? 'edit' : 'add' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="id" id="server-id" value="<?= htmlspecialchars($editData['id']) ?>">
            <?php endif; ?>

            <div>
                <label class="block font-medium mb-1" for="server-name">Nom du serveur</label>
                <input type="text" id="server-name" name="name"
                       value="<?= htmlspecialchars($editData['name'] ?? '') ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
            </div>

            <div>
                <label class="block font-medium mb-1" for="server-ip">Adresse IP / Nom d'hote</label>
                <input type="text" id="server-ip" name="hostname"
                       value="<?= htmlspecialchars($editData['hostname'] ?? '') ?>"
                       required
                       class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-medium mb-1" for="target-type">Type de cible</label>
                    <select id="target-type" name="target_type" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                        <?php foreach ($targetTypes as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= (($editData['target_type'] ?? 'linux') === $value) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1" for="environment">Environnement</label>
                    <select id="environment" name="environment" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                        <?php foreach ($environments as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= (($editData['environment'] ?? 'production') === $value) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1" for="criticality">Criticite</label>
                    <select id="criticality" name="criticality" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                        <?php foreach ($criticalities as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= (($editData['criticality'] ?? 'medium') === $value) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium mb-1" for="collection-method">Methode de collecte</label>
                    <select id="collection-method" name="collection_method" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                        <?php foreach ($collectionMethods as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= (($editData['collection_method'] ?? 'ssh') === $value) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded border border-slate-200 bg-slate-50 px-3 py-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="security_enabled" name="security_enabled" class="form-checkbox"
                            <?= isset($server['security_enabled']) && (int)$server['security_enabled'] === 1 ? 'checked' : '' ?>>
                        <span class="ml-2 font-medium">Inclure dans l'analyse securite</span>
                    </label>
                    <p class="mt-1 text-xs text-slate-500">
                        Active les vues et controles du module securite pour cette cible.
                    </p>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-3 py-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="patch_management_enabled" name="patch_management_enabled" class="form-checkbox"
                            <?= isset($server['patch_management_enabled']) && (int)$server['patch_management_enabled'] === 1 ? 'checked' : '' ?>>
                        <span class="ml-2 font-medium">Inclure dans le patch management</span>
                    </label>
                    <p class="mt-1 text-xs text-slate-500">
                        Active le suivi des mises a jour pour cette cible.
                    </p>
                </div>
            </div>

            <div>
                <label class="block font-medium mb-1" for="tags">Tags</label>
                <input type="hidden" id="tags" name="tags" value="<?= htmlspecialchars($editData['tags'] ?? '') ?>">
                <div class="border rounded px-2 py-2 min-h-11 focus-within:ring focus-within:border-blue-300">
                    <div id="tag-list" class="flex flex-wrap gap-2"></div>
                    <input type="text" id="tag-input"
                           placeholder="Ajouter un tag puis Entrer"
                           class="w-full border-0 focus:outline-none mt-2">
                </div>
            </div>

            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" id="ssh_enabled" name="ssh_enabled" class="form-checkbox"
                        <?= isset($server['ssh_enabled']) && (int)$server['ssh_enabled'] === 1 ? 'checked' : '' ?>>
                    <span class="ml-2">Connexion SSH activee</span>
                </label>
            </div>

            <div id="ssh-fields" class="mt-4 hidden space-y-4">
                <div>
                    <label class="block font-medium mb-1" for="server-user">Utilisateur SSH</label>
                    <input type="text" id="server-user" name="ssh_user"
                           value="<?= htmlspecialchars($editData['ssh_user'] ?? '') ?>"
                           required
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                </div>

                <div>
                    <label class="block font-medium mb-1" for="ssh_password">Mot de passe SSH</label>
                    <input type="password" id="ssh_password" name="ssh_password" value=""
                           placeholder="<?= $editMode ? 'Laisser vide pour conserver' : 'Mot de passe SSH' ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                </div>

                <div>
                    <label class="block font-medium mb-1" for="ssh_port">Port SSH</label>
                    <input type="number" id="ssh_port" name="ssh_port"
                           value="<?= htmlspecialchars($editData['ssh_port'] ?? 22) ?>"
                           required
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring focus:border-blue-300">
                </div>
            </div>

            <div class="text-right pt-2 flex justify-end gap-2">
                <button type="button" onclick="toggleModal(false)"
                        class="bg-gray-300 hover:bg-gray-400 text-black font-semibold py-2 px-4 rounded">
                    Annuler
                </button>
                <button type="submit"
                        id="submit-button"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                    <?= $editMode ? 'Enregistrer les modifications' : 'Ajouter le serveur' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.msmInventoryDefaults = {
    target_type: <?= json_encode(array_key_first($targetTypes) ?: 'other') ?>,
    environment: <?= json_encode(array_key_first($environments) ?: 'other') ?>,
    criticality: <?= json_encode(array_key_first($criticalities) ?: 'medium') ?>,
    collection_method: <?= json_encode(array_key_first($collectionMethods) ?: 'manual') ?>
};

document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('ssh_enabled');
    const sshFields = document.getElementById('ssh-fields');

    function toggleSSHFields() {
        if (!checkbox || !sshFields) return;

        if (checkbox.checked) {
            sshFields.classList.remove('hidden');
            sshFields.querySelectorAll('input').forEach(el => el.disabled = false);
        } else {
            sshFields.classList.add('hidden');
            sshFields.querySelectorAll('input').forEach(el => el.disabled = true);
        }
    }

    if (checkbox && sshFields) {
        checkbox.addEventListener('change', toggleSSHFields);
        toggleSSHFields();
    }

    const tagsInput = document.getElementById('tags');
    const tagInput = document.getElementById('tag-input');
    const tagList = document.getElementById('tag-list');
    let tags = [];

    function syncTags() {
        if (!tagsInput || !tagList) return;
        tagsInput.value = tags.join(', ');
        tagList.innerHTML = '';

        tags.forEach(tag => {
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 px-2 py-1 text-xs';
            badge.textContent = tag + ' x';
            badge.addEventListener('click', () => {
                tags = tags.filter(existing => existing !== tag);
                syncTags();
            });
            tagList.appendChild(badge);
        });
    }

    function addTag(rawTag) {
        const tag = rawTag.trim();
        if (!tag || tags.includes(tag)) return;
        tags.push(tag);
        syncTags();
    }

    if (tagsInput && tagInput && tagList) {
        tags = tagsInput.value
            .split(',')
            .map(tag => tag.trim())
            .filter(Boolean);
        syncTags();

        tagInput.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addTag(tagInput.value.replace(',', ''));
                tagInput.value = '';
            }
        });

        tagInput.addEventListener('blur', () => {
            addTag(tagInput.value);
            tagInput.value = '';
        });

        window.addEventListener('msm:reset-tags', () => {
            tags = [];
            syncTags();
        });
    }
});
</script>
