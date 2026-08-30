<?php
use App\Modules\Shop\Controllers\Base;

class ShopProduct extends Base {
    public function viewAction(): void
    {
        $id = (int) $this->request->route('id');
        $product = $this->db->query("
            SELECT p.*, c.name AS category_name, s.name AS store_name
            FROM #__shop_product p
            LEFT JOIN #__shop_categories c ON c.id = p.category_id
            LEFT JOIN #__shop_stores s ON s.id = p.store_id
            WHERE p.id = ? AND p.status = 'active' AND p.deleted_at IS NULL
        ", [$id])->row;

        if (!$product) {
            http_response_code(404);
            echo $this->view->inline(function () {
                echo '<h3>Product not found</h3>';
            }, 'shop');
            return;
        }

        $this->view->assign('title', $product['name']);

        $images = json_decode($product['images'] ?? '[]', true);
        $cover = $product['primary_image'] ?? ($images[0] ?? null);

        // Get option groups assigned to this product
        $optionGroups = $this->db->query("
            SELECT og.id AS group_id, og.name AS group_name, og.type,
                   ov.id AS value_id, ov.value AS value_name
            FROM #__shop_product_option_group pog
            JOIN #__shop_option_group og ON og.id = pog.group_id
            JOIN #__shop_option_value ov ON ov.group_id = og.id
            WHERE pog.product_id = ?
            ORDER BY og.sort_order, ov.sort_order
        ", [$id])->rows;

        // Get variants with their option value IDs
        $variants = $this->db->query("
            SELECT pv.*, 
                   GROUP_CONCAT(pvo.value_id ORDER BY pvo.value_id ASC) AS value_ids
            FROM #__shop_product_variant pv
            LEFT JOIN #__shop_product_variant_option pvo ON pvo.variant_id = pv.id
            WHERE pv.product_id = ?
            GROUP BY pv.id
            ORDER BY pv.price ASC
        ", [$id])->rows;

        $variantMap = [];
        foreach ($variants as $v) {
            if (!empty($v['value_ids'])) {
                $key = implode('_', explode(',', $v['value_ids']));
                $variantMap[$key] = $v;
            }
        }

        $hasOptions = !empty($optionGroups);

        echo $this->view->inline(function ($view) use ($product, $images, $cover, $optionGroups, $variants, $variantMap, $hasOptions) {
            echo Notify::read();

            echo '<div class="row">';
            // ---- Image column ----
            echo '<div class="col-md-6">';
            if ($cover) {
                $src = $this->url->asset('uploads/shop/products/' . $product['store_id'] . '/' . $cover);
                echo "<img src='{$src}' class='img-fluid rounded' alt='{$product['name']}' style='width:100%;'>";
            } else {
                echo '<div class="bg-light text-center p-5 rounded">No image</div>';
            }
            if (count($images) > 1) {
                echo '<div class="row mt-2 g-1">';
                foreach ($images as $img) {
                    $thumbSrc = $this->url->asset('uploads/shop/products/' . $product['store_id'] . '/' . $img);
                    echo "<div class='col-3'><img src='{$thumbSrc}' class='img-thumbnail' style='height:80px;object-fit:cover;cursor:pointer;' onclick=\"this.closest('.col-md-6').querySelector('.img-fluid').src='{$thumbSrc}'\"></div>";
                }
                echo '</div>';
            }
            echo '</div>';

            // ---- Details column ----
            echo '<div class="col-md-6">';
            echo "<h1>" . htmlspecialchars($product['name']) . "</h1>";
            echo "<p class='text-muted'>" . htmlspecialchars($product['category_name'] ?? 'Uncategorized') . " – " . htmlspecialchars($product['store_name'] ?? '') . "</p>";

            // Price display
            if ($hasOptions) {
                $minPrice = !empty($variants) ? min(array_column($variants, 'price')) : $product['price'];
                $maxPrice = !empty($variants) ? max(array_column($variants, 'price')) : $product['price'];
                if ($minPrice == $maxPrice) {
                    echo "<h3 class='text-primary'>£" . number_format($minPrice, 2) . "</h3>";
                } else {
                    echo "<h3 class='text-primary'>£" . number_format($minPrice, 2) . " – £" . number_format($maxPrice, 2) . "</h3>";
                }
            } else {
                echo "<h3 class='text-primary'>£" . number_format($product['price'], 2) . "</h3>";
            }

            echo "<p>" . nl2br(htmlspecialchars($product['description'] ?? '')) . "</p>";

            if ($hasOptions) {
                echo '<form id="add-to-cart-form" method="POST" action="' . $this->url->to('shop/cart/add') . '">';
                echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
                echo '<input type="hidden" name="variant_id" id="selected-variant" value="">';

                // Group options by group_id
                $groups = [];
                foreach ($optionGroups as $og) {
                    if (!isset($groups[$og['group_id']])) {
                        $groups[$og['group_id']] = [
                            'name' => $og['group_name'],
                            'type' => $og['type'],
                            'values' => [],
                        ];
                    }
                    $groups[$og['group_id']]['values'][] = [
                        'id' => $og['value_id'],
                        'name' => $og['value_name'],
                    ];
                }

                foreach ($groups as $gid => $group) {
                    echo '<div class="mb-3">';
                    echo '<label class="form-label fw-bold">' . htmlspecialchars($group['name']) . '</label>';
                    echo '<select class="form-select product-option" data-group="' . $gid . '">';
                    echo '<option value="">Select ' . htmlspecialchars($group['name']) . '</option>';
                    foreach ($group['values'] as $val) {
                        echo '<option value="' . $val['id'] . '">' . htmlspecialchars($val['name']) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                }

                echo '<div id="variant-info" class="alert alert-info">Please select all options to see price and stock.</div>';

                echo '<div class="input-group mb-3" style="max-width:150px;">';
                echo '<label class="input-group-text">Qty</label>';
                echo '<input type="number" name="quantity" class="form-control" value="1" min="1" max="999">';
                echo '</div>';
                echo '<button type="submit" class="btn btn-success btn-lg" id="add-to-cart-btn" disabled>Add to Cart</button>';
                echo '</form>';

                $variantMapJson = json_encode($variantMap);
                $view->doc->addInlineJs("
                    const variantMap = {$variantMapJson};

                    document.querySelectorAll('.product-option').forEach(function(select) {
                        select.addEventListener('change', updateVariant);
                    });

                    function updateVariant() {
                        const selects = document.querySelectorAll('.product-option');
                        const selectedValues = [];
                        let allSelected = true;
                        selects.forEach(function(sel) {
                            if (sel.value === '') {
                                allSelected = false;
                            } else {
                                selectedValues.push(sel.value);
                            }
                        });

                        const infoBox = document.getElementById('variant-info');
                        const addBtn = document.getElementById('add-to-cart-btn');
                        const variantInput = document.getElementById('selected-variant');

                        if (!allSelected) {
                            infoBox.innerHTML = '<div class=\"alert alert-info\">Please select all options to see price and stock.</div>';
                            addBtn.disabled = true;
                            variantInput.value = '';
                            return;
                        }

                        const key = selectedValues.join('_');
                        const variant = variantMap[key];

                        if (variant) {
                            const price = parseFloat(variant.price).toFixed(2);
                            const stock = parseInt(variant.stock);
                            const sku = variant.sku || 'N/A';
                            infoBox.innerHTML = '<div class=\"alert alert-success\">Price: £' + price + ' | Stock: ' + stock + ' | SKU: ' + sku + '</div>';
                            addBtn.disabled = (stock <= 0);
                            variantInput.value = variant.id;
                        } else {
                            infoBox.innerHTML = '<div class=\"alert alert-warning\">This combination is not available.</div>';
                            addBtn.disabled = true;
                            variantInput.value = '';
                        }
                    }
                ");
            } else {
                // ---- Simple product (no options) ----
                $maxQty = isset($product['quantity']) ? $product['quantity'] : 999;
                echo '<form id="add-to-cart-form" method="POST" action="' . $this->url->to('shop/cart/add') . '">';
                echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
                echo '<input type="hidden" name="variant_id" value="0">';
                echo '<div class="input-group mb-3" style="max-width:150px;">';
                echo '<label class="input-group-text">Qty</label>';
                echo '<input type="number" name="quantity" class="form-control" value="1" min="1" max="' . $maxQty . '">';
                echo '</div>';
                echo '<button type="submit" class="btn btn-success btn-lg">Add to Cart</button>';
                echo '</form>';
            }

            echo '</div></div>';
        }, 'shop/shop');
    }
}