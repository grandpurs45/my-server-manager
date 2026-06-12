<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\AuthManager;

$modules = AuthManager::MODULES;

function msmRedirectUsers(): void
{
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    msmRequireValidCsrf('users.php');

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $authManager->createUser([
                'username' => $_POST['username'] ?? '',
                'display_name' => $_POST['display_name'] ?? '',
                'password' => $_POST['password'] ?? '',
                'is_admin' => isset($_POST['is_admin']),
                'is_active' => isset($_POST['is_active']),
                'permissions' => $_POST['permissions'] ?? [],
            ]);
            $_SESSION['success'] = 'Utilisateur cree.';
        } elseif ($action === 'update') {
            $authManager->updateUser((int) ($_POST['user_id'] ?? 0), [
                'display_name' => $_POST['display_name'] ?? '',
                'is_admin' => isset($_POST['is_admin']),
                'is_active' => isset($_POST['is_active']),
                'permissions' => $_POST['permissions'] ?? [],
            ]);
            $_SESSION['success'] = 'Utilisateur mis a jour.';
        } elseif ($action === 'password') {
            $authManager->changePassword((int) ($_POST['user_id'] ?? 0), (string) ($_POST['password'] ?? ''), isset($_POST['password_must_change']));
            $_SESSION['success'] = 'Mot de passe mis a jour.';
        } elseif ($action === 'delete') {
            $authManager->deleteUser((int) ($_POST['user_id'] ?? 0));
            $_SESSION['success'] = 'Utilisateur supprime.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    msmRedirectUsers();
}

$users = $authManager->listUsers();
$permissionsByUser = [];
foreach ($users as $user) {
    $permissionsByUser[(int) $user['id']] = $authManager->permissionsForUser((int) $user['id']);
}

