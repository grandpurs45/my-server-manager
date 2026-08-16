<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

use MSM\ApiConnectorRegistry;
use MSM\ApiIntegrationManager;
use MSM\ApiSourceRepository;

$repository = new ApiSourceRepository($pdo);
$manager = new ApiIntegrationManager($pdo);
$registry = new ApiConnectorRegistry();

function msmRedirectApiIntegrations(?int $sourceId = null): never
{
    header('Location: api-integrations.php' . ($sourceId ? '?source_id=' . $sourceId : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    msmRequireValidCsrf('api-integrations.php');
    $action = (string) ($_POST['action'] ?? '');
    $sourceId = max(0, (int) ($_POST['source_id'] ?? 0));
    try {
        if ($action === 'create') {
            $sourceId = $repository->create($_POST);
            $_SESSION['success'] = 'Source API enregistree. Testez maintenant la connexion.';
        } elseif ($action === 'update') {
            $repository->update($sourceId, $_POST);
            $_SESSION['success'] = 'Configuration de la source mise a jour.';
        } elseif ($action === 'test') {
            $result = $manager->test($sourceId);
            if (empty($result['success'])) {
                throw new RuntimeException((string) $result['message']);
            }
            $_SESSION['success'] = (string) $result['message'];
        } elseif ($action === 'discover') {
            $result = $manager->discover($sourceId);
            $_SESSION['success'] = (string) $result['message'];
        } elseif ($action === 'save_metrics') {
            $enabledIds = array_map('intval', array_keys($_POST['metrics'] ?? []));
            $repository->updateMetricSelection($sourceId, $enabledIds, $_POST['intervals'] ?? []);
            $_SESSION['success'] = 'Selection et frequences de collecte enregistrees.';
        } elseif ($action === 'toggle') {
            $repository->setEnabled($sourceId, ($_POST['enabled'] ?? '0') === '1');
            $_SESSION['success'] = ($_POST['enabled'] ?? '0') === '1' ? 'Source activee.' : 'Source desactivee.';
        } elseif ($action === 'delete') {
            $repository->delete($sourceId);
            $_SESSION['success'] = 'Source API, ressources et mesures supprimees.';
            $sourceId = 0;
        } else {
            throw new InvalidArgumentException('Action inconnue.');
        }
    } catch (Throwable $exception) {
        $_SESSION['error'] = $exception->getMessage();
    }
    msmRedirectApiIntegrations($sourceId ?: null);
}

$sources = $repository->listSources();
$sourceId = max(0, (int) ($_GET['source_id'] ?? 0));
$source = $sourceId > 0 ? $repository->find($sourceId) : null;
$resources = $source !== null ? $repository->resourcesForSource($sourceId) : [];
$recentSamples = $source !== null ? $repository->recentSamplesForSource($sourceId) : [];
$edit = isset($_GET['edit']) && $source !== null;
$form = $edit ? $source : [
    'name' => '', 'connector_type' => 'reolink', 'protocol' => 'https', 'hostname' => '',
    'port' => 443, 'verify_tls' => 1, 'timeout_seconds' => 15, 'discovery_interval_minutes' => 60,
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 p-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Integrations API</h1>
            <p class="mt-1 text-sm text-slate-600">Connexion, decouverte et collecte de ressources exposees par des API locales.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="collectors.php" class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                <i data-lucide="workflow" class="h-4 w-4"></i> Etat du collecteur
            </a>
            <a href="api-integrations.php?new=1" class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="plus" class="h-4 w-4"></i> Nouvelle source
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_GET['new']) || $edit): ?>
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold"><?= $edit ? 'Modifier la source' : 'Ajouter une source API' ?></h2>
                    <p class="mt-1 text-sm text-slate-500">Les identifiants sont chiffres avant stockage. Le mot de passe ne sera jamais reaffiche.</p>
                </div>
                <a href="api-integrations.php<?= $edit ? '?source_id=' . $sourceId : '' ?>" class="rounded border border-gray-300 p-2 text-slate-600" title="Fermer"><i data-lucide="x" class="h-4 w-4"></i></a>
            </div>
            <form method="post" class="space-y-5">
                <?= msmCsrfField() ?>
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="source_id" value="<?= $sourceId ?>"><?php endif; ?>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm font-semibold text-slate-700">Nom
                        <input name="name" required maxlength="150" value="<?= htmlspecialchars((string) $form['name']) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="Hub Reolink maison">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Connecteur
                        <select name="connector_type" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            <?php foreach ($registry->all() as $key => $connector): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $form['connector_type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($connector->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Protocole
                        <select name="protocol" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                            <?php foreach (['https' => 'HTTPS', 'http' => 'HTTP'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $form['protocol'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Adresse IP ou DNS
                        <input name="hostname" required value="<?= htmlspecialchars((string) $form['hostname']) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 font-mono" placeholder="192.168.1.20">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Port
                        <input name="port" type="number" min="1" max="65535" required value="<?= (int) $form['port'] ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Utilisateur
                        <input name="username" <?= $edit ? '' : 'required' ?> autocomplete="off" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="<?= $edit ? 'Laisser vide pour conserver' : 'msm-monitor' ?>">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Mot de passe
                        <input name="secret" type="password" <?= $edit ? '' : 'required' ?> autocomplete="new-password" class="mt-1 w-full rounded border border-gray-300 px-3 py-2" placeholder="<?= $edit ? 'Laisser vide pour conserver' : '' ?>">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Timeout (secondes)
                        <input name="timeout_seconds" type="number" min="1" max="60" required value="<?= (int) $form['timeout_seconds'] ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Redecouverte (minutes)
                        <input name="discovery_interval_minutes" type="number" min="5" max="10080" required value="<?= (int) ($form['discovery_interval_minutes'] ?? 60) ?>" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    </label>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="verify_tls" value="1" <?= !empty($form['verify_tls']) ? 'checked' : '' ?> class="rounded border-gray-300">
                    Verifier le certificat TLS
                </label>
                <div class="flex gap-2">
                    <button class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white"><i data-lucide="save" class="h-4 w-4"></i> Enregistrer</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($source !== null): ?>
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-slate-900"><?= htmlspecialchars((string) $source['name']) ?></h2>
                        <span class="rounded px-2 py-1 text-xs font-semibold <?= (int) $source['enabled'] === 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>"><?= (int) $source['enabled'] === 1 ? 'Active' : 'Inactive' ?></span>
                    </div>
                    <div class="mt-1 font-mono text-sm text-slate-500"><?= htmlspecialchars($source['protocol'] . '://' . $source['hostname'] . ':' . $source['port']) ?></div>
                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                        <span>Test : <?= htmlspecialchars((string) ($source['last_test_status'] ?? 'jamais')) ?></span>
                        <span>Decouverte : <?= htmlspecialchars(msmDisplayDate($source['last_discovered_at'] ?? null, '-')) ?></span>
                        <span>Prochaine redecouverte : <?= htmlspecialchars(msmDisplayDate($source['next_discovery_at'] ?? null, 'au prochain passage')) ?></span>
                        <span>Collecte : <?= htmlspecialchars(msmDisplayDate($source['last_collected_at'] ?? null, '-')) ?></span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ([['test', 'plug-zap', 'Tester'], ['discover', 'scan-search', 'Decouvrir']] as [$action, $icon, $label]): ?>
                        <form method="post"><?= msmCsrfField() ?><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="source_id" value="<?= $sourceId ?>"><button class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50"><i data-lucide="<?= $icon ?>" class="h-4 w-4"></i><?= $label ?></button></form>
                    <?php endforeach; ?>
                    <a href="api-integrations.php?source_id=<?= $sourceId ?>&edit=1" class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-blue-700"><i data-lucide="pencil" class="h-4 w-4"></i>Modifier</a>
                    <form method="post"><?= msmCsrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="source_id" value="<?= $sourceId ?>"><input type="hidden" name="enabled" value="<?= (int) $source['enabled'] === 1 ? '0' : '1' ?>"><button class="inline-flex items-center gap-2 rounded px-3 py-2 text-sm font-semibold <?= (int) $source['enabled'] === 1 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700' ?>"><i data-lucide="power" class="h-4 w-4"></i><?= (int) $source['enabled'] === 1 ? 'Desactiver' : 'Activer' ?></button></form>
                </div>
            </div>
            <?php if (!empty($source['last_test_message'])): ?>
                <div class="mt-4 rounded bg-slate-50 px-3 py-2 text-sm text-slate-600"><?= htmlspecialchars((string) $source['last_test_message']) ?></div>
            <?php endif; ?>
        </section>

        <form method="post" class="space-y-4">
            <?= msmCsrfField() ?><input type="hidden" name="action" value="save_metrics"><input type="hidden" name="source_id" value="<?= $sourceId ?>">
            <?php if ($resources === []): ?>
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-slate-500">
                    <i data-lucide="scan-search" class="mx-auto mb-3 h-8 w-8"></i>
                    Lancez une decouverte pour identifier les ressources et metriques disponibles.
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Ressources de l'hote <?= htmlspecialchars((string) $source['name']) ?></h2>
                        <p class="text-sm text-slate-500">Hub, cameras et stockage restent rattaches a cette source API. Les nouvelles ressources sont ajoutees lors de la redecouverte automatique.</p>
                    </div>
                    <button class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white"><i data-lucide="save" class="h-4 w-4"></i>Enregistrer la selection</button>
                </div>
                <?php foreach ($resources as $resource): ?>
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-200 bg-slate-50 px-5 py-3">
                            <div><span class="font-semibold text-slate-900"><?= htmlspecialchars((string) $resource['name']) ?></span><span class="ml-2 rounded bg-white px-2 py-1 text-xs text-slate-500"><?= htmlspecialchars((string) $resource['resource_type']) ?></span></div>
                            <span class="font-mono text-xs text-slate-400"><?= htmlspecialchars((string) $resource['external_id']) ?></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-white text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-5 py-3">Collecter</th><th class="px-5 py-3">Metrique</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Derniere valeur</th><th class="px-5 py-3">Historique</th><th class="px-5 py-3">Frequence</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                <?php foreach ($resource['metrics'] as $metric): ?>
                                    <tr>
                                        <td class="px-5 py-3"><input type="checkbox" name="metrics[<?= (int) $metric['metric_id'] ?>]" value="1" <?= (int) $metric['metric_enabled'] === 1 ? 'checked' : '' ?> class="rounded border-gray-300"></td>
                                        <td class="px-5 py-3"><div class="font-semibold text-slate-800"><?= htmlspecialchars((string) $metric['metric_name']) ?></div><div class="font-mono text-xs text-slate-400"><?= htmlspecialchars((string) $metric['external_key']) ?></div></td>
                                        <td class="px-5 py-3"><span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600"><?= htmlspecialchars((string) $metric['data_type']) ?></span></td>
                                        <td class="px-5 py-3">
                                            <div class="font-mono"><?= htmlspecialchars(is_scalar($metric['last_value']) || $metric['last_value'] === null ? var_export($metric['last_value'], true) : json_encode($metric['last_value'])) ?><?= $metric['unit'] ? ' ' . htmlspecialchars((string) $metric['unit']) : '' ?></div>
                                            <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars(msmDisplayDate($metric['last_collected_at'] ?? null, 'Jamais')) ?></div>
                                        </td>
                                        <td class="px-5 py-3 align-top">
                                            <?php $metricSamples = $recentSamples[(int) $metric['metric_id']] ?? []; ?>
                                            <?php if ($metricSamples === []): ?>
                                                <span class="text-xs italic text-slate-400">Aucun echantillon</span>
                                            <?php else: ?>
                                                <details class="min-w-56">
                                                    <summary class="cursor-pointer text-sm font-semibold text-blue-700"><?= count($metricSamples) ?> derniere(s) valeur(s)</summary>
                                                    <div class="mt-2 overflow-hidden rounded border border-gray-200 bg-white">
                                                        <?php foreach ($metricSamples as $sample): ?>
                                                            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-3 py-2 text-xs last:border-b-0">
                                                                <span class="font-mono"><?= htmlspecialchars(is_scalar($sample['value']) || $sample['value'] === null ? var_export($sample['value'], true) : json_encode($sample['value'])) ?><?= $metric['unit'] ? ' ' . htmlspecialchars((string) $metric['unit']) : '' ?></span>
                                                                <span class="whitespace-nowrap text-slate-500"><?= htmlspecialchars(msmDisplayDate($sample['collected_at'] ?? null, '-')) ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3"><div class="flex items-center gap-2"><input type="number" name="intervals[<?= (int) $metric['metric_id'] ?>]" min="1" max="1440" value="<?= (int) $metric['collection_interval_minutes'] ?>" class="w-24 rounded border border-gray-300 px-2 py-1"><span class="text-xs text-slate-500">min</span></div></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </form>

        <div class="flex justify-end"><form method="post" onsubmit="return confirm('Supprimer cette source et toutes ses donnees ?')"><?= msmCsrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="source_id" value="<?= $sourceId ?>"><button class="inline-flex items-center gap-2 rounded border border-red-200 px-3 py-2 text-sm font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i>Supprimer la source</button></form></div>
    <?php else: ?>
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4"><h2 class="text-lg font-semibold">Sources configurees</h2></div>
            <?php if ($sources === []): ?>
                <div class="p-10 text-center text-slate-500">Aucune source API. Commencez par ajouter votre Home Hub Reolink.</div>
            <?php else: ?>
                <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($sources as $item): ?>
                        <a href="api-integrations.php?source_id=<?= (int) $item['id'] ?>" class="rounded-lg border border-gray-200 p-4 transition hover:border-blue-300 hover:bg-blue-50/40">
                            <div class="flex items-center justify-between gap-3"><span class="font-semibold text-slate-900"><?= htmlspecialchars((string) $item['name']) ?></span><span class="rounded px-2 py-1 text-xs font-semibold <?= (int) $item['enabled'] === 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>"><?= (int) $item['enabled'] === 1 ? 'Active' : 'Inactive' ?></span></div>
                            <div class="mt-1 font-mono text-xs text-slate-500"><?= htmlspecialchars((string) $item['hostname']) ?>:<?= (int) $item['port'] ?></div>
                            <div class="mt-4 flex gap-4 text-xs text-slate-500"><span><?= (int) $item['resource_count'] ?> ressource(s)</span><span><?= (int) $item['enabled_metric_count'] ?> metrique(s) active(s)</span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
