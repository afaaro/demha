<?php

namespace System\Library;

use System\Engine\Registry;
use System\Library\Config;
use System\Library\Request;
use System\Library\Document;
use System\Library\Session;
use RuntimeException;

/**
 * View renderer with theme support, layouts, partials, and asset management.
 */
class View {
    protected Registry $container;
    protected Config $config;
    protected Request $request;
    protected Document $doc;
    protected Session $session;
    protected array $vars = [];

    // Theme properties
    protected string $activeTheme = 'default';
    protected array $themeCache = [];

    public function __construct(Registry $container)
    {
        $this->container = $container;
        $this->config = $container->get('config');
        $this->request = $container->get('request');
        $this->doc = $container->get('doc');
        $this->session = $container->get('session');

        // Load theme from config
        $this->activeTheme = $this->config->get('app.theme', 'default');
        $this->validateTheme($this->activeTheme);
    }

    // -------------------------------------------------------------------------
    // Magic Methods
    // -------------------------------------------------------------------------

    public function __get(string $key)
    {
        return $this->container->get($key);
    }

    // -------------------------------------------------------------------------
    // Theme Management
    // -------------------------------------------------------------------------

    public function setTheme(string $theme): void
    {
        $theme = $this->sanitizeThemeName($theme);
        $this->validateTheme($theme);
        $this->activeTheme = $theme;
    }

    public function getTheme(): string
    {
        return $this->activeTheme;
    }

    protected function validateTheme(string $theme): void
    {
        if (!$this->themeExists($theme)) {
            throw new RuntimeException("Theme '{$theme}' not found.");
        }
    }

    public function themeExists(string $theme): bool
    {
        $theme = $this->sanitizeThemeName($theme);
        return is_dir(BASEDIR . 'themes' . DIRECTORY_SEPARATOR . $theme);
    }

    /**
     * Get the full path to a theme file.
     */
    public function themePath(string $path = ''): string
    {
        return BASEDIR
            . 'themes'
            . DIRECTORY_SEPARATOR
            . $this->activeTheme
            . DIRECTORY_SEPARATOR
            . ltrim($path, '/\\');
    }

