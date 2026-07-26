<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\NotificationManager;
use MSM\NotificationRepository;
use MSM\SettingsManager;

$repository = new NotificationRepository($pdo);
$settingsManager = new SettingsManager($pdo);
$manager = new NotificationManager($repository, $settingsManager);
$allowedTypes = ['webhook', 'discord'];
$allowedSeverities = ['critical', 'warning', 'info'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    msmRequireValidCsrf('notifications.php');
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_settings') {
            $publicBaseUrl = rtrim(trim((string) ($_POST['public_base_url'] ?? '')), '/');
            if ($publicBaseUrl !== '' && filter_var($publicBaseUrl, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException('URL publique MSM invalide.');
            }

            $settingsManager->set('notifications', 'public_base_url', $publicBaseUrl);
            $settingsManager->set(
                'notifications',
                'max_attempts',
                (string) max(1, min(10, (int) ($_POST['max_attempts'] ?? 3)))
            );
            $_SESSION['success'] = 'Parametres de notification mis a jour.';
        } elseif ($action === 'save_channel') {
            $channelId = (int) ($_POST['channel_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $type = (string) ($_POST['channel_type'] ?? '');
            $severity = (string) ($_POST['minimum_severity'] ?? 'warning');
            $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
            $notifyOnOpen = isset($_POST['notify_on_open']);
            $notifyOnResolve = isset($_POST['notify_on_resolve']);

            if ($name === '') {
                throw new InvalidArgumentException('Le nom du canal est obligatoire.');
            }
            if (!in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException('Type de canal invalide.');
            }
            if (!in_array($severity, $allowedSeverities, true)) {
                throw new InvalidArgumentException('Severite minimale invalide.');
            }
            if (!$notifyOnOpen && !$notifyOnResolve) {
                throw new InvalidArgumentException('Selectionnez au moins un evenement a notifier.');
            }
            if ($channelId === 0 && $endpoint === '') {
                throw new InvalidArgumentException('L URL du webhook est obligatoire.');
            }
            if ($endpoint !== '') {
                if (filter_var($endpoint, FILTER_VALIDATE_URL) === false
                    || !in_array(strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)), ['http', 'https'], true)
                ) {
                    throw new InvalidArgumentException('L URL du webhook doit utiliser HTTP ou HTTPS.');
                }
                $endpoint = encrypt($endpoint);
            }

            $savedId = $repository->saveChannel($channelId > 0 ? $channelId : null, [
                'name' => $name,
                'channel_type' => $type,
                'endpoint_encrypted' => $endpoint,
                'enabled' => isset($_POST['enabled']) ? 1 : 0,
                'minimum_severity' => $severity,
                'notify_on_open' => $notifyOnOpen ? 1 : 0,
                'notify_on_resolve' => $notifyOnResolve ? 1 : 0,
            ]);
            $_SESSION['success'] = 'Canal de notification enregistre.';

            if (isset($_POST['test_after_save'])) {
                $channel = $repository->findChannel($savedId);
                if ($channel !== null) {
                    $manager->testChannel($channel);
                    $_SESSION['success'] .= ' Notification de test envoyee.';
                }
            }
        } elseif ($action === 'test_channel') {
            $channel = $repository->findChannel((int) ($_POST['channel_id'] ?? 0));
            if ($channel === null) {
                throw new InvalidArgumentException('Canal de notification introuvable.');
            }

            $result = $manager->testChannel($channel);
            $_SESSION['success'] = 'Notification de test envoyee (HTTP '
                . (int) ($result['status_code'] ?? 0) . ').';
        } elseif ($action === 'delete_channel') {
            if (!$repository->deleteChannel((int) ($_POST['channel_id'] ?? 0))) {
                throw new InvalidArgumentException('Canal de notification introuvable.');
            }
            $_SESSION['success'] = 'Canal de notification supprime.';
        }
    } catch (Throwable $exception) {
        $_SESSION['error'] = $exception->getMessage();
    }

    header('Location: notifications.php');
    exit;
}

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editedChannel = $editId > 0 ? $repository->findChannel($editId) : null;
$channels = $repository->getChannels();
$deliveries = $repository->getRecentDeliveries();
$publicBaseUrl = (string) ($settingsManager->get('notifications', 'public_base_url') ?? '');
$maxAttempts = max(1, (int) ($settingsManager->get('notifications', 'max_attempts') ?? 3));

