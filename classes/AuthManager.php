<?php
namespace MSM;

use PDO;
use PDOException;

class AuthManager
{
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'serveurs' => 'Serveurs',
        'supervision' => 'Supervision',
        'alertes' => 'Alertes',
        'patch_management' => 'Patch management',
        'securite' => 'Securite',
        'diagnostic' => 'Diagnostic',
        'settings' => 'Parametres',
        'metrics' => 'Export Prometheus',
    ];

    public function __construct(
        private PDO $pdo,
        private SettingsManager $settingsManager
    ) {
    }

    public function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $timeoutMinutes = $this->sessionTimeoutMinutes();
        $gcLifetime = $timeoutMinutes === 0
            ? 315360000
            : max(1440, $timeoutMinutes * 60);

        ini_set('session.gc_maxlifetime', (string) $gcLifetime);

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->baseUrl(),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('MSMSESSID');
        session_start();
    }

    public function isInstalled(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM msm_users LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function requireLogin(): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        if ($this->currentUser() !== null) {
            return;
        }

        $next = $_SERVER['REQUEST_URI'] ?? $this->baseUrl();
        header('Location: ' . $this->baseUrl() . 'login.php?next=' . rawurlencode($next));
        exit;
    }

    public function requireModule(string $moduleKey): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        if ($this->userCan($moduleKey)) {
            return;
        }

        http_response_code(403);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Acces refuse</title>';
        echo '<script src="https://cdn.tailwindcss.com"></script></head>';
        echo '<body class="bg-gray-100 min-h-screen flex items-center justify-center">';
        echo '<div class="bg-white rounded-lg shadow p-8 max-w-lg">';
        echo '<h1 class="text-2xl font-bold text-red-700 mb-3">Acces refuse</h1>';
        echo '<p class="text-gray-700 mb-5">Votre compte ne dispose pas du droit necessaire pour acceder a ce module.</p>';
        echo '<a class="inline-flex rounded bg-blue-600 px-4 py-2 text-white" href="' . htmlspecialchars($this->baseUrl(), ENT_QUOTES, 'UTF-8') . 'index.php">Retour au dashboard</a>';
        echo '</div></body></html>';
        exit;
    }

    public function login(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '' || !$this->isInstalled()) {
            $this->logEvent(null, $username, 'login_failed');
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM msm_users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logEvent($user['id'] ?? null, $username, 'login_failed');
            return false;
        }

        $this->ensureSession();
        session_regenerate_id(true);
        $_SESSION['msm_user_id'] = (int) $user['id'];
        $_SESSION['msm_username'] = $user['username'];
        $_SESSION['msm_is_admin'] = (bool) $user['is_admin'];
        $_SESSION['msm_last_activity'] = time();

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateHash = $this->pdo->prepare('UPDATE msm_users SET password_hash = ? WHERE id = ?');
            $updateHash->execute([$newHash, (int) $user['id']]);
        }

        $updateLogin = $this->pdo->prepare('UPDATE msm_users SET last_login_at = NOW() WHERE id = ?');
        $updateLogin->execute([(int) $user['id']]);
        $this->logEvent((int) $user['id'], $username, 'login_success');

        return true;
    }

    public function logout(): void
    {
        $user = $this->currentUser();
        if ($user !== null) {
            $this->logEvent((int) $user['id'], $user['username'], 'logout');
        }

        $this->ensureSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function currentUser(): ?array
    {
        $this->ensureSession();
        $id = $_SESSION['msm_user_id'] ?? null;
        if (!$id) {
            return null;
        }

        if ($this->isSessionExpired()) {
            $this->logEvent((int) $id, $_SESSION['msm_username'] ?? null, 'session_expired');
            $this->clearSessionIdentity();
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM msm_users WHERE id = ? AND is_active = 1');
            $stmt->execute([(int) $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return null;
        }

        if (!$user) {
            $this->clearSessionIdentity();
            return null;
        }

        $_SESSION['msm_last_activity'] = time();

        return $user;
    }

    public function userCan(string $moduleKey, ?int $userId = null): bool
    {
        $user = $userId === null ? $this->currentUser() : $this->findUser($userId);
        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        if ((bool) $user['is_admin']) {
            return true;
        }

        $stmt = $this->pdo->prepare('SELECT can_access FROM msm_user_module_permissions WHERE user_id = ? AND module_key = ?');
        $stmt->execute([(int) $user['id'], $moduleKey]);

        return (bool) $stmt->fetchColumn();
    }

    public function listUsers(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM msm_users ORDER BY username');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM msm_users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function permissionsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT module_key FROM msm_user_module_permissions WHERE user_id = ? AND can_access = 1');
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function createUser(array $data): int
    {
        $username = $this->normalizeUsername((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $displayName = trim((string) ($data['display_name'] ?? ''));
        $isAdmin = !empty($data['is_admin']);
        $isActive = !empty($data['is_active']);

        $errors = $this->validatePassword($password, $username);
        if ($username === '') {
            $errors[] = 'Le nom utilisateur est obligatoire.';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO msm_users (username, display_name, password_hash, is_admin, is_active, password_must_change)
            VALUES (?, ?, ?, ?, ?, 0)
        ');
        $stmt->execute([
            $username,
            $displayName !== '' ? $displayName : null,
            password_hash($password, PASSWORD_DEFAULT),
            $isAdmin ? 1 : 0,
            $isActive ? 1 : 0,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->replacePermissions($userId, $data['permissions'] ?? []);
        $this->logEvent($this->currentUser()['id'] ?? null, $username, 'user_created');

        return $userId;
    }

    public function updateUser(int $userId, array $data): void
    {
        $user = $this->findUser($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur introuvable.');
        }

        $displayName = trim((string) ($data['display_name'] ?? ''));
        $isAdmin = !empty($data['is_admin']);
        $isActive = !empty($data['is_active']);

        if (!$isActive && $this->isCurrentUser($userId)) {
            throw new \InvalidArgumentException('Vous ne pouvez pas desactiver votre propre compte.');
        }

        if (!$isAdmin && $this->isLastActiveAdmin($userId)) {
            throw new \InvalidArgumentException('Impossible de retirer le dernier compte administrateur actif.');
        }

        $stmt = $this->pdo->prepare('UPDATE msm_users SET display_name = ?, is_admin = ?, is_active = ? WHERE id = ?');
        $stmt->execute([
            $displayName !== '' ? $displayName : null,
            $isAdmin ? 1 : 0,
            $isActive ? 1 : 0,
            $userId,
        ]);

        $this->replacePermissions($userId, $data['permissions'] ?? []);
        $this->logEvent($this->currentUser()['id'] ?? null, $user['username'], 'user_updated');
    }

    public function changePassword(int $userId, string $password, bool $mustChange = false): void
    {
        $user = $this->findUser($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Utilisateur introuvable.');
        }

        $errors = $this->validatePassword($password, $user['username']);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        if ($this->isCurrentUser($userId)) {
            $mustChange = false;
        }

        $stmt = $this->pdo->prepare('UPDATE msm_users SET password_hash = ?, password_must_change = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $userId]);
        $this->logEvent($this->currentUser()['id'] ?? null, $user['username'], 'password_changed');
    }

    public function deleteUser(int $userId): void
    {
        $user = $this->findUser($userId);
        if (!$user) {
            return;
        }

        if ($this->isCurrentUser($userId)) {
            throw new \InvalidArgumentException('Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($this->isLastActiveAdmin($userId)) {
            throw new \InvalidArgumentException('Impossible de supprimer le dernier compte administrateur actif.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM msm_users WHERE id = ?');
        $stmt->execute([$userId]);
        $this->logEvent($this->currentUser()['id'] ?? null, $user['username'], 'user_deleted');
    }

    public function validatePassword(string $password, string $username = ''): array
    {
        $errors = [];
        $minLength = max(1, (int) ($this->settingsManager->get('auth', 'password_min_length') ?? 12));

        if (strlen($password) < $minLength) {
            $errors[] = 'Le mot de passe doit contenir au moins ' . $minLength . ' caracteres.';
        }

        if (($this->settingsManager->get('auth', 'password_require_uppercase') ?? 'true') === 'true' && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir une majuscule.';
        }

        if (($this->settingsManager->get('auth', 'password_require_lowercase') ?? 'true') === 'true' && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir une minuscule.';
        }

        if (($this->settingsManager->get('auth', 'password_require_digit') ?? 'true') === 'true' && !preg_match('/\d/', $password)) {
            $errors[] = 'Le mot de passe doit contenir un chiffre.';
        }

        if (($this->settingsManager->get('auth', 'password_require_special') ?? 'true') === 'true' && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir un caractere special.';
        }

        if ($username !== '' && mb_strtolower($password) === mb_strtolower($username)) {
            $errors[] = 'Le mot de passe ne doit pas etre identique au nom utilisateur.';
        }

        return $errors;
    }

    public function generatePassword(?int $length = null): string
    {
        $length = max(12, $length ?? (int) ($this->settingsManager->get('auth', 'password_generator_length') ?? 18));
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-_=';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    public function inferModuleFromRequest(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = basename($script);

        return match ($base) {
            'index.php' => 'dashboard',
            'serveurs.php', 'add-server.php', 'details-cible.php', 'refresh-target.php' => 'serveurs',
            'supervision.php', 'update-status.php' => 'supervision',
            'alerts.php', 'alerts-wall.php', 'alerts-wall-standalone.php', 'alert-rules.php', 'alert-action.php' => 'alertes',
            'patch-management.php' => 'patch_management',
            'securite-serveurs.php', 'securite-web.php', 'details-securite.php' => 'securite',
            'diagnostic.php' => 'diagnostic',
            'settings.php', 'users.php' => 'settings',
            default => 'dashboard',
        };
    }

    private function replacePermissions(int $userId, array $permissions): void
    {
        $delete = $this->pdo->prepare('DELETE FROM msm_user_module_permissions WHERE user_id = ?');
        $delete->execute([$userId]);

        $insert = $this->pdo->prepare('INSERT INTO msm_user_module_permissions (user_id, module_key, can_access) VALUES (?, ?, 1)');
        foreach (array_keys(self::MODULES) as $moduleKey) {
            if (in_array($moduleKey, $permissions, true)) {
                $insert->execute([$userId, $moduleKey]);
            }
        }
    }

    private function isCurrentUser(int $userId): bool
    {
        $current = $this->currentUser();
        return $current !== null && (int) $current['id'] === $userId;
    }

    private function isLastActiveAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM msm_users WHERE is_admin = 1 AND is_active = 1 AND id <> ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn() === 0;
    }

    private function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    private function isSessionExpired(): bool
    {
        $timeoutMinutes = $this->sessionTimeoutMinutes();
        if ($timeoutMinutes === 0) {
            return false;
        }

        $lastActivity = (int) ($_SESSION['msm_last_activity'] ?? time());
        return (time() - $lastActivity) > ($timeoutMinutes * 60);
    }

    private function sessionTimeoutMinutes(): int
    {
        return max(0, (int) ($this->settingsManager->get('auth', 'session_timeout_minutes') ?? 60));
    }

    private function clearSessionIdentity(): void
    {
        unset(
            $_SESSION['msm_user_id'],
            $_SESSION['msm_username'],
            $_SESSION['msm_is_admin'],
            $_SESSION['msm_last_activity']
        );
    }

    private function logEvent(?int $userId, ?string $username, string $eventType): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO msm_auth_events (user_id, username, event_type, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                $username,
                $eventType,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (PDOException) {
            // L'authentification ne doit pas echouer uniquement parce que l'audit log est indisponible.
        }
    }

    private function baseUrl(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if (basename($scriptDirectory) === 'pages') {
            $scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptDirectory)), '/');
        }

        return ($scriptDirectory === '' || $scriptDirectory === '.') ? '/' : $scriptDirectory . '/';
    }
}