    /**
     * Get the URL to a theme asset (with cache-busting version).
     */
    public function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new RuntimeException('Invalid asset path.');
        }
        $fullPath = BASEDIR . 'themes' . DIRECTORY_SEPARATOR . $this->activeTheme . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . $path;

        // Cache-busting with file modification time
        $version = is_file($fullPath) ? filemtime($fullPath) : time();

        // Use base URL from request
        $base = rtrim($this->request->getBaseUrl(), '/');
        return $base . '/themes/' . $this->activeTheme . '/asset/' . $path . '?v=' . $version;
    }

    /**
     * Get the full path to a theme layout file, or null if not found.
     */
    public function themeLayout(string $layout = 'main'): ?string
    {
        $file = $this->themePath($layout . '.php');
        return is_file($file) ? $file : null;
    }

    // -------------------------------------------------------------------------
    // Variable Assignment
    // -------------------------------------------------------------------------

    public function assign(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }

        return $this;
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    // -------------------------------------------------------------------------
    // Partials
    // -------------------------------------------------------------------------

    public function partial(string $file, array $data = []): void
    {
        $file = trim($file, '/\\');
        if (!preg_match('/^[a-zA-Z0-9_\/-]+$/', $file) || str_contains($file, '..')) {
            throw new RuntimeException('Invalid partial path.');
        }

        extract(array_merge($this->vars, $data), EXTR_SKIP);

        $path = $this->themePath('partials/' . $file . '.php');
        if (is_file($path)) {
            require $path;
        } else {
            // Try module-specific partials as fallback
            $modulePath = $this->detectModulePath();
            if ($modulePath) {
                $modulePartial = $modulePath . 'views/partials/' . $file . '.php';
                if (is_file($modulePartial)) {
                    require $modulePartial;
                    return;
                }
            }
            throw new RuntimeException("Partial not found: {$path}");
        }
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Render a controller view with a layout.
     */
    public function render(array $data = [], string $layout = 'main'): string
    {
        [$controllerClass, $action] = $this->detectController();

        $controllerFile = (new \ReflectionClass($controllerClass))->getFileName();
        $moduleRoot = $this->detectModuleRoot($controllerFile);

        $data = array_merge($this->vars, $data);

        // Find and render the view file
        $viewFile = $this->findViewFile($controllerFile, $action);
        $content = $this->renderFile($viewFile, $data);

        // If no layout, return just the content
        if ($layout === '') {
            return $content;
        }

        // Find and render the layout
        $layoutFile = $this->findLayout($moduleRoot, $layout);
        $layoutHtml = $this->renderFile($layoutFile, array_merge($data, [
            'content' => $content,
            'theme'   => $this,
        ]));

        // Apply document processing only after the final HTML layout exists.
        return $this->doc->handleOutput($layoutHtml);
    }

    /**
     * Render an inline callback with a layout.
     */
    public function inline(callable $callback, string $layout = 'main', array $data = []): string
    {
        $data = array_merge($this->vars, $data);
        
        extract($data, EXTR_SKIP);

        ob_start();
        $callback($this);
        $content = ob_get_clean();

        if ($layout === '') {
            return $content;
        }

        // If layout contains a slash, treat it as "module/layout"
        if (str_contains($layout, '/')) {
            [$module, $layoutName] = explode('/', $layout, 2);
            $modulePath = get_module_path($module); // must be defined
            if ($modulePath) {
                $layoutFile = rtrim($modulePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . $layoutName . '.php';
                if (is_file($layoutFile)) {
                    return $this->renderFile($layoutFile, array_merge($data, ['content' => $content]));
                }
            }
        }

        // Find layout file
        $layoutFile = $this->findGlobalLayout($layout);
        $layoutHtml = $this->renderFile($layoutFile, array_merge($data, [
            'content' => $content,
            'theme'   => $this,
        ]));

        // Apply document processing only after the final HTML layout exists.
        return $this->doc->handleOutput($layoutHtml);
    }

    // -------------------------------------------------------------------------
    // Layout & View Resolution
    // -------------------------------------------------------------------------

    protected function findViewFile(string $controllerFile, string $action): string
    {
        $viewDirectory = str_replace(
            DIRECTORY_SEPARATOR . 'controllers',
            DIRECTORY_SEPARATOR . 'views',
            dirname($controllerFile)
        );

        $controller = basename($controllerFile, '.php');

        $viewFile = $viewDirectory
            . DIRECTORY_SEPARATOR
            . $controller
            . DIRECTORY_SEPARATOR
            . $action
            . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View file not found: {$viewFile}");
        }

        return $viewFile;
    }

    protected function findLayout(string $moduleRoot, string $layout = 'main'): string
    {
        // 1. Try module-specific layout
        $moduleLayout = $moduleRoot
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layout'
            . DIRECTORY_SEPARATOR
            . $layout
            . '.php';

        if (is_file($moduleLayout)) {
            return $moduleLayout;
        }

        // 2. Try theme layout
        $themeLayout = $this->themeLayout($layout);
        if ($themeLayout) {
            return $themeLayout;
        }

        // 3. Fallback to global layout
        return $this->findGlobalLayout($layout);
    }

    protected function findGlobalLayout(string $layout = 'main'): string
    {
        // 1. Try theme layout
        $themeLayout = $this->themeLayout($layout);
        if ($themeLayout) {
            return $themeLayout;
        }

        // 2. Fallback to global views/layout/ directory
        $globalLayout = BASEDIR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layout'
            . DIRECTORY_SEPARATOR
            . $layout
            . '.php';

        if (is_file($globalLayout)) {
            return $globalLayout;
        }

        throw new RuntimeException("Layout not found: {$layout}");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function renderFile(string $file, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return ob_get_clean();
    }

    protected function detectController(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        // Find the first caller that's not from this class
        foreach ($trace as $call) {
            if (isset($call['class']) && $call['class'] !== self::class) {
                $class = $call['class'] ?? null;
                $action = preg_replace('/Action$/', '', $call['function'] ?? 'indexAction');
                return [$class, $action];
            }
        }

        throw new RuntimeException('Unable to detect controller class.');
    }

    protected function detectModuleRoot(string $controllerFile): string
    {
        $root = strstr(
            dirname($controllerFile),
            DIRECTORY_SEPARATOR . 'controllers',
            true
        );

        if (!$root) {
            throw new RuntimeException("Unable to detect module root from: {$controllerFile}");
        }

        return $root;
    }

    protected function detectModulePath(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        foreach ($trace as $call) {
            if (isset($call['class']) && $call['class'] !== self::class) {
                $reflection = new \ReflectionClass($call['class']);
                $file = $reflection->getFileName();
                if ($file) {
                    $root = strstr(dirname($file), DIRECTORY_SEPARATOR . 'controllers', true);
                    if ($root) {
                        return $root . DIRECTORY_SEPARATOR;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Check if a view file exists without throwing an exception.
     */
    public function viewExists(string $view): bool
    {
        [$controllerClass] = $this->detectController();
        $controllerFile = (new \ReflectionClass($controllerClass))->getFileName();
        $viewDirectory = str_replace(
            DIRECTORY_SEPARATOR . 'controllers',
            DIRECTORY_SEPARATOR . 'views',
            dirname($controllerFile)
        );
        $controller = basename($controllerFile, '.php');

        return is_file($viewDirectory . DIRECTORY_SEPARATOR . $controller . DIRECTORY_SEPARATOR . $view . '.php');
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }

    private function sanitizeThemeName(string $theme): string
    {
        $theme = preg_replace('/[^a-zA-Z0-9_-]/', '', $theme);
        return $theme !== '' ? $theme : 'default';
    }

    public function e(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'e'], $value);
        }

        if (is_object($value)) {
            // Objects can be cast to arrays or handled via method calls
            return method_exists($value, '__toString') ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '[Object]';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}