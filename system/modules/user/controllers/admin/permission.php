<?php

use System\Engine\Controller;
use System\Library\Notify;

class UserAdminPermission extends Controller {
    public function indexAction()
    {
        $groupId = (int) $this->request->get('id', 'int', 0);
        echo $this->view->inline(function($view) use ($groupId) {

            $allGroups = $this->auth->allGroups();
            $groupMap = [];
            foreach ($allGroups as $groupRow) {
                $gid = (int) ($groupRow['id'] ?? 0);
                if ($gid > 0) {
                    $groupMap[$gid] = $groupRow;
                }
            }
            $group = $groupMap[$groupId] ?? null;

            if (!$group) {
                Notify::error("Group not found.");
                redirect_to('user/admin/role');
                return;
            }

            // Save handler processing
            if ($this->request->isPost()) {
                $postGroupId = $this->request->post('group_id', 'int', 0);
                $flatPermissions = $this->request->post('permissions', false, []);
                $normalized = array_values(array_unique(array_filter($flatPermissions)));

                $staticGroups = method_exists($this->auth, 'getStaticGroups') ? $this->auth->getStaticGroups() : [];
                if (isset($staticGroups[$postGroupId]) || $postGroupId < 0) {
                    if (isset($staticGroups[$postGroupId])) {
                        $this->config->set("static_groups.{$postGroupId}.permissions", $normalized);
                    }
                } else {
                    $this->db->update('user_group', [
                        'permissions' => json_encode($normalized, JSON_UNESCAPED_SLASHES)
                    ], ['id' => $postGroupId]);
                }

                Notify::success("Permissions updated successfully.");
                redirect_to($this->url->cleanRequest('', ['id'], false));
                return;
            }

            $currentPermissions = $group['permissions'] ?? [];
            if (is_string($currentPermissions)) {
                $decoded = json_decode($currentPermissions, true);
                $currentPermissions = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($currentPermissions)) {
                $currentPermissions = [];
            }

            // Fetch structured permissions dynamically via the scanner
            $allPermissionsStructured = $this->availablePermissions();
            
            $allPermissions = [];
            foreach ($allPermissionsStructured as $module => $controllers) {
                foreach ($controllers as $controller => $perms) {
                    foreach ($perms as $permKey => $label) {
                        $allPermissions[$module][] = $permKey;
                    }
                }
            }

            // Group selector + search bar layout
            echo "<div class='d-flex flex-wrap align-items-end gap-3 mb-3'>";

            echo "<div>";
            echo "<label for='group-select' class='form-label me-2'><strong>Select Group:</strong></label>";
            echo "<select id='group-select' class='form-select w-auto d-inline-block' onchange='location = this.value'>";
            foreach ($allGroups as $g) {
                $gid = (int) ($g['id'] ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                $selected = ($groupId === $gid) ? "selected" : "";
                echo "<option value='" . htmlspecialchars($this->url->to('user/admin/permission', ['id' => $gid])) . "' {$selected}>" . escape($g['name']) . "</option>";
            }
            echo "</select>";
            echo "</div>";

            echo "<div class='flex-grow-1'>";
            echo "<input type='text' id='perm-search' class='form-control' placeholder='Search permissions...'>";
            echo "</div>";
            echo "</div>";

            $this->doc->addInlineJs("
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.select-all-module').forEach(toggle => {
                        toggle.addEventListener('change', function () {
                            const targetClass = 'module-' + this.dataset.target;
                            const checkboxes = document.querySelectorAll('.' + targetClass + ' input[type=\"checkbox\"]');
                            checkboxes.forEach(cb => cb.checked = this.checked);
                        });
                    });

                    const permSearch = document.getElementById('perm-search');
                    if (permSearch) {
                        permSearch.addEventListener('input', function () {
                            const term = this.value.toLowerCase();
                            document.querySelectorAll('#perm-table .perm-row').forEach(row => {
                                const perm = (row.dataset.perm || '').toLowerCase();
                                row.style.display = perm.includes(term) ? '' : 'none';
                            });
                        });
                    }
                });
            ");

            $columnsPerRow = 2;

            echo opentable('Edit Permissions for Group: ' . escape($group['name'] ?? ''));
            echo '<div class="table-responsive">';
            echo '<form method="post" action="" id="perm-table">';
            echo '<input type="hidden" name="group_id" value="' . (int)$groupId . '">';
            echo '<table class="table table-bordered align-middle" style="table-layout:fixed;">';

            foreach ($allPermissions as $plugin => $permList) {
                $safePluginId = htmlspecialchars(preg_replace('/[^a-z0-9]/i', '_', $plugin));
                $collapseId = 'collapse_' . $safePluginId;

                echo '<thead class="table-light">';
                echo '<tr><th colspan="' . $columnsPerRow . '">';
                echo '<div class="d-flex justify-content-between align-items-center">';
                echo '<button class="btn btn-link text-start text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="true" aria-controls="' . $collapseId . '">';
                echo '<strong>' . htmlspecialchars(strtoupper($plugin)) . '</strong>';
                echo '</button>';
                echo '<div class="form-check form-switch m-0">';
                echo '<input class="form-check-input select-all-module" type="checkbox" id="selectAll_' . $safePluginId . '" data-target="' . $safePluginId . '">';
                echo '<label class="form-check-label small ms-2" for="selectAll_' . $safePluginId . '">Select All</label>';
                echo '</div></div></th></tr></thead>';

                echo '<tbody id="' . $collapseId . '" class="collapse show"><tr>';
                $count = 0;
                foreach ($permList as $perm) {
                    $checked = in_array($perm, $currentPermissions, true) ? 'checked' : '';
                    $inputId = 'perm_' . md5($plugin . $perm);
                    echo '<td class="perm-row module-' . $safePluginId . '" data-perm="' . htmlspecialchars($perm) . '">';
                    echo '<div class="form-check form-switch">';
                    echo '<input class="form-check-input" type="checkbox" role="switch" id="' . $inputId . '" name="permissions[]" value="' . htmlspecialchars($perm) . '" ' . $checked . '>';
                    echo '<label class="form-check-label small text-break" for="' . $inputId . '">' . htmlspecialchars($perm) . '</label>';
                    echo '</div></td>';

                    $count++;
                    if ($count % $columnsPerRow === 0) {
                        echo '</tr><tr>';
                    }
                }

                if ($count % $columnsPerRow !== 0) {
                    for ($i = 0; $i < ($columnsPerRow - ($count % $columnsPerRow)); $i++) {
                        echo '<td></td>';
                    }
                }

                echo '</tr></tbody>';
            }
            echo '</table>';
            echo '</div>';

            echo closetable('<div class="d-flex gap-3">
                <button type="submit" class="btn btn-primary">Save Permissions</button>
                <a href="' . $this->url->to('user/admin/role') . '" class="btn btn-secondary text-white">Back</a>
            </div>');
            echo '</form>';
        }, 'admin');
    }
    
    // ==============================
    // Scan all controller actions to build available permissions
    // ==============================
    protected function availablePermissions(): array
    {
        $permissions = [];
        $controllerFiles = [];

        if (method_exists($this, 'get_module_controller_files')) {
            $controllerFiles = $this->get_module_controller_files();
        }

        if (empty($controllerFiles)) {
            $roots = [
                BASEDIR . 'modules' . DIRECTORY_SEPARATOR,
                BASEDIR . 'system' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR,
            ];
            foreach ($roots as $root) {
                if (!is_dir($root)) continue;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $path = $file->getPathname();
                        if (str_contains($path, DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR)) {
                            $controllerFiles[] = str_replace(BASEDIR, '', $path);
                        }
                    }
                }
            }
        }

        if (empty($controllerFiles)) {
            return [];
        }

        foreach ($controllerFiles as $file) {
            $file = str_replace('\\', '/', $file);
            $clean = str_replace(['system/modules/', 'modules/', 'controllers/', '.php'], '', $file);
            $clean = trim($clean, '/');

            if (empty($clean)) continue;

            $fullPath = BASEDIR . $file;
            if (!is_file($fullPath)) continue;

            // Ensure the module content exists in DB before scanning actions
            $parts = explode('/', $clean);
            $pluginName = $parts[0] ?? '';

            // Skip scanning if the module is not enabled
            if (!get_enabled_module($pluginName)) {
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
                $label = ucwords(str_replace(['.', '_', '-'], ' ', $permission));

                $permParts = explode('_', $permission);
                $moduleName = $permParts[0] ?? 'other';
                $controller = implode('.', array_slice($permParts, 1, -1));

                $permissions[$moduleName][$controller][$permission] = $label;
            }
        }

        ksort($permissions);
        foreach ($permissions as &$controllers) {
            ksort($controllers);
            foreach ($controllers as &$items) {
                ksort($items);
            }
        }

        return $permissions;
    }


    function get_module_controller_files(): array
    {
        static $controllers = null;

        if ($controllers !== null) {
            return $controllers;
        }

        $base = BASEDIR;
        $baseLen = strlen($base);

        $files = [];

        foreach (get_modules() as $module) {
            $controllerPath = $base
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $module)
                . DIRECTORY_SEPARATOR
                . 'controllers';

            if (!is_dir($controllerPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $controllerPath,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), $baseLen);
                $relative = str_replace('\\', '/', $relative);

                $files[] = $relative;
            }
        }

        sort($files);

        return $controllers = $files;
    }
}