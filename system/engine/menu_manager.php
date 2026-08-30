<?php
namespace System\Engine;

class MenuManager
{
    private Registry $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get all module menus.
     *
     * @return array
     */
    public function getAllMenus(): array
    {
        $menus = [];
        $moduleDirs = get_modules();

        foreach ($moduleDirs as $modulePath) {
            $module = basename($modulePath);
            if (!get_enabled_module($module)) {
                continue; // Skip disabled modules
            }
            
            $menuFile = $modulePath . '/helpers/menu.php';
            if (is_file($menuFile)) {
                $menu = include $menuFile;
                if (is_array($menu) && isset($menu['label'], $menu['children'])) {
                    // Resolve URLs (they are stored as route strings)
                    $this->resolveUrls($menu);
                    $menus[] = $menu;
                }
            }
        }

        return $menus;
    }

    /**
     * Resolve route strings to actual URLs.
     */
    private function resolveUrls(array &$menu): void
    {
        foreach ($menu['children'] as &$child) {
            if (isset($child['url']) && is_string($child['url'])) {
                $child['url'] = $this->registry->get('url')->to($child['url']);
            }
            // If children have sub‑children, recursively resolve
            if (isset($child['children'])) {
                $this->resolveUrls($child);
            }
        }
    }
}