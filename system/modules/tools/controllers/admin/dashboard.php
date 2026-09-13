<?php

use System\Engine\Controller;

class ToolsAdminDashboard extends Controller
{
    public function indexAction()
    {
        $rawStats = [];
        $modules = get_modules();

        foreach ($modules as $module_path) {
            $module = basename($module_path);

            if (!get_enabled_module($module)) {
                continue;
            }

            $file = BASEDIR . $module_path . '/helpers/dashboard.php';
            if (file_exists($file)) {
                $data = require $file;
                if (is_array($data)) {
                    $rawStats = array_merge($rawStats, $data);
                }
            }
        }

        $stats = [];
        $systemInfo = [];

        foreach ($rawStats as $key => $item) {
            // System info (simple key-value from tools)
            if (!is_array($item)) {
                $systemInfo[$key] = $item;
                continue;
            }

            // ✅ Stats with DB placeholder — handles full WHERE conditions!
            $value = $item['value'] ?? '';
            if (is_string($value) && str_starts_with($value, '__DB_COUNT__')) {
                $sqlPart = trim(substr($value, 12)); // strips prefix, keeps full table + WHERE
                $count = $this->db->query("SELECT COUNT(*) AS total FROM {$sqlPart}")->row['total'] ?? 0;
                $item['value'] = $count;
            }

            $stats[] = $item;
        }

        // Fill extra system info stats
        $systemInfo['logs']    = is_dir(BASEDIR . '/storage/logs') ? count(scandir(BASEDIR . '/storage/logs')) - 2 : 0;
        $systemInfo['blocks']  = $this->db->query("SELECT COUNT(*) AS total FROM #__block")->row['total'] ?? 0;
        $systemInfo['modules'] = $this->db->query("SELECT COUNT(*) AS total FROM #__module WHERE installed = 1")->row['total'] ?? 0;

        // Sort stats by priority
        usort($stats, fn($a, $b) => ($a['priority'] ?? 50) <=> ($b['priority'] ?? 50));

        // ─── Enabled module flags for quick actions ───
        $showNews   = get_enabled_module('news');
        $showShop   = get_enabled_module('shop');
        $showHifdh  = get_enabled_module('hifdh');

        // ─── Recent articles (News module only) ───
        $recentArticles = $showNews ? $this->db->query("
            SELECT n.title, n.status, n.created_at, c.name AS category_name
            FROM #__news n
            LEFT JOIN #__news_categories c ON c.id = n.category_id
            ORDER BY n.created_at DESC
            LIMIT 5
        ")->rows : [];

        // ─── FULL DASHBOARD VIEW ───
        echo $this->view->inline(function ($view) use (
            $stats, $systemInfo,
            $showNews, $showShop, $showHifdh,
            $recentArticles
        ): void {
    ?>
    <div class="container py-4">
        <h2 class="mb-4">System Dashboard</h2>

        <!-- STATS CARDS -->
        <div class="row g-3 mb-4">
            <?php foreach ($stats as $item): ?>
            <div class="col-sm-6 col-md-3 col-xl-2">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="text-muted small mb-1"><?= escape($item['label']) ?></h5>
                        <p class="display-6 fw-bold <?= escape($item['color'] ?? 'text-dark') ?> mb-0">
                            <?= escape($item['value']) ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <!-- RECENT ARTICLES -->
            <?php if ($showNews): ?>
            <div class="col-md-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📰 Recent Articles</h5>
                        <a href="<?= $view->url->to('news/admin/article') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recentArticles)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentArticles as $a): ?>
                            <div class="list-group-item">
                                <h6 class="mb-1"><?= escape($a['title']) ?></h6>
                                <div class="d-flex small text-muted gap-2">
                                    <span><?= escape($a['category_name'] ?? 'General') ?></span>
                                    <span>&bull;</span>
                                    <span><?= escape(date('j M Y', strtotime($a['created_at']))) ?></span>
                                    <span class="ms-auto badge <?= $a['status'] == 1 ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $a['status'] == 1 ? 'Published' : 'Draft' ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="p-3 text-center text-muted">No articles yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- QUICK ACTIONS + SYSTEM INFO -->
            <div class="col-md-<?= $showNews ? '5' : '12' ?>">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">⚡ Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($showNews): ?>
                            <a href="<?= $view->url->to('news/admin/article/create') ?>" class="btn btn-primary text-start">➕ New Article</a>
                            <a href="<?= $view->url->to('news/admin/category/create') ?>" class="btn btn-outline-primary text-start">📁 New Category</a>
                            <hr class="my-2">
                            <a href="<?= $view->url->to('news/admin/article') ?>" class="btn btn-outline-secondary text-start">📋 Manage Articles</a>
                            <?php endif; ?>
                            <?php if ($showShop): ?>
                            <a href="<?= $view->url->to('shop/admin/product') ?>" class="btn btn-outline-secondary text-start">🛍️ Manage Products</a>
                            <?php endif; ?>
                            <?php if ($showHifdh): ?>
                            <a href="<?= $view->url->to('hifdh/admin/student') ?>" class="btn btn-outline-secondary text-start">📖 Hifdh Students</a>
                            <?php endif; ?>
                            <?php if ($showNews || $showShop || $showHifdh): ?>
                            <hr class="my-2">
                            <?php endif; ?>
                            <a href="<?= $view->url->to('tools/admin/setting') ?>" class="btn btn-outline-secondary text-start">⚙️ System Settings</a>
                        </div>
                    </div>
                </div>

                <!-- SYSTEM INFO TABLE -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">ℹ️ System Information</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">PHP Version</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['php_version'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Server</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['server_software'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Database</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['database'] ?? 'MySQL') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Server Time</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['server_time'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Memory Usage</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['memory_usage'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Enabled Modules</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['modules'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Blocks</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['blocks'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th class="ps-3 fw-normal text-muted">Log Files</th>
                                    <td class="text-end pe-3"><?= escape($systemInfo['logs'] ?? '-') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
        }, 'admin');
    }
}