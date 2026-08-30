<?php

namespace System\Library;

use System\Engine\Registry;

/**
 * Renders admin-managed content blocks into named theme regions (sidebars, footer, etc.),
 * with visibility rules based on the current route/module (include or exclude).
 */
class Block
{
    private Registry $registry;
    private Database $db;
    private Request $request;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->request = $registry->get('request');
    }

    /**
     * Render all active, visible blocks assigned to a region.
     */
    public function render(string $region): string
    {
        $this->ensureTable();

        $blocks = $this->db->query(
            'SELECT * FROM #__block WHERE `region` = ? AND `status` = 1 ORDER BY `weight` ASC, `id` ASC',
            [$region]
        )->rows;

        if (empty($blocks)) {
            return '';
        }

        $route = trim($this->request->getRoute(), '/');
        $module = explode('/', $route)[0] ?? '';

        $html = '';
        foreach ($blocks as $block) {
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
     * Decide whether a block should show on the current route.
     * No rules configured = show everywhere. Otherwise "include" shows only on
     * matches, "exclude" hides on matches and shows everywhere else.
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

    private function renderBlock(array $block): string
    {
        $body = (string) ($block['body_type'] ?? 'html') === 'module'
            ? $this->renderModuleBlock((string) $block['body'])
            : (string) $block['body'];

        if ($body === '') {
            return '';
        }

        $title = trim((string) ($block['title'] ?? ''));
        $titleHtml = $title !== '' ? '<div class="block-title h6 mb-2">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>' : '';

        return '<div class="block block-' . (int) $block['id'] . ' mb-3">' . $titleHtml . $body . '</div>';
    }

    /**
     * Module-sourced blocks resolve to a module's library/block.php, which must
     * return an object with a render(Registry) method — mirrors the setup.php lifecycle convention.
     */
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
