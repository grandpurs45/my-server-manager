<?php
session_start();

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\SettingsManager;

$settingsManager = new SettingsManager($pdo);

$categories = ['reseau', 'supervision', 'bdd', 'msm'];
$labels = [
    'reseau' => 'Reseau',
    'supervision' => 'Supervision',
    'bdd' => 'Base de donnees',
    'msm' => 'MSM',
];

$settings_schema = require __DIR__ . '/../config/settings-schema.php';

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
    <h1 class="text-2xl font-bold mb-6">Parametres de My Server Manager</h1>

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

    <div class="mb-4 border-b border-gray-200">
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

    <?php foreach ($categories as $index => $category):
        $settings = $settingsManager->getAllByCategory($category);
    ?>
    <div class="tab-content <?php echo $index === 0 ? '' : 'hidden'; ?>" data-tab-content="<?php echo $category; ?>">
        <form method="POST" class="space-y-4">
            <?php echo msmCsrfField(); ?>
            <input type="hidden" name="category" value="<?php echo $category; ?>">

            <?php if (empty($settings)): ?>
                <p class="text-gray-500 italic">Aucun parametre enregistre pour cette categorie.</p>
            <?php else: ?>
                <?php foreach ($settings as $key => $value):
                    $type = $settings_schema[$category][$key]['type'] ?? 'text';
                    $label = $settings_schema[$category][$key]['label'] ?? $key;
                ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="<?php echo htmlspecialchars($key); ?>">
                            <?php echo htmlspecialchars($label); ?>
                        </label>

                        <?php if ($type === 'checkbox'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="false">
                            <input type="checkbox" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="true"
                                <?php echo $value === 'true' ? 'checked' : ''; ?>
                                class="mt-1">
                        <?php else: ?>
                            <input type="<?php echo htmlspecialchars($type); ?>" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>"
                                   value="<?php echo htmlspecialchars($value); ?>"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Enregistrer</button>
        </form>
    </div>
    <?php endforeach; ?>
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
