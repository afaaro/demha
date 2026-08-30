<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <title>Demha</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($this->view->asset('main.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #2563eb;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fbff 0%, var(--bg) 100%);
            color: var(--text);
            min-height: 100vh;
        }
        .app-shell {
            padding: 2rem 0 3rem;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .content-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
            padding: 1.5rem;
        }
        .content-card h1, .content-card h2, .content-card h3 {
            color: #0f172a;
            font-weight: 700;
        }
        .content-card .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        .content-card .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        .content-card .text-muted {
            color: var(--muted) !important;
        }
        .hero-banner {
            background: linear-gradient(120deg, #0f172a 0%, #2563eb 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        .hero-banner h1 {
            color: white;
        }
        .hero-banner .lead {
            color: rgba(255,255,255,0.9);
        }
    </style>
    <?= $this->doc->renderMeta(); ?>
    <?= $this->doc->renderCss(); ?>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(120deg, #0f172a 0%, #1d4ed8 100%);">
        <div class="container">
            <a class="navbar-brand" href="<?= escape(route_url('user')) ?>">Demha</a>
            <div class="ms-auto d-flex gap-2 flex-wrap align-items-center">
                <?php
                if ($this->auth->check()) {
                    echo '<span class="text-light">Hello, ' . escape($this->auth->data('username')) . '</span>';
                    echo '<a class="btn btn-outline-light btn-sm" href="' . escape(route_url('user/logout')) . '">Logout</a>';
                } else {
                    echo '<a class="btn btn-outline-light btn-sm" href="' . escape(route_url('user/login')) . '">Login</a>';
                    if ($this->config->get('auth.register_enabled')) {
                        echo '<a class="btn btn-light btn-sm" href="' . escape(route_url('user/register')) . '">Register</a>';
                    }
                }
                ?>
                
            </div>
        </div>
    </nav>

    <main class="app-shell">
        <div class="container">
            <!-- <div class="hero-banner">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h1 class="display-6 fw-bold mb-2">Modern authentication, permissions, and account management</h1>
                        <p class="lead mb-0">A clean foundation for secure user experiences with a polished, professional presentation.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <a class="btn btn-light btn-lg" href="<?= escape(route_url('user/register')) ?>">Get started</a>
                    </div>
                </div>
            </div> -->

            <?= $this->block->render('content_top') ?>

            <?php
            $sidebarLeft = $this->block->render('sidebar_left');
            $sidebarRight = $this->block->render('sidebar_right');
            $hasSidebarLeft = trim($sidebarLeft) !== '';
            $hasSidebarRight = trim($sidebarRight) !== '';
            $contentClass = 'col-12';
            if ($hasSidebarLeft && $hasSidebarRight) {
                $contentClass = 'col-lg-6';
            } elseif ($hasSidebarLeft || $hasSidebarRight) {
                $contentClass = 'col-lg-9';
            }
            ?>
            <div class="row">
                <?php if ($hasSidebarLeft): ?>
                <div class="col-lg-3">
                    <?= $sidebarLeft ?>
                </div>
                <?php endif; ?>
                <div class="<?= $contentClass ?>">
                    <div class="content-card">
                        <?= $content ?? '' ?>
                    </div>
                </div>
                <?php if ($hasSidebarRight): ?>
                <div class="col-lg-3">
                    <?= $sidebarRight ?>
                </div>
                <?php endif; ?>
            </div>

            <?= $this->block->render('content_bottom') ?>
        </div>
    </main>

    <footer class="container mt-4">
        <?= $this->block->render('footer') ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->doc->renderJs() ?>
</body>
</html>