$generatedPassword = $authManager->generatePassword();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Utilisateurs et droits</h1>
            <p class="text-sm text-gray-500 mt-1">Authentification locale MSM et acces modulaires.</p>
        </div>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="rounded border border-red-200 bg-red-50 p-4 text-red-800">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="rounded border border-green-200 bg-green-50 p-4 text-green-800">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <details class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
            <span class="text-lg font-semibold">Creer un utilisateur</span>
            <span class="inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white">
                <i data-lucide="user-plus" class="h-4 w-4"></i>
                Nouveau
            </span>
        </summary>
        <form method="POST" class="border-t border-gray-200 p-5 space-y-5">
            <?= msmCsrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="grid gap-4 lg:grid-cols-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="username">Utilisateur</label>
                    <input id="username" name="username" type="text" required class="mt-2 w-full rounded border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="display_name">Nom affiche</label>
                    <input id="display_name" name="display_name" type="text" class="mt-2 w-full rounded border border-gray-300 px-3 py-2">
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700" for="password">Mot de passe</label>
                    <div class="mt-2 flex gap-2">
                        <input id="password" name="password" type="password" required value="<?= htmlspecialchars($generatedPassword) ?>" class="w-full rounded border border-gray-300 px-3 py-2 font-mono">
                        <button type="button" data-password-toggle="password" class="inline-flex items-center rounded border border-gray-300 px-3 hover:bg-gray-50" title="Afficher ou masquer">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                        </button>
                        <button type="button" data-password-target="password" class="inline-flex items-center rounded border border-gray-300 px-3 hover:bg-gray-50" title="Generer">
                            <i data-lucide="wand-sparkles" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-6">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" checked class="rounded border-gray-300">
                    Actif
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_admin" class="rounded border-gray-300">
                    Administrateur
                </label>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Droits modulaires</h3>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($modules as $moduleKey => $moduleLabel): ?>
                        <label class="inline-flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($moduleKey) ?>" class="rounded border-gray-300">
                            <?= htmlspecialchars($moduleLabel) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                <i data-lucide="user-plus" class="h-4 w-4"></i>
                Creer
            </button>
        </form>
    </details>

    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold">Comptes existants</h2>
                <p class="text-xs text-gray-500">Recherche et tri local sur les comptes affiches.</p>
            </div>
            <div class="relative w-full sm:w-80">
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                <input
                    id="users_search"
                    type="search"
                    placeholder="Rechercher un utilisateur"
                    class="w-full rounded border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-blue-500"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="overflow-x-auto">
            <table id="users_table" class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">
                            <button type="button" data-sort-column="0" class="inline-flex items-center gap-1 font-semibold uppercase hover:text-slate-900">
                                Utilisateur
                                <i data-lucide="arrow-up-down" class="h-3.5 w-3.5"></i>
                            </button>
                        </th>
                        <th class="px-5 py-3">
                            <button type="button" data-sort-column="1" class="inline-flex items-center gap-1 font-semibold uppercase hover:text-slate-900">
                                Profil
                                <i data-lucide="arrow-up-down" class="h-3.5 w-3.5"></i>
                            </button>
                        </th>
                        <th class="px-5 py-3">
                            <button type="button" data-sort-column="2" data-sort-type="number" class="inline-flex items-center gap-1 font-semibold uppercase hover:text-slate-900">
                                Modules
                                <i data-lucide="arrow-up-down" class="h-3.5 w-3.5"></i>
                            </button>
                        </th>
                        <th class="px-5 py-3">
                            <button type="button" data-sort-column="3" class="inline-flex items-center gap-1 font-semibold uppercase hover:text-slate-900">
                                Derniere connexion
                                <i data-lucide="arrow-up-down" class="h-3.5 w-3.5"></i>
                            </button>
                        </th>
                        <th class="px-5 py-3 text-right">Gestion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $user):
                        $userId = (int) $user['id'];
                        $userPermissions = $permissionsByUser[$userId] ?? [];
                        $moduleCount = $user['is_admin'] ? count($modules) : count($userPermissions);
                        $moduleSummary = $user['is_admin']
                            ? 'Tous'
                            : ($moduleCount > 0 ? $moduleCount . ' module(s)' : 'Aucun');
                        $profileSort = sprintf(
                            '%s %s',
                            $user['is_active'] ? 'actif' : 'inactif',
                            $user['is_admin'] ? 'admin' : 'utilisateur'
                        );
                        $searchText = trim(implode(' ', [
                            $user['username'],
                            $user['display_name'] ?: '',
                            $profileSort,
                            $moduleSummary,
                            $user['last_login_at'] ?: '',
                        ]));
                    ?>
                        <tr class="align-top" data-user-row data-search="<?= htmlspecialchars(mb_strtolower($searchText), ENT_QUOTES, 'UTF-8') ?>">
                            <td class="px-5 py-4" data-sort="<?= htmlspecialchars(mb_strtolower((string) $user['username']), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($user['display_name'] ?: '-') ?></div>
                                <?php if ((int) $user['password_must_change'] === 1): ?>
                                    <div class="mt-1 text-xs font-semibold text-amber-700">Mot de passe a changer</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4" data-sort="<?= htmlspecialchars($profileSort, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="rounded px-2 py-1 text-xs font-semibold <?= $user['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                                    <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                </span>
                                <span class="ml-2 rounded px-2 py-1 text-xs font-semibold <?= $user['is_admin'] ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' ?>">
                                    <?= $user['is_admin'] ? 'Admin' : 'Utilisateur' ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-gray-700" data-sort="<?= $moduleCount ?>"><?= htmlspecialchars($moduleSummary) ?></td>
                            <td class="px-5 py-4 text-gray-500" data-sort="<?= htmlspecialchars($user['last_login_at'] ?: '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($user['last_login_at'] ?: '-') ?></td>
                            <td class="px-5 py-4 text-right">
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer list-none rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                                        Gerer
                                    </summary>
                                    <div class="mt-3 w-[min(760px,calc(100vw-3rem))] rounded-lg border border-gray-200 bg-white p-4 text-left shadow-lg">
                                        <div class="grid gap-5 xl:grid-cols-2">
                                            <form method="POST" class="space-y-4 rounded border border-gray-200 bg-gray-50 p-4">
                                                <?= msmCsrfField() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="user_id" value="<?= $userId ?>">

                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700" for="display_name_<?= $userId ?>">Nom affiche</label>
                                                    <input id="display_name_<?= $userId ?>" name="display_name" type="text" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" class="mt-2 w-full rounded border border-gray-300 px-3 py-2">
                                                </div>

                                                <div class="flex flex-wrap gap-6">
                                                    <label class="inline-flex items-center gap-2 text-sm">
                                                        <input type="checkbox" name="is_active" <?= $user['is_active'] ? 'checked' : '' ?> class="rounded border-gray-300">
                                                        Actif
                                                    </label>
                                                    <label class="inline-flex items-center gap-2 text-sm">
                                                        <input type="checkbox" name="is_admin" <?= $user['is_admin'] ? 'checked' : '' ?> class="rounded border-gray-300">
                                                        Administrateur
                                                    </label>
                                                </div>

                                                <div>
                                                    <h4 class="mb-3 text-sm font-semibold text-gray-700">Droits modulaires</h4>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <?php foreach ($modules as $moduleKey => $moduleLabel): ?>
                                                            <label class="inline-flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2 text-sm">
                                                                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($moduleKey) ?>"
                                                                    <?= in_array($moduleKey, $userPermissions, true) ? 'checked' : '' ?>
                                                                    class="rounded border-gray-300">
                                                                <?= htmlspecialchars($moduleLabel) ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <button type="submit" class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                                                    <i data-lucide="save" class="h-4 w-4"></i>
                                                    Enregistrer
                                                </button>
                                            </form>

                                            <div class="space-y-4">
                                                <form method="POST" class="space-y-4 rounded border border-gray-200 bg-gray-50 p-4">
                                                    <?= msmCsrfField() ?>
                                                    <input type="hidden" name="action" value="password">
                                                    <input type="hidden" name="user_id" value="<?= $userId ?>">

                                                    <div>
                                                        <label class="block text-sm font-semibold text-gray-700" for="password_<?= $userId ?>">Nouveau mot de passe</label>
                                                        <div class="mt-2 flex gap-2">
                                                            <input id="password_<?= $userId ?>" name="password" type="password" class="w-full rounded border border-gray-300 px-3 py-2 font-mono">
                                                            <button type="button" data-password-toggle="password_<?= $userId ?>" class="inline-flex items-center rounded border border-gray-300 px-3 hover:bg-gray-50" title="Afficher ou masquer">
                                                                <i data-lucide="eye" class="h-4 w-4"></i>
                                                            </button>
                                                            <button type="button" data-password-target="password_<?= $userId ?>" class="inline-flex items-center rounded border border-gray-300 px-3 hover:bg-gray-50" title="Generer">
                                                                <i data-lucide="wand-sparkles" class="h-4 w-4"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <?php if ((int) $currentUser['id'] !== $userId): ?>
                                                        <label class="inline-flex items-center gap-2 text-sm">
                                                            <input type="checkbox" name="password_must_change" class="rounded border-gray-300">
                                                            Forcer un changement par cet utilisateur
                                                        </label>
                                                    <?php else: ?>
                                                        <p class="text-sm text-gray-500">Le changement de votre propre mot de passe supprime automatiquement l'avertissement.</p>
                                                    <?php endif; ?>

                                                    <button type="submit" class="inline-flex items-center gap-2 rounded bg-slate-700 px-4 py-2 font-semibold text-white hover:bg-slate-800">
                                                        <i data-lucide="key-round" class="h-4 w-4"></i>
                                                        Changer le mot de passe
                                                    </button>
                                                </form>

                                                <form method="POST" onsubmit="return confirm('Supprimer cet utilisateur ?');" class="rounded border border-red-200 bg-red-50 p-4">
                                                    <?= msmCsrfField() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                                                    <button type="submit" class="inline-flex items-center gap-2 rounded bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700">
                                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="users_empty_row" class="hidden">
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">Aucun utilisateur ne correspond a cette recherche.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-_=';
    const length = <?= (int) ($settings->get('auth', 'password_generator_length') ?? 18) ?>;

    function generatePassword() {
        const values = new Uint32Array(length);
        window.crypto.getRandomValues(values);
        let password = '';
        for (const value of values) {
            password += chars[value % chars.length];
        }
        return password;
    }

    document.querySelectorAll('[data-password-target]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordTarget);
            if (input) {
                input.value = generatePassword();
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', input.type === 'password' ? 'eye' : 'eye-off');
                if (window.lucide) {
                    lucide.createIcons();
                }
            }
        });
    });

    const usersTable = document.getElementById('users_table');
    const usersSearch = document.getElementById('users_search');
    const emptyRow = document.getElementById('users_empty_row');
    let activeSort = { column: 0, direction: 'asc', type: 'text' };

    function userRows() {
        return Array.from(usersTable.querySelectorAll('[data-user-row]'));
    }

    function updateEmptyState() {
        const visibleRows = userRows().filter(row => !row.classList.contains('hidden'));
        emptyRow.classList.toggle('hidden', visibleRows.length > 0);
    }

    function sortUsers(column, direction, type = 'text') {
        const tbody = usersTable.tBodies[0];
        const multiplier = direction === 'asc' ? 1 : -1;
        const rows = userRows();

        rows.sort((a, b) => {
            const cellA = a.children[column];
            const cellB = b.children[column];
            const valueA = cellA?.dataset.sort ?? cellA?.textContent.trim() ?? '';
            const valueB = cellB?.dataset.sort ?? cellB?.textContent.trim() ?? '';

            if (type === 'number') {
                return ((Number(valueA) || 0) - (Number(valueB) || 0)) * multiplier;
            }

            return valueA.localeCompare(valueB, 'fr', { numeric: true, sensitivity: 'base' }) * multiplier;
        });

        rows.forEach(row => tbody.insertBefore(row, emptyRow));
        activeSort = { column, direction, type };
    }

    usersTable.querySelectorAll('[data-sort-column]').forEach(button => {
        button.addEventListener('click', () => {
            const column = Number(button.dataset.sortColumn);
            const type = button.dataset.sortType || 'text';
            const direction = activeSort.column === column && activeSort.direction === 'asc' ? 'desc' : 'asc';
            sortUsers(column, direction, type);

            usersTable.querySelectorAll('[data-sort-column] i').forEach(icon => icon.setAttribute('data-lucide', 'arrow-up-down'));
            const icon = button.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', direction === 'asc' ? 'arrow-up' : 'arrow-down');
            }
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    });

    usersSearch.addEventListener('input', () => {
        const query = usersSearch.value.trim().toLocaleLowerCase('fr');
        userRows().forEach(row => {
            const match = row.dataset.search.includes(query);
            row.classList.toggle('hidden', !match);
        });
        updateEmptyState();
    });

    sortUsers(activeSort.column, activeSort.direction, activeSort.type);
    updateEmptyState();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