function msmNotificationStatusClass(string $status): string
{
    return match ($status) {
        'sent' => 'bg-green-100 text-green-700',
        'failed' => 'bg-red-100 text-red-700',
        default => 'bg-amber-100 text-amber-800',
    };
}

function msmNotificationEventLabel(string $eventType): string
{
    return match ($eventType) {
        'opened' => 'Ouverture',
        'resolved' => 'Resolution',
        default => ucfirst($eventType),
    };
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Notifications</h1>
            <p class="mt-1 text-sm text-slate-600">
                Envoi des ouvertures et resolutions d'alertes vers Discord ou un webhook HTTP.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="alerts.php" class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                <i data-lucide="bell" class="h-4 w-4"></i>
                Alertes
            </a>
            <button type="button" data-open-channel-modal class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Nouveau canal
            </button>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="mb-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Configuration globale</h2>
            <p class="mt-1 text-sm text-slate-600">L'URL publique est ajoutee aux messages pour ouvrir directement la vue des alertes.</p>
        </div>
        <form method="post" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(280px,1fr)_180px_auto] lg:items-end">
            <?= msmCsrfField() ?>
            <input type="hidden" name="action" value="save_settings">
            <label class="text-sm font-semibold text-slate-700">
                URL publique MSM
                <input type="url" name="public_base_url" value="<?= htmlspecialchars($publicBaseUrl) ?>"
                       placeholder="https://msm.example.lan"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Tentatives maximales
                <input type="number" name="max_attempts" min="1" max="10" value="<?= $maxAttempts ?>"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            </label>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                Enregistrer
            </button>
        </form>
    </section>

    <section class="mb-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Canaux</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Canal</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Etat</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Severite minimale</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Evenements</th>
                        <th class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($channels === []): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                Aucun canal configure. Les alertes restent disponibles dans MSM.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($channels as $channel): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($channel['name']) ?></div>
                                <div class="text-xs text-slate-500"><?= $channel['channel_type'] === 'discord' ? 'Discord' : 'Webhook JSON' ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-1 text-xs font-semibold <?= !empty($channel['enabled']) ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>">
                                    <?= !empty($channel['enabled']) ? 'Actif' : 'Desactive' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700"><?= ucfirst(htmlspecialchars($channel['minimum_severity'])) ?></td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                <?= !empty($channel['notify_on_open']) ? 'Ouverture' : '' ?>
                                <?= !empty($channel['notify_on_open']) && !empty($channel['notify_on_resolve']) ? ', ' : '' ?>
                                <?= !empty($channel['notify_on_resolve']) ? 'Resolution' : '' ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <form method="post">
                                        <?= msmCsrfField() ?>
                                        <input type="hidden" name="action" value="test_channel">
                                        <input type="hidden" name="channel_id" value="<?= (int) $channel['id'] ?>">
                                        <button type="submit" title="Tester le canal" class="rounded border border-gray-300 p-2 text-slate-700 hover:bg-gray-50">
                                            <i data-lucide="send" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                    <a href="?edit=<?= (int) $channel['id'] ?>" title="Modifier le canal" class="rounded border border-gray-300 p-2 text-blue-700 hover:bg-blue-50">
                                        <i data-lucide="pencil" class="h-4 w-4"></i>
                                    </a>
                                    <form method="post" onsubmit="return confirm('Supprimer ce canal et son historique d envoi ?');">
                                        <?= msmCsrfField() ?>
                                        <input type="hidden" name="action" value="delete_channel">
                                        <input type="hidden" name="channel_id" value="<?= (int) $channel['id'] ?>">
                                        <button type="submit" title="Supprimer le canal" class="rounded border border-red-200 p-2 text-red-700 hover:bg-red-50">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Derniers envois</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Canal</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Evenement</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Alerte</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Etat</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($deliveries === []): ?>
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Aucun envoi enregistre.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600"><?= htmlspecialchars(msmDisplayDate($delivery['last_attempt_at'] ?: $delivery['created_at'])) ?></td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-800"><?= htmlspecialchars($delivery['channel_name']) ?></td>
                            <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars(msmNotificationEventLabel($delivery['event_type'])) ?></td>
                            <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($delivery['title']) ?></td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-1 text-xs font-semibold <?= msmNotificationStatusClass($delivery['status']) ?>">
                                    <?= htmlspecialchars($delivery['status']) ?>
                                </span>
                            </td>
                            <td class="max-w-md px-4 py-3 text-xs text-slate-600">
                                <?php if (!empty($delivery['error_message'])): ?>
                                    <?= htmlspecialchars($delivery['error_message']) ?>
                                <?php else: ?>
                                    HTTP <?= (int) ($delivery['response_code'] ?? 0) ?>, tentative <?= (int) $delivery['attempt_count'] ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="channel-modal" class="<?= $editedChannel === null ? 'hidden ' : '' ?>fixed inset-0 z-50 bg-slate-950/50 p-4">
    <div class="mx-auto mt-10 max-w-2xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900"><?= $editedChannel ? 'Modifier le canal' : 'Nouveau canal' ?></h2>
            <button type="button" data-close-channel-modal title="Fermer" class="rounded p-2 text-slate-500 hover:bg-slate-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <form method="post" class="space-y-4 p-5">
            <?= msmCsrfField() ?>
            <input type="hidden" name="action" value="save_channel">
            <input type="hidden" name="channel_id" value="<?= (int) ($editedChannel['id'] ?? 0) ?>">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">
                    Nom
                    <input type="text" name="name" required value="<?= htmlspecialchars((string) ($editedChannel['name'] ?? '')) ?>"
                           placeholder="Discord exploitation"
                           class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                </label>
                <label class="text-sm font-semibold text-slate-700">
                    Type
                    <select name="channel_type" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        <option value="discord" <?= ($editedChannel['channel_type'] ?? '') === 'discord' ? 'selected' : '' ?>>Discord</option>
                        <option value="webhook" <?= ($editedChannel['channel_type'] ?? '') === 'webhook' ? 'selected' : '' ?>>Webhook JSON generique</option>
                    </select>
                </label>
            </div>
            <label class="block text-sm font-semibold text-slate-700">
                URL du webhook
                <input type="url" name="endpoint" <?= $editedChannel === null ? 'required' : '' ?>
                       placeholder="<?= $editedChannel ? 'Laisser vide pour conserver l URL actuelle' : 'https://...' ?>"
                       autocomplete="off"
                       class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                <span class="mt-1 block text-xs font-normal text-slate-500">L'URL est chiffree en base et n'est jamais reaffichee.</span>
            </label>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">
                    Severite minimale
                    <select name="minimum_severity" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                        <?php foreach (['critical' => 'Critical uniquement', 'warning' => 'Warning et Critical', 'info' => 'Toutes'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($editedChannel['minimum_severity'] ?? 'warning') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="space-y-2 pt-1 text-sm text-slate-700">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="enabled" value="1" <?= $editedChannel === null || !empty($editedChannel['enabled']) ? 'checked' : '' ?>>
                        Canal actif
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="notify_on_open" value="1" <?= $editedChannel === null || !empty($editedChannel['notify_on_open']) ? 'checked' : '' ?>>
                        Notifier les ouvertures
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="notify_on_resolve" value="1" <?= $editedChannel === null || !empty($editedChannel['notify_on_resolve']) ? 'checked' : '' ?>>
                        Notifier les resolutions
                    </label>
                </div>
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 pt-4">
                <button type="button" data-close-channel-modal class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">Annuler</button>
                <button type="submit" name="test_after_save" value="1" class="rounded border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">Enregistrer et tester</button>
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    const channelModal = document.getElementById('channel-modal');
    document.querySelectorAll('[data-open-channel-modal]').forEach((button) => {
        button.addEventListener('click', () => channelModal.classList.remove('hidden'));
    });
    document.querySelectorAll('[data-close-channel-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            channelModal.classList.add('hidden');
            if (new URLSearchParams(window.location.search).has('edit')) {
                window.location.href = 'notifications.php';
            }
        });
    });
    channelModal.addEventListener('click', (event) => {
        if (event.target === channelModal) {
            channelModal.classList.add('hidden');
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
