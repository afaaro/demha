<?php

namespace System\Library;

use System\Engine\Registry;

class Block
{
    private Registry $registry;
    private Database $db;
    private Request $request;

    /** @var array<string, array> Static blocks [block_id => config] — NO DATABASE NEEDED */
    private static array $staticBlocks = [];

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->request = $registry->get('request');
    }

    /**
     * ✅ Register a CUSTOM STATIC block (no database required!)
     * Example: Block::register('news_sidebar', [
     *     'title' => 'Latest News',
     *     'region' => 'sidebar',
     *     'body' => '<b>Hardcoded HTML</b> or callable',
     *     'status' => 1
     * ]);
     */
    public static function register(string $id, array $config): void
    {
        // Capture where this register() was called from!
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $callerFile = $backtrace[0]['file'] ?? '';
        $callerLine = $backtrace[0]['line'] ?? 0;

        $config['id'] = $id;
        $config['weight'] = (int) ($config['weight'] ?? 0);
        $config['status'] = (int) ($config['status'] ?? 1);
        $config['body_type'] = 'static';
        
        // Store file/line for UI display
        $config['__file'] = $callerFile;
        $config['__line'] = $callerLine;

        self::$staticBlocks[$id] = $config;
    }

    /**
     * Render ALL blocks: DATABASE blocks + STATIC blocks
     */
    public function render(string $region): string
    {
        $this->ensureTable();

        // 1. Get database blocks
        $dbBlocks = $this->db->query(
            'SELECT * FROM #__block WHERE `region` = ? AND `status` = 1 ORDER BY `weight` ASC, `id` ASC',
            [$region]
        )->rows;

        // 2. Get STATIC blocks for this region
        $staticBlocks = array_filter(self::$staticBlocks, function($b) use ($region) {
            return ($b['region'] ?? '') === $region && ($b['status'] ?? 1) === 1;
        });

        // 3. Merge & sort ALL blocks by weight
        $allBlocks = array_merge($dbBlocks, array_values($staticBlocks));
        usort($allBlocks, fn($a,$b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        if (empty($allBlocks)) {
            return '';
        }

        $route = trim($this->request->getRoute(), '/');
        $module = explode('/', $route)[0] ?? '';
        $html = '';

        foreach ($allBlocks as $block) {
            if ($this->isVisible($block, $route, $module)) {
                $html .= $this->renderBlock($block);
            }
        }
        return $html;
    }

    public function ensureTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `#__block` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(150) NOT NULL DEFAULT '',
            `region` VARCHAR(60) NOT NULL,
            `body_type` ENUM('html','module') NOT NULL DEFAULT 'html',
            `body` TEXT NOT NULL,
            `visibility` ENUM('include','exclude') NOT NULL DEFAULT 'include',
            `paths` TEXT DEFAULT NULL,
            `modules` VARCHAR(255) DEFAULT NULL,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `weight` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_block_region` (`region`),
            KEY `idx_block_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Visibility rules work for BOTH database AND static blocks
     */
    private function isVisible(array $block, string $route, string $module): bool
    {
        $paths = array_filter(array_map('trim', explode("\n", (string) ($block['paths'] ?? ''))));
        $modules = array_filter(array_map('trim', explode(',', (string) ($block['modules'] ?? ''))));

        if (empty($paths) && empty($modules)) {
            return true;
        }

        $matched = false;
        foreach ($paths as $pattern) {
            if ($this->matchesPath($route, $pattern)) {
                $matched = true;
                break;
            }
        }
        if (!$matched && in_array($module, $modules, true)) {
            $matched = true;
        }

        $mode = (string) ($block['visibility'] ?? 'include');
        return $mode === 'exclude' ? !$matched : $matched;
    }

    private function matchesPath(string $route, string $pattern): bool
    {
        $pattern = trim($pattern, '/');
        if ($pattern === '') {
            return false;
        }
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        return preg_match($regex, $route) === 1;
    }

    /**
     * Render block — NOW supports: html / module / ✅ static (string or callable)
     */
    private function renderBlock(array $block): string
    {
        $bodyType = (string) ($block['body_type'] ?? 'html');

        // ✅ STATIC block: can be plain HTML or a PHP callable!
        if ($bodyType === 'static') {
            $body = $block['body'] ?? '';
            // If body is a function, call it!
            if (is_callable($body)) {
                $body = (string) call_user_func($body, $this->registry, $block);
            }
        }
        // Database module block
        elseif ($bodyType === 'module') {
            $body = $this->renderModuleBlock((string) ($block['body'] ?? ''));
        }
        // Database HTML block
        else {
            $body = (string) ($block['body'] ?? '');
        }

        if ($body === '') {
            return '';
        }

        $title = trim((string) ($block['title'] ?? ''));
        $titleHtml = $title !== '' ? '<div class="block-title h6 mb-2 fw-bold">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        $blockId = is_string($block['id'] ?? '') ? preg_replace('/[^a-z0-9_-]/', '', $block['id']) : (int) ($block['id'] ?? 0);

        return '<div class="block block-' . $blockId . ' mb-4 p-3 border rounded bg-white">' . $titleHtml . '<div class="block-body">' . $body . '</div></div>';
    }

    private function renderModuleBlock(string $module): string
    {
        $module = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($module))) ?? '';
        if ($module === '') {
            return '';
        }
        $modulePath = get_module_path($module);
        if ($modulePath === null) {
            return '';
        }
        $hookFile = $modulePath . DS . 'library' . DS . 'block.php';
        if (!is_file($hookFile)) {
            return '';
        }
        $hookResult = include $hookFile;
        if (is_object($hookResult) && method_exists($hookResult, 'render')) {
            return (string) $hookResult->render($this->registry);
        }
        return '';
    }
}