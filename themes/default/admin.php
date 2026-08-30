<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111827">
    <title>Demha Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= escape($this->view->asset('admin.css')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <?= $this->doc->renderCss() ?>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.js"></script>
</head>
<body>

<?php
// Build dynamic menu from all modules
$modules = $this->menuManager->getAllMenus();

// Get current route for active state
$currentRoute = $this->request->getRoute();
$currentUrl = $this->request->get('route', 'raw', '');
$basePath = trim((string) $this->request->getBasePath(), '/');

/**
 * Normalize a route or URL so it can be compared reliably.
 */
function normalizeMenuRoute($value, string $basePath = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^https?://[^/]+#i', '', $value);

    if (str_contains($value, '?')) {
        parse_str(parse_url($value, PHP_URL_QUERY) ?? '', $queryParams);
        if (!empty($queryParams['route'])) {
            $value = (string) $queryParams['route'];
        }
    }

    $path = parse_url($value, PHP_URL_PATH) ?: $value;
    $path = preg_replace('#^/+#', '', $path);
    $path = trim($path, '/');

    if ($basePath !== '') {
        $basePrefix = $basePath . '/';
        if (str_starts_with($path, $basePrefix)) {
            $path = substr($path, strlen($basePrefix));
        } elseif ($path === $basePath) {
            $path = '';
        }
    }

    if (str_ends_with($path, '/index.php')) {
        $path = preg_replace('#/index\.php$#', '', $path);
    }

    return trim($path, '/');
}

/**
 * Helper: Check if a menu item is active
 */
function isMenuItemActive($item, $currentRoute, $currentUrl, string $basePath = ''): bool
{
    if (empty($item['url'])) return false;

    $itemRoute = normalizeMenuRoute($item['url'], $basePath);
    $routeA = normalizeMenuRoute($currentRoute, $basePath);
    $routeB = normalizeMenuRoute($currentUrl, $basePath);

    // Exact match
    if ($itemRoute === $routeA || $itemRoute === $routeB) {
        return true;
    }

    // Partial match for nested routes like shop/admin/product/edit
    if (str_starts_with($routeA, $itemRoute . '/') || str_starts_with($routeB, $itemRoute . '/')) {
        return true;
    }

    return false;
}

/**
 * Helper: Check if any child in a menu group is active
 */
function isMenuGroupActive($children, $currentRoute, $currentUrl, string $basePath = ''): bool
{
    foreach ($children as $child) {
        if (isMenuItemActive($child, $currentRoute, $currentUrl, $basePath)) {
            return true;
        }
        // Recursively check nested children
        if (!empty($child['children']) && isMenuGroupActive($child['children'], $currentRoute, $currentUrl, $basePath)) {
            return true;
        }
    }
    return false;
}

/**
 * Helper: Get all visible children (recursive, flattened for navbar)
 */
function getVisibleChildren($items): array
{
    $result = [];
    foreach ($items as $item) {
        if (!empty($item['hidden'])) continue;
        if (!empty($item['children'])) {
            $result = array_merge($result, getVisibleChildren($item['children']));
        } else {
            $result[] = $item;
        }
    }
    return $result;
}
?>

<!-- ============================================ -->
<!-- NAVBAR                                        -->
<!-- ============================================ -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background: linear-gradient(120deg, #111827 0%, #1d4ed8 100%);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= escape(route_url('user/admin/dashboard')) ?>">
            <i class="bi bi-cpu me-2"></i>Demha Admin
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($modules as $module): ?>
                    <?php
                    $visibleChildren = array_filter($module['children'] ?? [], fn($c) => empty($c['hidden']));
                    if (empty($visibleChildren)) continue;

                    $isGroupActive = isMenuGroupActive($visibleChildren, $currentRoute, $currentUrl, $basePath);
                    ?>
                    <li class="nav-item dropdown <?= $isGroupActive ? 'active' : '' ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown-<?= md5($module['label']) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($module['icon'])): ?>
                                <i class="<?= escape($module['icon']) ?>"></i>
                            <?php endif; ?>
                            <?= escape($module['label']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdown-<?= md5($module['label']) ?>">
                            <?php foreach ($visibleChildren as $child): ?>
                                <?php if (!empty($child['url'])): ?>
                                    <?php
                                    $isActive = isMenuItemActive($child, $currentRoute, $currentUrl, $basePath);
                                    $target = !empty($child['target']) ? 'target="' . escape($child['target']) . '"' : '';
                                    ?>
                                    <li>
                                        <a class="dropdown-item <?= $isActive ? 'active' : '' ?>" 
                                           href="<?= escape($child['url']) ?>" 
                                           <?= $target ?>>
                                            <?php if (!empty($child['icon'])): ?>
                                                <i class="<?= escape($child['icon']) ?> me-1"></i>
                                            <?php endif; ?>
                                            <?= escape($child['label']) ?>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-primary ms-1">•</span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-item disabled fw-bold"><?= escape($child['label']) ?></span></li>
                                    <?php if (!empty($child['children'])): ?>
                                        <?php foreach ($child['children'] as $subChild): ?>
                                            <?php if (!empty($subChild['hidden']) || empty($subChild['url'])) continue; ?>
                                            <li>
                                                <a class="dropdown-item <?= isMenuItemActive($subChild, $currentRoute, $currentUrl, $basePath) ? 'active' : '' ?>" 
                                                   href="<?= escape($subChild['url']) ?>">
                                                    <?= escape($subChild['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($this->auth->check()): ?>
                    <span class="navbar-text text-light me-2">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= escape($this->auth->data('username')) ?>
                    </span>
                    <a class="btn btn-outline-light btn-sm" href="<?= escape(route_url('user/admin/profile')) ?>" title="Profile">
                        <i class="bi bi-gear"></i>
                    </a>
                    <a class="btn btn-warning btn-sm" href="<?= escape(route_url('user/logout')) ?>" title="Logout">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- MAIN CONTENT                                 -->
<!-- ============================================ -->
<main class="admin-shell">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <aside class="col-lg-2 col-md-3 d-none d-md-block sidebar p-0">
                <nav class="nav flex-column p-3">
                    <?php foreach ($modules as $menu): ?>
                        <?php
                        $visibleChildren = array_filter($menu['children'] ?? [], fn($c) => empty($c['hidden']));
                        if (empty($visibleChildren)) continue;

                        $isGroupActive = isMenuGroupActive($visibleChildren, $currentRoute, $currentUrl, $basePath);
                        $groupId = 'admin-menu-' . md5((string) ($menu['label'] ?? 'group'));
                        ?>
                        <div class="menu-group mb-3">
                            <button
                                class="menu-group-header btn btn-link text-uppercase small fw-bold text-muted mb-2 px-0 text-start w-100 <?= $isGroupActive ? 'text-primary' : '' ?>"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= $groupId ?>"
                                aria-expanded="<?= $isGroupActive ? 'true' : 'false' ?>"
                                aria-controls="<?= $groupId ?>"
                            >
                                <?php if (!empty($menu['icon'])): ?>
                                    <i class="<?= htmlspecialchars($menu['icon']) ?> me-1"></i>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($menu['label']) ?></span>
                            </button>

                            <ul id="<?= $groupId ?>" class="nav flex-column collapse <?= $isGroupActive ? 'show' : '' ?>">
                                <?php foreach ($visibleChildren as $item): ?>
                                    <li class="nav-item">
                                        <?php if (!empty($item['url'])): ?>
                                            <?php
                                            $isActive = isMenuItemActive($item, $currentRoute, $currentUrl, $basePath);
                                            $target = !empty($item['target']) ? 'target="' . escape($item['target']) . '"' : '';
                                            ?>
                                            <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                                               href="<?= $item['url'] ?>"
                                               <?= $target ?>>
                                                <?php if (!empty($item['icon'])): ?>
                                                    <i class="<?= htmlspecialchars($item['icon']) ?> me-2"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($item['label']) ?>
                                                <?php if ($isActive): ?>
                                                    <span class="badge bg-primary rounded-pill ms-1">•</span>
                                                <?php endif; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="nav-link text-muted fw-bold">
                                                <?php if (!empty($item['icon'])): ?>
                                                    <i class="<?= htmlspecialchars($item['icon']) ?> me-2"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($item['label']) ?>
                                            </span>
                                            <?php if (!empty($item['children'])): ?>
                                                <ul class="nav flex-column ps-3">
                                                    <?php foreach ($item['children'] as $subItem): ?>
                                                        <?php if (!empty($subItem['hidden']) || empty($subItem['url'])) continue; ?>
                                                        <li class="nav-item">
                                                            <?php $isActive = isMenuItemActive($subItem, $currentRoute, $currentUrl, $basePath); ?>
                                                            <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= $subItem['url'] ?>">
                                                                <?php if (!empty($subItem['icon'])): ?>
                                                                    <i class="<?= htmlspecialchars($subItem['icon']) ?> me-2"></i>
                                                                <?php endif; ?>
                                                                <?= htmlspecialchars($subItem['label']) ?>
                                                                <?php if ($isActive): ?>
                                                                    <span class="badge bg-primary rounded-pill ms-1">•</span>
                                                                <?php endif; ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <!-- CONTENT AREA -->
            <div class="col-lg-10 col-md-9 col-12 content-wrapper">
                <?php echo \System\Library\Notify::read(); ?>
                <?= $this->block->render('content_top') ?>
                
                <div class="content-card p-4 bg-white rounded-3 shadow-sm">
                    <?= $content ?? '<div class="text-muted text-center py-5">Select a menu item to begin.</div>' ?>
                </div>
                
                <?= $this->block->render('content_bottom') ?>
            </div>
        </div>
    </div>
</main>

<!-- ============================================ -->
<!-- FOOTER                                       -->
<!-- ============================================ -->
<footer class="admin-footer text-center text-muted py-3 border-top">
    <small>Demha Admin &copy; <?= date('Y') ?> — All rights reserved.</small>
</footer>

<!-- ============================================ -->
<!-- SCRIPTS                                      -->
<!-- ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->doc->renderJs() ?>

<script>
// Dark mode toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.createElement('button');
    toggle.className = 'btn btn-outline-light btn-sm ms-2';
    toggle.innerHTML = '<i class="bi bi-moon"></i>';
    toggle.title = 'Toggle Dark Mode';
    toggle.addEventListener('click', function() {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
        toggle.innerHTML = isDark ? '<i class="bi bi-moon"></i>' : '<i class="bi bi-sun"></i>';
    });

    // Check saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        toggle.innerHTML = '<i class="bi bi-sun"></i>';
    }

    // Add to navbar
    const navbarRight = document.querySelector('.navbar .d-flex');
    if (navbarRight) {
        navbarRight.prepend(toggle);
    }

    // Sidebar accordion: keep only the current group open if it is active,
    // but still allow manual collapse/expand for all others.
    document.querySelectorAll('.menu-group-header').forEach((button) => {
        const target = document.getElementById(button.getAttribute('aria-controls'));
        if (!target) return;

        button.addEventListener('click', function() {
            const isOpen = button.getAttribute('aria-expanded') === 'true';
            document.querySelectorAll('.menu-group .collapse.show').forEach((section) => {
                if (section !== target) {
                    const collapse = bootstrap.Collapse.getOrCreateInstance(section);
                    collapse.hide();
                }
            });

            if (!isOpen) {
                const instance = bootstrap.Collapse.getOrCreateInstance(target);
                instance.show();
            }
        });
    });
});
</script>
</body>
</html>