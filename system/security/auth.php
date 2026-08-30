<?php

namespace System\Security;

use RuntimeException;
use System\Library\Database;
use System\Library\Session;

class Auth
{
    private Database $db;
    private Session $session;
    private array $config;

    private ?array $user = null;
    private array $permissions = [];
    private array $groups = [];
    private bool $loaded = false;

    public function __construct(Database $db, Session $session, array $config = [])
    {
        $this->db = $db;
        $this->session = $session;
        $this->config = $config;

        if (!empty($this->config['auto_install'])) {
            $this->install();
        }
    }

    public function install(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `#__users` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            user_groups JSON DEFAULT NULL,
            permissions JSON DEFAULT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            email_verified_at DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            last_login_ip VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email),
            KEY idx_users_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `#__user_group` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            label VARCHAR(150) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            permissions JSON DEFAULT NULL,
            `system` TINYINT(1) NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_group_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->seedDefaults();
    }

    public function attempt(string $identity, string $password, ?string $ipAddress = null): bool
    {
        $identity = trim($identity);
        if ($identity === '' || $password === '') {
            return false;
        }

        $user = $this->db->query(
            "SELECT * FROM #__users WHERE status = 1 AND (username = ? OR email = ?) LIMIT 1",
            [$identity, $identity]
        )->row;

        if (!$user) {
            return false;
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return false;
        }

        $userId = (int) $user['id'];
        $this->session->regenerate(true);
        $this->session->set($this->sessionKey(), $userId);

        $this->db->query(
            "UPDATE #__users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$ipAddress, $userId]
        );

        $this->loaded = false;
        $this->load();

        return true;
    }

    public function register(array $payload): int
    {
        $username = trim((string) ($payload['username'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if (strlen($username) < 3) {
            throw new RuntimeException('Username must be at least 3 characters.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        if ($this->db->exists('users', ['username' => $username])) {
            throw new RuntimeException('Username is already taken.');
        }

        if ($this->db->exists('users', ['email' => $email])) {
            throw new RuntimeException('Email is already in use.');
        }

        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));

        $defaultGroupId = $this->groupIdByName((string) ($this->config['default_group'] ?? 'member'));
        $defaultGroupIds = $defaultGroupId > 0 ? [$defaultGroupId] : [];

        $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'user_groups' => json_encode($defaultGroupIds, JSON_UNESCAPED_SLASHES),
            'permissions' => json_encode([], JSON_UNESCAPED_SLASHES),
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    public function logout(): void
    {
        $this->session->delete($this->sessionKey());
        $this->session->regenerate(true);
        $this->user = null;
        $this->groups = [];
        $this->permissions = [];
        $this->loaded = false;
    }

    public function check(): bool
    {
        $this->load();
        return $this->user !== null;
    }

    public function id(): int
    {
        $this->load();
        return (int) ($this->user['id'] ?? 0);
    }

    public function user(): ?array
    {
        $this->load();
        return $this->user;
    }

    public function data(string $key, mixed $default = null): mixed
    {
        $this->load();
        return $this->user[$key] ?? $default;
    }
    
    public function groups(): array
    {
        $this->load();
        return $this->groups;
    }

    public function permissions(): array
    {
        $this->load();
        return $this->permissions;
    }

    public function hasGroup(int|string $group): bool
    {
        $this->load();

        foreach ($this->groups as $item) {
            if (is_int($group) && (int) ($item['id'] ?? 0) === $group) {
                return true;
            }

            if (is_string($group) && (string) ($item['name'] ?? '') === $group) {
                return true;
            }
        }

        return false;
    }

    public function can(string $permission): bool
    {
        $this->load();

        if (!$this->user) {
            return false;
        }

        if ($this->hasGroup((string) ($this->config['super_group'] ?? 'super_admin'))) {
            return true;
        }

        $required = $this->normalizePermission($permission);
        if ($required === '') {
            return false;
        }

        foreach ($this->permissions as $granted) {
            if ($this->matchesPermission($required, (string) $granted)) {
                return true;
            }
        }

        return false;
    }

    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can((string) $permission)) {
                return true;
            }
        }

        return false;
    }

    public function csrfToken(): string
    {
        $token = (string) $this->session->get('auth.csrf', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('auth.csrf', $token);
        }

        return $token;
    }

    public function verifyCsrf(?string $token): bool
    {
        $stored = (string) $this->session->get('auth.csrf', '');
        if ($stored === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public function allUsers(): array
    {
        return $this->db->query(
            "SELECT id, username, email, first_name, last_name, status, created_at FROM `#__users` ORDER BY id DESC"
        )->rows;
    }

    public function allGroups(): array
    {
        try {
            return $this->db->query("SELECT * FROM `#__user_group` ORDER BY `system` DESC, label ASC")->rows;
        } catch (\Throwable $e) {
            return $this->db->query("SELECT * FROM `groups` ORDER BY `system` DESC, label ASC")->rows;
        }
    }

    public function getGroups(): array
    {
        return $this->allGroups();
    }

    public function getStaticGroups(): array
    {
        return [];
    }

    public function allPermissions(): array
    {
        $permissions = [];
        $seen = [];

        foreach ($this->scanAvailablePermissions() as $name) {
            $name = $this->normalizePermission((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $permissions[] = [
                'id' => count($permissions) + 1,
                'name' => $name,
                'label' => ucwords(str_replace(['.', '_', '-'], ' ', $name)),
                'module' => explode('.', $name)[0] ?? '',
                'system' => 1,
                'status' => 1,
            ];
        }

        $rows = $this->db->query("SELECT id, permissions FROM `#__user_group` WHERE permissions IS NOT NULL AND permissions != ''")->rows;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            foreach ($this->decodePermissionList($row['permissions'] ?? null) as $name) {
                $name = $this->normalizePermission($name);
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $permissions[] = [
                    'id' => $id,
                    'name' => $name,
                    'label' => ucwords(str_replace(['.', '_', '-'], ' ', $name)),
                    'module' => explode('.', $name)[0] ?? '',
                    'system' => 1,
                    'status' => 1,
                ];
            }
        }

        $defaults = [
            ['name' => 'user.account.view', 'label' => 'View Account', 'module' => 'user', 'system' => 1, 'status' => 1],
            ['name' => 'user.account.edit', 'label' => 'Edit Account', 'module' => 'user', 'system' => 1, 'status' => 1],
            ['name' => 'user.auth.logout', 'label' => 'Logout', 'module' => 'user', 'system' => 1, 'status' => 1],
            ['name' => 'user.admin.account.manage', 'label' => 'Manage Accounts', 'module' => 'user', 'system' => 1, 'status' => 1],
        ];

        foreach ($defaults as $default) {
            $name = $this->normalizePermission((string) ($default['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $permissions[] = array_merge($default, ['id' => count($permissions) + 1]);
        }

        return $permissions;
    }

    private function scanAvailablePermissions(): array
    {
        $permissions = [];
        $controllerFiles = [];
        $roots = [
            BASEDIR . 'modules' . DIRECTORY_SEPARATOR,
            BASEDIR . 'system' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR,
        ];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                if (str_contains($path, DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR)) {
                    $controllerFiles[] = str_replace('\\', '/', str_replace(BASEDIR, '', $path));
                }
            }
        }

        $controllerFiles = array_values(array_unique($controllerFiles));
        foreach ($controllerFiles as $file) {
            $path = str_replace('\\', '/', $file);
            $clean = str_replace(['system/modules/', 'modules/', 'controllers/', '.php'], '', $path);
            $clean = trim($clean, '/');

            if ($clean === '') {
                continue;
            }

            $fullPath = BASEDIR . $path;
            if (!is_file($fullPath)) {
                continue;
            }

            require_once $fullPath;

            $className = str_replace(['_', '-', '/'], '', ucwords($clean, '_-/'));
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!str_ends_with($method->name, 'Action')) {
                    continue;
                }

                $action = strtolower(substr($method->name, 0, -6));
                $permission = str_replace('/', '_', $clean) . '_' . $action;
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique($permissions));
    }

    public function groupById(int $groupId): ?array
    {
        try {
            $row = $this->db->query("SELECT * FROM `#__user_group` WHERE id = ? LIMIT 1", [$groupId])->row;
        } catch (\Throwable $e) {
            $row = $this->db->query("SELECT * FROM `groups` WHERE id = ? LIMIT 1", [$groupId])->row;
        }

        return $row ?: null;
    }

    public function permissionById(int $permissionId): ?array
    {
        return null;
    }

    public function createGroup(string $name, string $label, string $description = ''): int
    {
        $name = $this->cleanSlug($name);
        $label = trim($label);

        if ($name === '' || $label === '') {
            throw new RuntimeException('Group name and label are required.');
        }

        if ($this->groupIdByName($name) > 0) {
            throw new RuntimeException('Group already exists.');
        }

        $this->db->insert('user_group', [
            'name' => $name,
            'label' => $label,
            'description' => trim($description) !== '' ? trim($description) : null,
            'permissions' => json_encode([], JSON_UNESCAPED_SLASHES),
            'system' => 0,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    public function updateGroup(int $groupId, string $label, string $description = '', int $status = 1): void
    {
        $group = $this->groupById($groupId);
        if (!$group) {
            throw new RuntimeException('Group not found.');
        }

        $this->db->update('user_group', [
            'label' => trim($label),
            'description' => trim($description) !== '' ? trim($description) : null,
            'status' => $status ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $groupId]);
    }

    public function deleteGroup(int $groupId): void
    {
        $group = $this->groupById($groupId);
        if (!$group) {
            return;
        }

        if ((int) ($group['system'] ?? 0) === 1) {
            throw new RuntimeException('System groups cannot be deleted.');
        }

        $this->db->delete('user_group', ['id' => $groupId]);
    }

    public function createPermission(string $name, string $label, string $module = '', string $description = ''): int
    {
        return 0;
    }

    public function updatePermission(int $permissionId, string $label, string $module = '', string $description = '', int $status = 1): void
    {
    }

    public function deletePermission(int $permissionId): void
    {
    }

    public function syncGroupPermissions(int $groupId, array $permissionNames): void
    {
        $this->db->update('user_group', [
            'permissions' => json_encode($this->normalizePermissionList($permissionNames), JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $groupId]);

        $this->loaded = false;
    }

    public function syncUserGroups(int $userId, array $groupIds): void
    {
        $groupIds = array_values(array_unique(array_map('intval', array_filter($groupIds, 'is_numeric'))));
        $this->db->update('users', [
            'user_groups' => json_encode($groupIds, JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);

        $this->loaded = false;
    }

    public function syncUserDirectPermissions(int $userId, array $allowedPermissionNames): void
    {
        $this->db->update('users', [
            'permissions' => json_encode($this->normalizePermissionList($allowedPermissionNames), JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);

        $this->loaded = false;
    }

    public function permissionNamesForGroup(int $groupId): array
    {
        $group = $this->groupById($groupId);
        if (!$group) {
            return [];
        }

        return $this->decodePermissionList($group['permissions'] ?? null);
    }

    public function assignmentsForUser(int $userId): array
    {
        $user = $this->db->query("SELECT user_groups, permissions FROM `#__users` WHERE id = ? LIMIT 1", [$userId])->row;
        $groupIds = $this->decodeGroupIds($user['user_groups'] ?? null);
        $groups = [];

        foreach ($groupIds as $groupId) {
            $group = $this->groupById($groupId);
            if ($group) {
                $groups[] = ['id' => (int) $group['id'], 'name' => (string) $group['name'], 'label' => (string) $group['label']];
            }
        }

        return [
            'groups' => $groups,
            'permissions' => $this->decodePermissionList($user['permissions'] ?? null),
        ];
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $this->user = null;
        $this->groups = [];
        $this->permissions = [];

        $userId = (int) $this->session->get($this->sessionKey(), 0);
        if ($userId <= 0) {
            return;
        }

        $user = $this->db->query(
            "SELECT * FROM #__users WHERE id = ? AND status = 1 LIMIT 1",
            [$userId]
        )->row;

        if (!$user) {
            $this->session->delete($this->sessionKey());
            return;
        }

        $this->user = $user;
        $groupIds = $this->decodeGroupIds($user['user_groups'] ?? null);

        foreach ($groupIds as $groupId) {
            $group = $this->groupById($groupId);
            if (!$group) {
                continue;
            }

            if ((int) ($group['status'] ?? 0) !== 1) {
                continue;
            }

            $this->groups[] = $group;
        }

        $permissions = [];
        foreach ($this->groups as $group) {
            foreach ($this->decodePermissionList($group['permissions'] ?? null) as $permission) {
                $permission = $this->normalizePermission($permission);
                if ($permission !== '') {
                    $permissions[$permission] = true;
                }
            }
        }

        foreach ($this->decodePermissionList($user['permissions'] ?? null) as $permission) {
            $permission = $this->normalizePermission($permission);
            if ($permission !== '') {
                $permissions[$permission] = true;
            }
        }

        $this->permissions = array_keys($permissions);
        sort($this->permissions);
    }

    private function seedDefaults(): void
    {
        $groups = [
            ['name' => 'member', 'label' => 'Member', 'permissions' => ['user.account.view'], 'system' => 1],
            ['name' => 'admin', 'label' => 'Administrator', 'permissions' => ['user.account.view', 'user.account.edit', 'user.admin.account.manage'], 'system' => 1],
            ['name' => 'super_admin', 'label' => 'Super Administrator', 'permissions' => ['*'], 'system' => 1],
        ];

        foreach ($groups as $group) {
            $this->db->query(
                "INSERT IGNORE INTO `#__user_group` (`name`, `label`, `description`, `permissions`, `system`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, 1, ?)",
                [
                    $group['name'],
                    $group['label'],
                    $group['label'] . ' system group',
                    json_encode($this->normalizePermissionList($group['permissions'] ?? []), JSON_UNESCAPED_SLASHES),
                    (int) $group['system'],
                    date('Y-m-d H:i:s'),
                ]
            );
        }

        $seed = (array) ($this->config['seed_super_admin'] ?? []);
        $seedUsername = trim((string) ($seed['username'] ?? ''));
        $seedEmail = trim((string) ($seed['email'] ?? ''));
        $seedPassword = (string) ($seed['password'] ?? '');

        if ($seedUsername !== '' && $seedEmail !== '' && strlen($seedPassword) >= 8) {
            $existing = $this->db->query(
                "SELECT id FROM #__users WHERE username = ? OR email = ? LIMIT 1",
                [$seedUsername, $seedEmail]
            )->row;

            if (!$existing) {
                $superGroupId = $this->groupIdByName((string) ($this->config['super_group'] ?? 'super_admin'));
                $this->db->insert('users', [
                    'username' => $seedUsername,
                    'email' => $seedEmail,
                    'password_hash' => password_hash($seedPassword, PASSWORD_DEFAULT),
                    'user_groups' => $superGroupId > 0 ? json_encode([$superGroupId], JSON_UNESCAPED_SLASHES) : '[]',
                    'first_name' => (string) ($seed['first_name'] ?? 'System'),
                    'last_name' => (string) ($seed['last_name'] ?? 'Admin'),
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function groupIdByName(string $groupName): int
    {
        try {
            $row = $this->db->query(
                "SELECT id FROM `#__user_group` WHERE name = ? LIMIT 1",
                [$groupName]
            )->row;
        } catch (\Throwable $e) {
            $row = $this->db->query(
                "SELECT id FROM `groups` WHERE name = ? LIMIT 1",
                [$groupName]
            )->row;
        }

        return (int) ($row['id'] ?? 0);
    }

    private function sessionKey(): string
    {
        return (string) ($this->config['session_key'] ?? 'auth.user_id');
    }

    private function normalizePermission(string $permission): string
    {
        $permission = strtolower(trim($permission));
        $permission = str_replace(['\\', '/'], '.', $permission);
        $permission = preg_replace('/[^a-z0-9._*-]/', '', $permission) ?? '';
        return trim($permission, '.');
    }

    private function normalizePermissionList(array $permissions): array
    {
        $result = [];
        foreach ($permissions as $permission) {
            $normalized = $this->normalizePermission((string) $permission);
            if ($normalized !== '') {
                $result[] = $normalized;
            }
        }

        return array_values(array_unique($result));
    }

    private function decodePermissionList(mixed $value): array
    {
        if (is_array($value)) {
            return $this->normalizePermissionList($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->normalizePermissionList($decoded);
                }
            }

            return $this->normalizePermissionList(preg_split('/[.,;]+/', $trimmed) ?: []);
        }

        return [];
    }

    private function decodeGroupIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('intval', $value), static fn($id) => $id > 0)));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->decodeGroupIds($decoded);
                }
            }

            $parts = preg_split('/[.,;]+/', $trimmed) ?: [];
            $ids = [];
            foreach ($parts as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }

            return array_values(array_unique($ids));
        }

        return [];
    }

    private function matchesPermission(string $required, string $granted): bool
    {
        $required = $this->normalizePermission($required);
        $granted = $this->normalizePermission($granted);

        if ($granted === '' || $required === '') {
            return false;
        }

        if ($granted === '*' || $required === '*') {
            return true;
        }

        if ($granted === $required) {
            return true;
        }

        if (str_contains($granted, '*')) {
            $pattern = str_replace('.', '\\.', $granted);
            $pattern = str_replace('*', '.*', $pattern);
            return preg_match('/^' . $pattern . '$/', $required) === 1;
        }

        return false;
    }

    private function cleanSlug(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
    }
}
