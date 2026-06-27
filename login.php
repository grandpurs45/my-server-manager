<?php
define('MSM_AUTH_PUBLIC', true);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/login.php');
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$baseUrl = ($scriptDirectory === '' || $scriptDirectory === '.') ? '/' : $scriptDirectory . '/';

$next = $_GET['next'] ?? $_POST['next'] ?? ($baseUrl . 'index.php');
$error = null;

if (!$authManager->isInstalled()) {
    $error = 'Les tables d authentification ne sont pas encore installees. Lancez php apply_migrations.php.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    msmRequireValidCsrf();

    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($authManager->login($username, $password)) {
        header('Location: ' . $next);
        exit;
    }

    $error = 'Identifiants invalides ou compte inactif.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - My Server Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link rel="icon" type="image/png" href="<?= $baseUrl ?>assets/favicon.png">
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-6">
    <main class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="flex flex-col items-center mb-8">
                <div class="bg-white rounded-xl p-2 shadow-md border border-gray-100">
                    <img src="<?= $baseUrl ?>assets/logos/logo_msm.png" alt="Logo MSM" class="w-16 h-16">
                </div>
                <h1 class="text-2xl font-bold mt-4">My Server Manager</h1>
            </div>

            <?php if ($error !== null): ?>
                <div class="mb-5 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <?= msmCsrfField() ?>
                <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700">Utilisateur</label>
                    <input id="username" name="username" type="text" autocomplete="username" required autofocus
                           class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700">Mot de passe</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="log-in" class="h-4 w-4"></i>
                    Connexion
                </button>
            </form>
        </div>
    </main>

    <script>
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</body>
</html>
