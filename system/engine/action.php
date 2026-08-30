<?php
namespace System\Engine;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
// use ReflectionClass;
// use ReflectionMethod;
use RuntimeException;
use System\Engine\Registry;

class Action {
    private array $moduleControllerFiles = [];
    
    public function __construct(readonly Registry $registry) {}
    
    public function execute() {
        $request = $this->registry->get('request');
        $routeString = (string) $request->get('route', 'string', 'user/account');
        $routeString = trim($routeString, '/');

        // http://localhost/demha/user/account/edit, http://localhost/demha/user/admin/account/edit
        // Split route into module and the rest
        $parts = explode('/', $routeString);
        $module = $this->sanitizeModuleName($parts[0] ?? '');

        // Check if the module is enabled
        if (!get_enabled_module($module)) {
            throw new RuntimeException("{$module} module is disabled.");
        }

        $route = $this->resolveRoute($routeString, $module);      
        if (!$route) {
            throw new RuntimeException("No route found for '{$routeString}'.");
        }

        // --- Detect admin area ---
        if (str_contains($route['url'], '/admin/') || str_ends_with($route['url'], '/admin')) {
            defined('ADMIN_PANEL') || define('ADMIN_PANEL', true);
        }

        $this->checkPermissions($route, $routeString);

        if ($this->registry->has('request')) {
            $this->registry->get('request')->setRouteParams($route['params'] ?? []);
            $this->registry->get('request')->setMatchedRoute($route);
        }
        
        // Include the controller file
        if (!is_file($route['file'])) {
            throw new RuntimeException("Controller file not found: {$route['file']}");
        }
        require_once $route['file'];

        // Instantiate the controller
        if (!class_exists($route['class'])) {
            throw new RuntimeException("Controller class not found: {$route['class']}");
        }
        $controller = new $route['class']($this->registry);

        // Execute the action
        $action = $this->sanitizeActionName((string) ($route['action'] ?? 'index'));
        $method = $action . 'Action';
        if (!is_callable([$controller, $method])) {
            throw new RuntimeException("Action '{$method}' not found in " . $route['class']);
        }
        $controller->$method();
    }

