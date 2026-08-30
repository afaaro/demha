<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Shop') ?></title>
    <base href="<?= $this->request->getBaseUrl() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?= $this->doc->renderMeta(); ?>
    <?= $this->doc->renderCss(); ?>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?= $this->url->to('shop') ?>">🛒 My Shop</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navShop">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navShop">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= $this->url->to('shop') ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Categories</a></li>
                </ul>
                <?= $head_title ?? '' ?>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div id="cart-notice"></div>
        <?= $content ?? '' ?>
    </div>

    <footer class="bg-light text-center py-3 mt-4 border-top">
        <p>&copy; <?= date('Y') ?> My Shop – All rights reserved.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->doc->renderJs(); ?>
</body>
</html>