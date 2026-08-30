<?php
use App\Modules\Shop\Controllers\Base;

class ShopShop extends Base {
    public function indexAction() {
        Template::setTitle('Shopping homepage');

        $products = $this->db->query("
            SELECT p.id, p.name, p.slug, p.price, p.discount, p.images, p.status,p.primary_image,
                s.id AS store_id, s.name AS store_name
            FROM #__shop_products p
            INNER JOIN #__shop_stores s ON s.id = p.store_id
            WHERE p.status = 'active' AND s.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        //echo opentable('Our Products');

        echo '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">';

        foreach ($products as $p) {
            $price = $p['discount'] > 0 ? '<s>£' . number_format($p['pr ice'], 2) . '</s> £' . number_format($p['price'] - $p['discount'], 2) : '£' . number_format($p['price'], 2);

            $images = !empty($p['images']) ? json_decode($p['images'], true) : [];
            $primaryImage = $p['primary_image'] ?? ($images[0] ?? null);

            if (!empty($primaryImage) && file_exists(BASEDIR . 'uploads/shop/products/' . $p['store_id'] . '/' . $primaryImage)) {
                $cover = 'uploads/shop/products/' . $p['store_id'] . '/' . $primaryImage;
            } else {
                $cover = 'themes/asset/images/no-image.png';
            }
            
            echo '<div class="col">
                <div class="card h-100">
                    <img src="' . $cover . '" class="card-img-top" alt="' . htmlspecialchars($p['name']) . '">
                    <div class="card-body">
                        <h5 class="card-title">' . htmlspecialchars($p['name']) . '</h5>
                        <p class="card-text">' . $price . '</p>
                        <a href="' . $this->url->link('shop/product', 'id=' . $p['id']) . '" class="btn text-bg-primary btn-sm">View</a>
                    </div>
                </div>
            </div>';
        }

        echo '</div>';

        //echo closetable();

        Template::theme()->render();
    }
}