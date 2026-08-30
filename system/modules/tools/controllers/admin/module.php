<?php

use System\Engine\Controller;
use System\Library\Notify;

class ToolsAdminModule extends Controller
{
	private const INSTALL_FORM_ID = 'tools_module_install_form';
	private const UNINSTALL_FORM_ID = 'tools_module_uninstall_form';
	private const UPGRADE_FORM_ID = 'tools_module_upgrade_form';

	public function indexAction(): void
	{
		$this->ensureRegistryTable();

		$modules = $this->listModules();

		echo $this->view->inline(static function ($view) use ($modules): void {
			echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Module Manager</h1><p class="text-muted mb-0">Install, uninstall, and monitor modules discovered in system/modules and modules.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/role') . '">Back to admin</a>';
			echo '</div>';

			echo '<div class="card border-0 shadow-sm">';
			echo '<div class="card-body p-0">';
			echo '<div class="table-responsive">';
			echo '<table class="table table-hover align-middle mb-0">';
			echo '<thead class="table-light"><tr><th>Module</th><th>Type</th><th>Status</th><th>Path</th><th class="text-end">Actions</th></tr></thead><tbody>';

			if (empty($modules)) {
				echo '<tr><td colspan="5" class="text-muted py-4">No modules found.</td></tr>';
			} else {
				foreach ($modules as $module) {
					$name = (string) ($module['module'] ?? '');
					$path = (string) ($module['path'] ?? '');
					$installed = (int) ($module['installed'] ?? 0) === 1;
					$isSystem = (int) ($module['is_system'] ?? 0) === 1;

					echo '<tr>';
					echo '<td><strong>' . $view->e($name) . '</strong></td>';
					echo '<td>' . ($isSystem ? '<span class="badge text-bg-info">system</span>' : '<span class="badge text-bg-light">custom</span>') . '</td>';
					echo '<td>' . ($installed ? '<span class="badge text-bg-success">installed</span>' : '<span class="badge text-bg-secondary">uninstalled</span>') . '</td>';
					echo '<td><small class="text-muted">' . $view->e($path) . '</small></td>';
					echo '<td class="text-end"><div class="d-flex justify-content-end gap-2">';

					if ($installed) {
						if ($module['has_upgrade'] ?? false) {
							echo '<form method="post" action="' . route_url('tools/admin/module/upgrade') . '" class="d-inline-block">';
							echo csrf_field(self::UPGRADE_FORM_ID);
							echo '<input type="hidden" name="module" value="' . $view->e($name) . '">';
							echo '<button type="submit" class="btn btn-outline-primary btn-sm" onclick="return confirm(\'Run upgrade for module ' . $view->e($name) . '?\')">Upgrade</button>';
							echo '</form>';
						}

						$disabled = $isSystem ? ' disabled' : '';
						$title = $isSystem ? ' title="System modules cannot be uninstalled."' : '';
						echo '<form method="post" action="' . route_url('tools/admin/module/uninstall') . '" class="d-inline-block">';
						echo csrf_field(self::UNINSTALL_FORM_ID);
						echo '<input type="hidden" name="module" value="' . $view->e($name) . '">';
						echo '<button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(\'Uninstall module ' . $view->e($name) . '?\')"' . $disabled . $title . '>Uninstall</button>';
						echo '</form>';
					} else {
						echo '<form method="post" action="' . route_url('tools/admin/module/install') . '" class="d-inline-block">';
						echo csrf_field(self::INSTALL_FORM_ID);
						echo '<input type="hidden" name="module" value="' . $view->e($name) . '">';
						echo '<button type="submit" class="btn btn-outline-success btn-sm">Install</button>';
						echo '</form>';
					}

					echo '</div></td>';
					echo '</tr>';
				}
			}

			echo '</tbody></table>';
			echo '</div></div></div>';
		}, 'admin');
	}

	public function installAction(): void
	{
		if (!$this->request->isPost()) {
			redirect_to('tools/admin/module');
		}

		if (!$this->form->checkToken(null, self::INSTALL_FORM_ID)) {
			Notify::error('Invalid CSRF token.');
			redirect_to('tools/admin/module');
		}

		$module = $this->normalizeModuleName((string) $this->request->post('module', 'string', ''));
		
		if (empty($module)) {
			Notify::error('Invalid module name.');
			redirect_to('tools/admin/module');
		}

		$this->ensureRegistryTable();

		$modulePath = get_module_path($module);
		if ($modulePath === null) {
			Notify::error('Module folder not found: ' . $module);
			redirect_to('tools/admin/module');
		}

		if (!is_file($modulePath . DS . 'library' . DS . 'setup.php')) {
			Notify::error('Module cannot be installed, missing library/setup.php: ' . $module);
			redirect_to('tools/admin/module');
		}

		$this->ensureRegistryRecord($module, $modulePath);

		$row = $this->db->first('module', ['module' => $module]);
		if ((int) ($row['installed'] ?? 0) === 1) {
			Notify::notice('Module is already installed: ' . $module);
			redirect_to('tools/admin/module');
		}

		try {
			$this->runLifecycleHook($module, $modulePath, 'install');
			$this->db->update('module', [
				'installed' => 1,
				'installed_at' => date('Y-m-d H:i:s'),
				'uninstalled_at' => null,
				'updated_at' => date('Y-m-d H:i:s'),
			], ['module' => $module]);
			Notify::success('Module installed successfully: ' . $module);
		} catch (\Throwable $e) {
			Notify::error('Install failed for ' . $module . ': ' . $e->getMessage());
		}

		redirect_to('tools/admin/module');
	}

