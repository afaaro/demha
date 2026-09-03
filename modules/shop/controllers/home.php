<?php

use System\Engine\Controller;

class ShopHome extends Controller
{
    public function indexAction(): void
    {
        $this->view->assign('title', 'Our Products');
        $prefix = $this->db->getPrefix();

        $products = $this->db->query("
            SELECT p.*, 
                   c.name AS category_name,
                   (SELECT MIN(price) FROM {$prefix}shop_product_variant WHERE product_id = p.id) AS min_price,
                   (SELECT MAX(price) FROM {$prefix}shop_product_variant WHERE product_id = p.id) AS max_price
            FROM {$prefix}shop_product p
            LEFT JOIN {$prefix}shop_categories c ON c.id = p.category_id
            WHERE p.status = 'active' AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
            LIMIT 12
        ")->rows;

        $url = $this->url;
        $getPriceDisplay = fn(array $p): string => $this->getPriceDisplay($p);

        echo $this->view->inline(function ($view) use ($products, $url, $getPriceDisplay) {
            if (empty($products)) {
                echo '<div class="alert alert-info">No products available yet.</div>';
            } else {
                echo '<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">';
                foreach ($products as $p) {
                    $img = !empty($p['primary_image'])
                        ? $url->asset('uploads/shop/products/' . ($p['store_id'] ?? 1) . '/' . $p['primary_image'])
                        : $url->asset('assets/img/no-image.png');
                    $category = !empty($p['category_name']) ? htmlspecialchars($p['category_name']) : 'Uncategorized';
                    $priceDisplay = $getPriceDisplay($p);
                    $productUrl = $url->to('shop/product', ['id' => $p['id']]);
                    $nameEsc = htmlspecialchars($p['name']);

                    echo <<<HTML
                    <div class="col">
                        <div class="card h-100">
                            <img src="{$img}" class="card-img-top" alt="{$nameEsc}" style="height:200px;object-fit:cover;">
                            <div class="card-body">
                                <h5 class="card-title">{$nameEsc}</h5>
                                <p class="card-text text-muted small">{$category}</p>
                                <p class="card-text"><strong>{$priceDisplay}</strong></p>
                                <a href="{$productUrl}" class="btn btn-primary btn-sm w-100">View Product</a>
                            </div>
                        </div>
                    </div>
                    HTML;
                }
                echo '</div>';
            }
        }, 'shop/shop');
    }

    private function getPriceDisplay(array $product): string
    {
        $basePrice = (float) ($product['price'] ?? 0);
        $minPrice  = isset($product['min_price']) ? (float) $product['min_price'] : null;
        $maxPrice  = isset($product['max_price']) ? (float) $product['max_price'] : null;

        if ($minPrice === null) {
            return '£' . number_format($basePrice, 2);
        }
        if ($minPrice === $maxPrice && $minPrice === $basePrice) {
            return '£' . number_format($basePrice, 2);
        }
        if ($minPrice !== $maxPrice) {
            return '£' . number_format($minPrice, 2) . ' – £' . number_format($maxPrice, 2);
        }
        return '£' . number_format($minPrice, 2);
    }
}