    /**
     * Sanitise module name (only alphanumeric and underscore).
     */
    private function sanitizeModuleName(string $input): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $input);
    }

    /**
     * Resolve the route by trying mapped routes, controller file routes, then fallback.
     */
    private function resolveRoute(string $routeString, string $module): ?array
    {
        // Try mapped routes first
        $route = $this->matchMappedRoute($routeString, $module)
            ?? $this->matchControllerRoute($routeString, $module)
            ?? $this->fallbackToHomeController($routeString, $module);

        return $route;
    }
    
    /**
     * Match against custom route maps defined in the module's `library/route.php`.
     */
    private function matchMappedRoute(string $routeString, string $module): ?array
    {
        $modulePath = get_modules($module)[$module] ?? null;
        if (!$modulePath) {
            return null;
        }

        $mapFile = $modulePath . DS . 'library' . DS . 'route.php';
        if (!is_readable($mapFile)) {
            return null;
        }

        $maps = $this->normalizeRouteMaps(include $mapFile);
        // Sort by length descending to match the most specific pattern first
        uksort($maps, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($maps as $pattern => $definition) {
            if (!$this->matchesRoutePrefix($routeString, $pattern)) {
                continue;
            }

            // Determine controller path
            $controllerPath = $definition['controller'] ?? $this->defaultControllerPath($module, $pattern);
            $controllerFile = $this->buildControllerFilePath($modulePath, $controllerPath);

            if (!is_file($controllerFile)) {
                continue;
            }

            // Extract parameters from remaining URI segments
            $paramValues = array_values(
                array_filter(explode('/', trim(substr($routeString, strlen($pattern)), '/')))
            );

            $params = [];
            foreach (($definition['params'] ?? []) as $i => $paramName) {
                if (isset($paramValues[$i])) {
                    $params[$paramName] = $this->sanitizeRouteParam(
                        html_entity_decode($paramValues[$i], ENT_QUOTES, 'UTF-8')
                    );
                }
            }

            return [
                'module'  => $module,
                'url'     => $controllerPath,
                'class'   => $this->controllerClass($controllerPath),
                'file'    => $controllerFile,
                'action'  => $this->sanitizeActionName((string) ($definition['action'] ?? 'index')),
                'params'  => $params,
                'auth'    => (bool) ($definition['auth'] ?? false),
                'guest'   => (bool) ($definition['guest'] ?? false),
                'public'  => (bool) ($definition['public'] ?? false),
                'permission' => $definition['permission'] ?? null,
                'permissions' => isset($definition['permissions']) && is_array($definition['permissions'])
                    ? $definition['permissions']
                    : [],
                'permission_mode' => (string) ($definition['permission_mode'] ?? 'all'),
                'group' => $definition['group'] ?? null,
                'groups' => isset($definition['groups']) && is_array($definition['groups'])
                    ? $definition['groups']
                    : [],
            ];
        }

        return null;
    }

    /**
     * Normalise route maps (support multiple definition formats).
     */
    private function normalizeRouteMaps(array $maps): array
    {
        $normalized = [];
        foreach ($maps as $pattern => $def) {
            $key = trim((string)(is_int($pattern) ? $def : $pattern), '/');

            if (is_string($def)) {
                $normalized[$key] = ['action' => $def, 'params' => []];
            } elseif (array_is_list($def)) {
                $normalized[$key] = ['action' => 'index', 'params' => $def];
            } else {
                $normalized[$key] = [
                    'controller' => $def['controller'] ?? null,
                    'action'     => $def['action'] ?? 'index',
                    'params'     => $def['params'] ?? [],
                    'auth'       => (bool) ($def['auth'] ?? false),
                    'guest'      => (bool) ($def['guest'] ?? false),
                    'public'     => (bool) ($def['public'] ?? false),
                    'permission' => $def['permission'] ?? null,
                    'permissions' => isset($def['permissions']) && is_array($def['permissions'])
                        ? $def['permissions']
                        : [],
                    'permission_mode' => (string) ($def['permission_mode'] ?? 'all'),
                    'group' => $def['group'] ?? null,
                    'groups' => isset($def['groups']) && is_array($def['groups'])
                        ? $def['groups']
                        : [],
                ];
            }
        }
        return $normalized;
    }

    /**
     * Match against a controller file that exists in the module's `controllers/` directory.
     */
    private function matchControllerRoute(string $routeString, string $module): ?array
    {
        $modulePath = get_modules($module)[$module] ?? null;
        if (!$modulePath) {
            return null;
        }

        $allControllers = $this->getModuleControllerFiles($module);
        
        foreach ($allControllers as $file) {
            // Convert file path to controller route (e.g., "news/view" from "news/controllers/view.php")
            $controllerRoute = $this->fileToControllerRoute($file, $module);
            
            if (!$this->matchesRoutePrefix($routeString, $controllerRoute)) {
                continue;
            }

            // Extract action and parameters from remaining URI
            $remaining = trim(substr($routeString, strlen($controllerRoute)), '/');
            $segments = array_values(array_filter(explode('/', $remaining)));

            $action = $this->sanitizeActionName((string) (array_shift($segments) ?? 'index'));
            $params = array_map([$this, 'sanitizeRouteParam'], $segments);

            $fullFilePath = $modulePath . $file;
            return [
                'module'  => $module,
                'url'     => $controllerRoute,
                'class'   => $this->controllerClass($controllerRoute),
                'file'    => $fullFilePath,
                'action'  => $action,
                'params'  => $params,
                'auth'    => false,
                'guest'   => false,
                'public'  => false,
                'permission' => null,
                'permissions' => [],
                'permission_mode' => 'all',
                'group' => null,
                'groups' => [],
            ];
        }

        return null;
    }

    private function fallbackToHomeController(string $routeString, string $module): ?array
    {
        // Implement logic to fallback to the home controller of the module
        // Return an array with controller and method if found, otherwise return null
        return null;
    }

    /**
     * Build the absolute file path for a controller given the module path and controller route.
     */
    private function buildControllerFilePath(string $modulePath, string $controllerRoute): string
    {
        $relative = substr($controllerRoute, strpos($controllerRoute, '/') + 1);
        return $modulePath . DS . 'controllers' . DS . str_replace('/', DS, $relative) . '.php';
    }

    /**
     * Convert a controller file path (relative to module) to its route representation.
     */
    private function fileToControllerRoute(string $file, string $module): string
    {
        $relative = str_replace('controllers' . DS, '', $file);
        $withoutExt = preg_replace('/\.php$/', '', $relative);
        return $module . '/' . $withoutExt;
    }

    /**
     * Get all controller files for a module (cached).
     */
    private function getModuleControllerFiles(string $module): array
    {
        if (isset($this->moduleControllerFiles[$module])) {
            return $this->moduleControllerFiles[$module];
        }

        $modulePath = get_modules($module)[$module] ?? null;
        if (!$modulePath) {
            return [];
        }

        $controllerDir = $modulePath . DS . 'controllers' . DS;
        if (!is_dir($controllerDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = str_replace($modulePath, '', $file->getPathname());
                $files[] = $relative;
            }
        }

        $this->moduleControllerFiles[$module] = $files;
        return $files;
    }

    /**
     * Build the fully qualified controller class name.
     */
    private function controllerClass(string $controllerPath): string
    {
        return str_replace(['_', '-', '/'], '', ucwords($controllerPath, '_-/'));
    }

    /**
     * Check if the given URI matches the prefix (exact or with a trailing slash).
     */
    private function matchesRoutePrefix(string $uri, string $prefix): bool
    {
        $uri = trim($uri, '/');
        $prefix = trim($prefix, '/');
        return $uri === $prefix || str_starts_with($uri, $prefix . '/');
    }

    /**
     * Determine the default controller path for a mapped route if not explicitly given.
     */
    private function defaultControllerPath(string $module, string $pattern): string
    {
        return $this->isAdminRoute($pattern)
            ? $module . '/admin/home'
            : $module . '/home';
    }

    /**
     * Sanitise action name for method dispatch.
     */
    private function sanitizeActionName(string $input): string
    {
        $input = str_replace('-', '_', $input);
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $input);
        return $clean !== '' ? $clean : 'index';
    }

    /**
     * Sanitise route parameter values.
     */
    private function sanitizeRouteParam(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-.~]/', '', $value);
    }

    /**
     * Check if a route pattern belongs to the admin section.
     */
    private function isAdminRoute(string $pattern): bool
    {
        return str_contains($pattern, '/admin/') || str_ends_with($pattern, '/admin');
    }

    private function checkPermissions(array $route, string $routeString): void
    {
        if ((bool) ($route['public'] ?? false)) {
            return;
        }

        $requiresAuth = (bool) ($route['auth'] ?? false);
        $guestOnly = (bool) ($route['guest'] ?? false);
        $permission = $route['permission'] ?? null;
        if ($permission === null && !$guestOnly) {
            $permission = $this->deriveDefaultPermission($route);
        }
        $permissions = isset($route['permissions']) && is_array($route['permissions']) ? $route['permissions'] : [];
        $permissionMode = strtolower((string) ($route['permission_mode'] ?? 'all'));
        $group = $route['group'] ?? null;
        $groups = isset($route['groups']) && is_array($route['groups']) ? $route['groups'] : [];

        $hasConstraints = $requiresAuth || $guestOnly || $permission !== null || !empty($permissions) || $group !== null || !empty($groups);
        if (!$hasConstraints) {
            return;
        }

        if (!$this->registry->has('auth')) {
            throw new RuntimeException('Auth service is required for protected routes.');
        }

        $auth = $this->registry->get('auth');

        if ($guestOnly && $auth->check()) {
            redirect_to((string) (registry('config')->get('auth.post_login_route', 'user/account')));
        }

        if (($requiresAuth || $permission !== null || !empty($permissions) || $group !== null || !empty($groups)) && !$auth->check()) {
            if ($routeString !== '' && $routeString !== 'user/login' && $routeString !== 'user/register') {
                $next = $routeString;
            } else {
                $next = 'user/account';
            }
            redirect_to((string) registry('config')->get('auth.login_route', 'user/login'), ['next' => $next]);
        }

        if ($permission !== null && !$auth->can((string) $permission)) {
            $this->denyAccess();
        }

        if (!empty($permissions)) {
            $allowed = false;

            if ($permissionMode === 'any') {
                $allowed = $auth->canAny($permissions);
            } else {
                $allowed = true;
                foreach ($permissions as $permissionName) {
                    if (!$auth->can((string) $permissionName)) {
                        $allowed = false;
                        break;
                    }
                }
            }

            if (!$allowed) {
                $this->denyAccess();
            }
        }

        if ($group !== null && !$auth->hasGroup($group)) {
            $this->denyAccess();
        }

        if (!empty($groups)) {
            $groupAllowed = false;
            foreach ($groups as $groupName) {
                if ($auth->hasGroup($groupName)) {
                    $groupAllowed = true;
                    break;
                }
            }

            if (!$groupAllowed) {
                $this->denyAccess();
            }
        }
    }

    private function deriveDefaultPermission(array $route): ?string
    {
        $module = (string) ($route['module'] ?? '');
        $routeUrl = (string) ($route['url'] ?? '');
        $action = $this->sanitizeActionName((string) ($route['action'] ?? 'index'));

        $segments = [];
        if ($module !== '') {
            $segments[] = strtolower($this->sanitizePermissionSegment($module));
        }

        $parts = array_values(array_filter(explode('/', trim($routeUrl, '/')), static fn($part) => $part !== ''));
        if (!empty($parts)) {
            if (isset($parts[0]) && strtolower($parts[0]) === $module) {
                array_shift($parts);
            }
            foreach ($parts as $part) {
                $segments[] = strtolower($this->sanitizePermissionSegment((string) $part));
            }
        }

        if ($action !== '') {
            $segments[] = strtolower($this->sanitizePermissionSegment($action));
        }

        $permission = implode('.', array_values(array_filter($segments, static fn($segment) => $segment !== '')));
        return $permission !== '' ? $permission : null;
    }

    private function sanitizePermissionSegment(string $segment): string
    {
        $segment = str_replace(['-', '_'], '.', $segment);
        return preg_replace('/[^a-z0-9.]/', '', strtolower($segment)) ?? '';
    }

    private function denyAccess(): never
    {
        http_response_code(403);

        if ($this->registry->has('request') && $this->registry->get('request')->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Forbidden'], JSON_UNESCAPED_SLASHES);
            exit;
        }

        echo '403 Forbidden';
        exit;
    }

    public function get_module_files() {
        static $cache = [];

        $cacheKey = 'module_files';
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $module_files = [];
        $modules = get_modules();

        foreach ($modules as $module) {
            $modulePath = $module;

            if (!is_dir($modulePath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($modulePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                // Safe relative path generation
                $realPath = $file->getPathname();
                $relative = str_replace('\\', '/', $realPath);
                $module_files[] = $relative;
            }
        }

        sort($module_files);
        $cache[$cacheKey] = $module_files;
        return $module_files;
    }

    // get all controller classes from all modules and return methods of each class
    public function get_all_controller_methods() {
        $controller_methods = [];
        $module_files = $this->get_module_files();

        foreach ($module_files as $file) {
            if (str_contains($file, '/controllers/')) {
                require_once $file;

                // Derive class name from file path
                $className = $this->deriveClassNameFromFile($file);
                if (!class_exists($className)) {
                    continue;
                }

                $methods = get_class_methods($className);
                if ($methods === false) {
                    continue;
                }

                // Filter methods to only include those ending with 'Action'
                $actionMethods = array_filter($methods, fn($method) => str_ends_with($method, 'Action'));
                if (!empty($actionMethods)) {
                    $controller_methods[$className] = array_values($actionMethods);
                }
            }
        }

        return $controller_methods;
    }

    protected function deriveClassNameFromFile(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $segments = array_values(array_filter(explode('/', $normalized), static fn($segment) => $segment !== ''));

        $controllersIndex = array_search('controllers', $segments, true);
        if ($controllersIndex === false || $controllersIndex < 1) {
            return '';
        }

        $module = $segments[$controllersIndex - 1] ?? '';
        $relative = implode('/', array_slice($segments, $controllersIndex + 1));
        $relative = preg_replace('/\.php$/', '', $relative);
        $controllerPath = trim($module . '/' . $relative, '/');

        return str_replace(['_', '-', '/'], '', ucwords($controllerPath, '_-/'));
    }

    // public function scanRoutes(): array {
    //     $routes = [];
    //     $possibleModuleBasePaths = [
    //         BASEDIR . 'system/modules/',
    //         BASEDIR . 'modules/',
    //     ];

    //     foreach ($possibleModuleBasePaths as $baseDir) {
    //         if (!is_dir($baseDir)) {
    //             continue;
    //         }

    //         foreach (glob($baseDir . '*', GLOB_ONLYDIR) as $moduleDir) {
    //             $module = basename($moduleDir);

    //             // if (!$this->registry->get('db')->count('modules', ['slug' => $module])) {
    //             //     continue;
    //             // }

    //             $controllersDir = $moduleDir . '/controllers';

    //             if (!is_dir($controllersDir)) {
    //                 continue;
    //             }

    //             $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersDir, RecursiveDirectoryIterator::SKIP_DOTS));
    //             foreach ($rii as $file) {
    //                 if ($file->isDir() || $file->getExtension() !== 'php') {
    //                     continue;
    //                 }

    //                 $classesBefore = get_declared_classes();
    //                 require_once $file->getPathname();
    //                 $classesAfter = get_declared_classes();
    //                 $newClasses = array_diff($classesAfter, $classesBefore);

    //                 foreach ($newClasses as $className) {
    //                     $reflection = new ReflectionClass($className);

    //                     foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    //                         if (!str_ends_with($method->name, 'Action')) {
    //                             continue;
    //                         }

    //                         $action = strtolower(substr($method->name, 0, -6));
    //                         $controllerPart = preg_replace('/Controller$/', '', $reflection->getShortName());

    //                         if (stripos($controllerPart, ucfirst($module)) === 0) {
    //                             $controllerPart = substr($controllerPart, strlen($module));
    //                         }

    //                         $segments = preg_split('/(?=[A-Z])/', $controllerPart, -1, PREG_SPLIT_NO_EMPTY);
    //                         $segments = array_map('strtolower', $segments);

    //                         $route = $module . '/' . implode('/', array_values(array_filter($segments))) . '/' . $action;
    //                         $routes[] = preg_replace('#/+#', '/', strtolower($route));
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     return array_values(array_unique($routes));
    // }
}