	public function uninstallAction(): void
	{
		if (!$this->request->isPost()) {
			redirect_to('tools/admin/module');
		}

		if (!$this->form->checkToken(null, self::UNINSTALL_FORM_ID)) {
			Notify::error('Invalid CSRF token.');
			redirect_to('tools/admin/module');
		}

		$module = $this->normalizeModuleName((string) $this->request->post('module', 'raw', ''));
		if ($module === '') {
			Notify::error('Invalid module name.');
			redirect_to('tools/admin/module');
		}

		$this->ensureRegistryTable();

		$modulePath = get_module_path($module);
		if ($modulePath === null) {
			Notify::error('Module folder not found: ' . $module);
			redirect_to('tools/admin/module');
		}

		$row = $this->db->first('module', ['module' => $module]);
		if (!$row) {
			Notify::error('Module not found in registry: ' . $module);
			redirect_to('tools/admin/module');
		}

		if ((int) ($row['is_system'] ?? 0) === 1) {
			Notify::warning('System modules cannot be uninstalled: ' . $module);
			redirect_to('tools/admin/module');
		}

		if ((int) ($row['installed'] ?? 0) !== 1) {
			Notify::notice('Module is already uninstalled: ' . $module);
			redirect_to('tools/admin/module');
		}

		try {
			$this->runLifecycleHook($module, $modulePath, 'uninstall');
			$this->db->update('module', [
				'installed' => 0,
				'uninstalled_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			], ['module' => $module]);
			Notify::success('Module uninstalled successfully: ' . $module);
		} catch (\Throwable $e) {
			Notify::error('Uninstall failed for ' . $module . ': ' . $e->getMessage());
		}

		redirect_to('tools/admin/module');
	}

	public function upgradeAction(): void
	{
		if (!$this->request->isPost()) {
			redirect_to('tools/admin/module');
		}

		if (!$this->form->checkToken(null, self::UPGRADE_FORM_ID)) {
			Notify::error('Invalid CSRF token.');
			redirect_to('tools/admin/module');
		}

		$module = $this->normalizeModuleName((string) $this->request->post('module', 'raw', ''));
		if ($module === '') {
			Notify::error('Invalid module name.');
			redirect_to('tools/admin/module');
		}

		$this->ensureRegistryTable();

		$modulePath = get_module_path($module);
		if ($modulePath === null) {
			Notify::error('Module folder not found: ' . $module);
			redirect_to('tools/admin/module');
		}

		if (!is_file($modulePath . DS . 'library' . DS . 'setup.php')) {
			Notify::error('Module cannot be upgraded, missing library/setup.php: ' . $module);
			redirect_to('tools/admin/module');
		}

		$row = $this->db->first('module', ['module' => $module]);
		if ((int) ($row['installed'] ?? 0) !== 1) {
			Notify::notice('Module must be installed before it can be upgraded: ' . $module);
			redirect_to('tools/admin/module');
		}

		try {
			$executed = $this->runLifecycleHook($module, $modulePath, 'upgrade', false);
			if (!$executed) {
				Notify::notice('No upgrade steps defined for module: ' . $module);
				redirect_to('tools/admin/module');
			}

			$this->db->update('module', [
				'upgraded_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			], ['module' => $module]);
			Notify::success('Module upgraded successfully: ' . $module);
		} catch (\Throwable $e) {
			Notify::error('Upgrade failed for ' . $module . ': ' . $e->getMessage());
		}

		redirect_to('tools/admin/module');
	}

	private function listModules(): array
	{
		$modules = [];

		foreach (get_modules() as $module => $path) {
			$row = $this->db->first('module', ['module' => $module]);
			$installed = (int) ($row['installed'] ?? 0) === 1;
			$modules[] = [
				'module' => $module,
				'path' => $path,
				'is_system' => (int) ($row['is_system'] ?? $this->isSystemModulePath($path)) === 1 ? 1 : 0,
				'installed' => $installed ? 1 : 0,
				'has_upgrade' => $installed && $this->hasLifecycleHook($module, $path, 'upgrade'),
			];
		}

		return $modules;
	}

	private function ensureRegistryTable(): void
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `#__module` (
			`module` VARCHAR(120) NOT NULL,
			`path` VARCHAR(255) NOT NULL,
			`is_system` TINYINT(1) NOT NULL DEFAULT 0,
			`installed` TINYINT(1) NOT NULL DEFAULT 1,
			`installed_at` DATETIME DEFAULT NULL,
			`uninstalled_at` DATETIME DEFAULT NULL,
			`upgraded_at` DATETIME DEFAULT NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`module`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$hasColumn = $this->db->query(
			"SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '#__module' AND COLUMN_NAME = 'upgraded_at'"
		)->value;

		if ((int) $hasColumn === 0) {
			$this->db->query('ALTER TABLE #__module ADD COLUMN `upgraded_at` DATETIME DEFAULT NULL AFTER `uninstalled_at`');
		}
	}

	private function ensureRegistryRecord(string $module, string $path): void
	{
		$this->db->query(
			'INSERT INTO #__module (`module`, `path`, `is_system`, `installed`, `created_at`, `updated_at`) VALUES (?, ?, ?, 0, NOW(), NOW())
			 ON DUPLICATE KEY UPDATE `path` = VALUES(`path`), `is_system` = VALUES(`is_system`), `updated_at` = NOW()',
			[$module, $path, $this->isSystemModulePath($path) ? 1 : 0]
		);
	}

	private function isSystemModulePath(string $path): bool
	{
		return str_starts_with(
			str_replace('\\', '/', $path),
			str_replace('\\', '/', BASEDIR . 'system' . DS . 'modules')
		);
	}

	private function normalizeModuleName(string $module): string
	{
		$module = strtolower(trim($module));
		return preg_replace('/[^a-z0-9_-]/', '', $module) ?? '';
	}

	private function hasLifecycleHook(string $module, string $modulePath, string $phase): bool
	{
		$hookFile = null;
		$candidates = [
			$modulePath . DS . 'library' . DS . 'setup.php',
			$modulePath . DS . 'library' . DS . $phase . '.php',
		];

		foreach ($candidates as $candidate) {
			if (is_file($candidate)) {
				$hookFile = $candidate;
				break;
			}
		}

		if ($hookFile === null) {
			return false;
		}

		$hookResult = include $hookFile;

		if (is_object($hookResult) && (method_exists($hookResult, $phase) || method_exists($hookResult, 'run'))) {
			return true;
		}

		if (function_exists($module . '_lifecycle') || function_exists($module . '_' . $phase)) {
			return true;
		}

		$className = $this->studly($module) . 'ModuleInstaller';
		return class_exists($className) && (method_exists($className, $phase) || method_exists($className, 'run'));
	}

	private function runLifecycleHook(string $module, string $modulePath, string $phase, bool $required = true): bool
	{
		$hookFile = null;
		$candidates = [
			$modulePath . DS . 'library' . DS . 'setup.php',
			$modulePath . DS . 'library' . DS . $phase . '.php',
		];

		foreach ($candidates as $candidate) {
			if (is_file($candidate)) {
				$hookFile = $candidate;
				break;
			}
		}

		if ($hookFile === null) {
			return false;
		}

		$hookResult = include $hookFile;

		if (is_object($hookResult)) {
			if (method_exists($hookResult, $phase)) {
				$hookResult->{$phase}($this->registry, $this->db);
				return true;
			}

			if (method_exists($hookResult, 'run')) {
				$hookResult->run($phase, $this->registry, $this->db);
				return true;
			}
		}

		$combinedFunction = $module . '_lifecycle';
		if (function_exists($combinedFunction)) {
			$combinedFunction($phase, $this->registry, $this->db);
			return true;
		}

		$function = $module . '_' . $phase;
		if (function_exists($function)) {
			$function($this->registry, $this->db);
			return true;
		}

		$className = $this->studly($module) . 'ModuleInstaller';
		if (class_exists($className)) {
			$instance = new $className($this->registry, $this->db);
			if (method_exists($instance, $phase)) {
				$instance->{$phase}($this->registry, $this->db);
				return true;
			}

			if (method_exists($instance, 'run')) {
				$instance->run($phase, $this->registry, $this->db);
				return true;
			}
		}

		if (!$required) {
			return false;
		}

		throw new \RuntimeException('Lifecycle hook file exists but no callable hook was found. Expected: returned object with method ' . $phase . '() or run(), function ' . $combinedFunction . '($phase, ...), function ' . $function . '(), or class ' . $className . ' with method ' . $phase . '() or run().');
	}

	private function studly(string $value): string
	{
		$value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
		$value = ucwords(strtolower(trim($value)));
		return str_replace(' ', '', $value);
	}
}