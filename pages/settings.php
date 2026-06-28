<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/os_logos.php';

use MSM\SettingsManager;

$settingsManager = new SettingsManager($pdo);

$categories = ['reseau', 'supervision', 'inventaire', 'patch_management', 'os_lifecycle', 'security', 'hardware_health', 'home_assistant', 'alerting', 'auth', 'bdd', 'msm'];
$labels = [
    'reseau' => 'Reseau',
    'supervision' => 'Supervision',
    'inventaire' => 'Inventaire',
    'patch_management' => 'Patch Management',
    'os_lifecycle' => 'Cycle de vie OS',
    'security' => 'Securite',
    'hardware_health' => 'Sante materielle',
    'home_assistant' => 'Home Assistant',
    'alerting' => 'Alerting',
    'auth' => 'Authentification',
    'bdd' => 'Base de donnees',
    'msm' => 'MSM',
];

$settings_schema = require __DIR__ . '/../config/settings-schema.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_msm_update') {
    msmRequireValidCsrf('settings.php');

    $settingsManager->deleteCategory('msm_update');
    $_SESSION['success'] = 'Cache de verification des mises a jour MSM vide. La prochaine page chargee relancera la verification.';
    header('Location: settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_os_logo') {
    msmRequireValidCsrf('settings.php');

    $slug = msmOsLogoSlug((string) ($_POST['logo_slug'] ?? ''));
    $file = $_FILES['logo_file'] ?? null;

    if ($slug === '') {
        $_SESSION['error'] = 'Identifiant de logo invalide.';
    } elseif (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Aucun fichier PNG valide recu.';
    } elseif (($file['size'] ?? 0) > 512 * 1024) {
        $_SESSION['error'] = 'Le logo est trop volumineux. Taille maximale : 512 Ko.';
    } else {
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $imageInfo = $tmpPath !== '' ? @getimagesize($tmpPath) : false;

        if (!is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== 'image/png') {
            $_SESSION['error'] = 'Seuls les fichiers PNG sont acceptes pour les logos OS.';
        } else {
            $logoDirectory = msmOsLogoDirectory();
            if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0775, true) && !is_dir($logoDirectory)) {
                $_SESSION['error'] = 'Impossible de creer le dossier des logos OS.';
            } else {
                $destination = $logoDirectory . '/' . $slug . '.png';
                if (move_uploaded_file($tmpPath, $destination)) {
                    $_SESSION['success'] = 'Logo OS ajoute : ' . $slug . '.png.';
                } else {
                    $_SESSION['error'] = 'Impossible d enregistrer le logo OS.';
                }
            }
        }
    }

    header('Location: settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fetch_os_logo') {
    msmRequireValidCsrf('settings.php');

    $query = trim((string) ($_POST['logo_query'] ?? ''));
    if ($query === '') {
        $_SESSION['error'] = 'Nom OS invalide.';
    } else {
        $result = msmOsLogoFetchFromInternet($query);
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    }

    header('Location: settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category'])) {
    msmRequireValidCsrf('settings.php');

    $category = $_POST['category'];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, ['category', 'submit', 'csrf_token'], true)) {
            $settingsManager->set($category, $key, $value);
        }
    }

    $_SESSION['success'] = 'Parametres mis a jour pour la categorie ' . ($labels[$category] ?? $category) . '.';
    header('Location: settings.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Parametres de My Server Manager</h1>
        <p class="text-sm text-gray-500 mt-1">Configuration locale de l'application et des listes utilisees par l'inventaire.</p>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="p-4 bg-red-100 text-red-800 rounded mb-4">
            <?php echo htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="p-4 bg-green-100 text-green-800 rounded mb-4">
            <?php echo htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200 px-4">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="tab-menu" role="tablist">
            <?php foreach ($categories as $index => $category): ?>
                <li class="me-2">
                    <button class="inline-block p-4 border-b-2 rounded-t-lg <?php echo $index === 0 ? 'border-blue-500 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'; ?>" data-tab="<?php echo $category; ?>" type="button">
                        <?php echo htmlspecialchars($labels[$category]); ?>
                    </button>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>

        <div class="p-6">
            <?php foreach ($categories as $index => $category):
                $settings = $settingsManager->getAllByCategory($category);
                $schema = $settings_schema[$category] ?? [];
                $keys = !empty($schema)
                    ? array_keys($schema)
                    : array_keys($settings);
            ?>
            <div class="tab-content <?php echo $index === 0 ? '' : 'hidden'; ?>" data-tab-content="<?php echo $category; ?>">
                <?php if ($category === 'auth'): ?>
                    <div class="mb-5 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span>Les comptes et droits se gerent depuis l'interface dediee.</span>
                            <a href="users.php" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700">
                                Gerer les utilisateurs
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($category === 'msm'): ?>
                    <div class="mb-5 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span>Force une nouvelle verification de version MSM au prochain chargement de page.</span>
                            <form method="POST">
                                <?php echo msmCsrfField(); ?>
                                <input type="hidden" name="action" value="check_msm_update">
                                <button type="submit" class="inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-700">
                                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                    Verifier les mises a jour MSM
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="mb-5 rounded-lg border border-gray-200 bg-white p-4">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Logos OS</h2>
                                <p class="mt-1 text-sm text-slate-600">
                                    Convention : <code><?= htmlspecialchars(msmOsLogoRelativeDirectory()) ?>/identifiant.png</code>.
                                    Exemple : <code>alpine.png</code> pour Alpine Linux.
                                </p>
                            </div>
                        </div>

                        <form method="POST" class="mb-4 grid grid-cols-1 gap-3 rounded border border-blue-100 bg-blue-50 p-3 md:grid-cols-[1fr_auto]">
                            <?php echo msmCsrfField(); ?>
                            <input type="hidden" name="action" value="fetch_os_logo">
                            <div>
                                <label class="block text-sm font-semibold text-blue-900" for="logo_query">Recherche automatique</label>
                                <input id="logo_query" name="logo_query" type="text" placeholder="Alpine Linux 3.21, Rocky Linux 10.1, Home Assistant OS"
                                       class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                                <p class="mt-1 text-xs text-blue-800">MSM essaie d'abord les familles connues, puis un identifiant derive du texte saisi.</p>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    Trouver automatiquement
                                </button>
                            </div>
                        </form>

                        <form method="POST" enctype="multipart/form-data" class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto]">
                            <?php echo msmCsrfField(); ?>
                            <input type="hidden" name="action" value="upload_os_logo">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="logo_slug">Identifiant OS</label>
                                <input id="logo_slug" name="logo_slug" type="text" placeholder="alpine, freebsd, oracle-linux"
                                       class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700" for="logo_file">Fichier PNG</label>
                                <input id="logo_file" name="logo_file" type="file" accept="image/png"
                                       class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    Ajouter
                                </button>
                            </div>
                        </form>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                            <?php foreach (msmOsLogoEntries($baseUrl) as $logo): ?>
                                <div class="rounded border border-gray-200 bg-slate-50 p-3">
                                    <div class="flex items-center gap-2">
                                        <img src="<?= htmlspecialchars($logo['url']) ?>" alt="" class="h-6 w-6">
                                        <span class="truncate text-sm font-semibold text-slate-800"><?= htmlspecialchars($logo['slug']) ?></span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        <?= $logo['custom'] ? 'Personnalise' : 'Integre' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <form method="POST" class="space-y-5 max-w-4xl">
                    <?php echo msmCsrfField(); ?>
                    <input type="hidden" name="category" value="<?php echo $category; ?>">

                    <?php if (empty($keys)): ?>
                        <p class="text-gray-500 italic">Aucun parametre declare pour cette categorie.</p>
                    <?php else: ?>
                        <?php foreach ($keys as $key):
                            $fieldSchema = $schema[$key] ?? [];
                            $type = $fieldSchema['type'] ?? 'text';
                            $label = $fieldSchema['label'] ?? $key;
                            $value = $settings[$key] ?? ($fieldSchema['default'] ?? '');
                        ?>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="block text-sm font-semibold text-gray-800" for="<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </label>

                                <?php if ($type === 'checkbox'): ?>
                                    <div class="mt-2">
                                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="false">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="true"
                                                <?php echo $value === 'true' ? 'checked' : ''; ?>
                                                class="rounded border-gray-300">
                                            Active
                                        </label>
                                    </div>
                                <?php elseif ($type === 'textarea'): ?>
                                    <textarea id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" rows="7"
                                              class="mt-2 block w-full border-gray-300 rounded-md shadow-sm font-mono text-sm"><?php echo htmlspecialchars($value); ?></textarea>
                                    <p class="text-xs text-gray-500 mt-2">Une option par ligne. Format recommande : <code>valeur=Libelle</code>.</p>
                                <?php else: ?>
                                    <input type="<?php echo htmlspecialchars($type); ?>" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>"
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           class="mt-2 block w-full border-gray-300 rounded-md shadow-sm">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Enregistrer</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('#tab-menu button').forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const selectedTab = tabBtn.getAttribute('data-tab');

            document.querySelectorAll('#tab-menu button').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
            });
            tabBtn.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
            tabBtn.classList.add('border-blue-500', 'text-blue-600');

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            document.querySelector(`.tab-content[data-tab-content="${selectedTab}"]`).classList.remove('hidden');
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
