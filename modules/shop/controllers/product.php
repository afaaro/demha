<?php
use System\Engine\Controller;

class ShopProduct extends Controller
{
    public function indexAction(): void
    {
        $id = (int) $this->request->get('id', 'int', 0);

        // ---- Product details ----
        $product = $this->db->query("
            SELECT p.*, c.name AS category_name
            FROM #__shop_product p
            LEFT JOIN #__shop_categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.status = 'active' AND p.deleted_at IS NULL
        ", [$id])->row;

        if (!$product) {
            http_response_code(404);
            echo $this->view->inline(function () {
                echo '<div class="container py-5"><h3>Product not found</h3><p>The product you are looking for does not exist or is unavailable.</p></div>';
            }, 'main');
            return;
        }

        $this->view->assign('title', $product['name']);

        // ---- Product Images ----
        $images = $this->db->query("
            SELECT url, is_primary, alt
            FROM #__shop_product_image
            WHERE product_id = ?
            ORDER BY is_primary DESC, sort_order ASC
        ", [$id])->rows;

        $primaryImg = null;
        $allImages = [];
        foreach ($images as $img) {
            $allImages[] = $img['url'];
            if ($img['is_primary']) {
                $primaryImg = $img['url'];
            }
        }
        if (!$primaryImg && !empty($allImages)) {
            $primaryImg = $allImages[0];
        }

        // ---- Option Groups & Values (with sort_order) ----
        $optionGroups = $this->db->query("
            SELECT og.id AS group_id, og.name AS group_name, og.type, og.sort_order AS group_sort,
                   ov.id AS value_id, ov.value AS value_name, ov.sort_order AS val_sort
            FROM #__shop_product_option_group pog
            JOIN #__shop_option_group og ON og.id = pog.group_id
            JOIN #__shop_option_value ov ON ov.group_id = og.id
            WHERE pog.product_id = ?
            ORDER BY og.sort_order, og.id, ov.sort_order, ov.id
        ", [$id])->rows;

        // Get display order of group IDs
        $groupOrder = [];
        foreach ($optionGroups as $og) {
            if (!in_array($og['group_id'], $groupOrder)) {
                $groupOrder[] = $og['group_id'];
            }
        }

        // Map value_id → group_id
        $valueToGroup = [];
        foreach ($optionGroups as $og) {
            $valueToGroup[$og['value_id']] = $og['group_id'];
        }

        // ---- Variants ----
        $variantsRaw = $this->db->query("
            SELECT pv.id, pv.sku, pv.price,
                   COALESCE((SELECT quantity FROM #__shop_inventory WHERE variant_id = pv.id LIMIT 1), 0) AS stock,
                   pvo.value_id
            FROM #__shop_product_variant pv
            LEFT JOIN #__shop_product_variant_option pvo ON pvo.variant_id = pv.id
            WHERE pv.product_id = ? AND pv.deleted_at IS NULL
            ORDER BY pv.id
        ", [$id])->rows;

        // Build variant map in correct display order
        $variantMap = [];
        $currentVid = null;
        $valueIds = [];
        $variantData = [];

        foreach ($variantsRaw as $row) {
            if ($currentVid !== $row['id']) {
                // Save previous variant
                if ($currentVid !== null) {
                    $keyParts = [];
                    foreach ($groupOrder as $gid) {
                        if (!empty($valueIds[$gid])) {
                            $keyParts[] = $valueIds[$gid];
                        }
                    }
                    $key = implode(',', $keyParts);
                    $variantMap[$key] = [
                        'id'    => $currentVid,
                        'sku'   => $variantData['sku'],
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                    ];
                }
                $currentVid = $row['id'];
                $variantData = [
                    'sku'   => $row['sku'],
                    'price' => $row['price'],
                    'stock' => $row['stock']
                ];
                $valueIds = [];
            }
            if (!empty($row['value_id']) && isset($valueToGroup[$row['value_id']])) {
                $gid = $valueToGroup[$row['value_id']];
                $valueIds[$gid] = $row['value_id'];
            }
        }

        // Save last variant
        if ($currentVid !== null) {
            $keyParts = [];
            foreach ($groupOrder as $gid) {
                if (!empty($valueIds[$gid])) {
                    $keyParts[] = $valueIds[$gid];
                }
            }
            $key = implode(',', $keyParts);
            $variantMap[$key] = [
                'id'    => $currentVid,
                'sku'   => $variantData['sku'],
                'price' => $variantData['price'],
                'stock' => $variantData['stock'],
            ];
        }

        // ---- Debug: Log variant map for frontend troubleshooting ----
        error_log('Product ID: ' . $id . ' - Variant Map: ' . print_r($variantMap, true));

        $hasOptions = !empty($groupOrder);
        $url = $this->url;

        echo $this->view->inline(function ($view) use (
            $product, $allImages, $primaryImg, $optionGroups,
            $variantMap, $hasOptions, $url, $groupOrder, $id
        ) {
            ?>
            <div class="container py-4">
                <div class="row g-4">

                    <!-- Image Column -->
                    <div class="col-md-6">
                        <?php if ($primaryImg): ?>
                            <?php
                            $src = $url->asset('uploads/shop/products/' . basename($primaryImg));
                            ?>
                            <img src="<?= $src ?>" class="img-fluid rounded shadow-sm" alt="<?= htmlspecialchars($product['name']) ?>" id="main-product-img">
                        <?php else: ?>
                            <div class="bg-light text-center p-5 rounded">
                                <p class="text-muted">No image uploaded</p>
                            </div>
                        <?php endif; ?>

                        <?php if (count($allImages) > 1): ?>
                        <div class="row mt-3 g-2">
                            <?php foreach ($allImages as $imgUrl): ?>
                                <?php $thumbSrc = $url->asset('uploads/shop/products/' . basename($imgUrl)); ?>
                                <div class="col-3">
                                    <img src="<?= $thumbSrc ?>" class="img-thumbnail" style="height:80px;object-fit:cover;cursor:pointer;" 
                                         onclick="document.getElementById('main-product-img').src='<?= $thumbSrc ?>'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info Column -->
                    <div class="col-md-6">
                        <h1 class="h3 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                        <p class="text-muted mb-3"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></p>

                        <!-- Price -->
                        <?php if ($hasOptions): ?>
                            <?php
                            $prices = array_column($variantMap, 'price');
                            $minPrice = $prices ? min($prices) : $product['price'];
                            $maxPrice = $prices ? max($prices) : $product['price'];
                            ?>
                            <h3 class="text-primary mb-3">
                                <?php if ($minPrice == $maxPrice): ?>
                                    £<?= number_format($minPrice, 2) ?>
                                <?php else: ?>
                                    £<?= number_format($minPrice, 2) ?> – £<?= number_format($maxPrice, 2) ?>
                                <?php endif; ?>
                            </h3>
                        <?php else: ?>
                            <h3 class="text-primary mb-3">£<?= number_format($product['price'], 2) ?></h3>
                        <?php endif; ?>

                        <!-- Description -->
                        <p><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>

                        <?php if ($hasOptions): ?>
                            <!-- With Options -->
                            <form method="POST" action="<?= $url->to('shop/cart/add') ?>">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input type="hidden" name="variant_id" id="selected-variant" value="">

                                <?php
                                // Build grouped options
                                $groups = [];
                                foreach ($optionGroups as $og) {
                                    if (!isset($groups[$og['group_id']])) {
                                        $groups[$og['group_id']] = [
                                            'name' => $og['group_name'],
                                            'type' => $og['type'] ?? 'select',
                                            'values' => []
                                        ];
                                    }
                                    $found = false;
                                    foreach ($groups[$og['group_id']]['values'] as $v) {
                                        if ($v['id'] == $og['value_id']) {
                                            $found = true;
                                            break;
                                        }
                                    }
                                    if (!$found) {
                                        $groups[$og['group_id']]['values'][] = [
                                            'id' => $og['value_id'],
                                            'name' => $og['value_name']
                                        ];
                                    }
                                }

                                // Render selects in display order
                                foreach ($groupOrder as $gid):
                                    $g = $groups[$gid];
                                    $isColor = strtolower($g['name']) === 'color';
                                ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold"><?= htmlspecialchars($g['name']) ?></label>
                                    <?php if ($isColor): ?>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <?php foreach ($g['values'] as $val): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-secondary option-btn" 
                                                        data-group="<?= $gid ?>" 
                                                        data-value="<?= $val['id'] ?>"
                                                        style="min-width:50px;">
                                                    <?= htmlspecialchars($val['name']) ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" class="product-option-hidden" data-group="<?= $gid ?>" value="">
                                    <?php else: ?>
                                        <select class="form-select product-option" data-group="<?= $gid ?>" required>
                                            <option value="">— Select <?= htmlspecialchars($g['name']) ?> —</option>
                                            <?php foreach ($g['values'] as $val): ?>
                                                <option value="<?= $val['id'] ?>"><?= htmlspecialchars($val['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>

                                <div id="variant-info" class="alert alert-info mb-3">
                                    Please select all options to see price and stock.
                                </div>

                                <div class="input-group mb-3" style="max-width:160px;">
                                    <label class="input-group-text">Qty</label>
                                    <input type="number" name="quantity" class="form-control" value="1" min="1" max="999">
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100" id="add-btn" disabled>
                                    Add to Cart
                                </button>
                            </form>

                            <!-- Pass data to JavaScript -->
                            <?php
                            $vm = json_encode($variantMap);
                            $go = json_encode($groupOrder);
                            $view->doc->addInlineJs("
                                const variantMap = {$vm};
                                const groupOrder = {$go};
                                const info = document.getElementById('variant-info');
                                const btn = document.getElementById('add-btn');
                                const vidInput = document.getElementById('selected-variant');

                                // Get all option inputs
                                const selects = document.querySelectorAll('.product-option');
                                const hiddenInputs = document.querySelectorAll('.product-option-hidden');
                                const optionButtons = document.querySelectorAll('.option-btn');

                                // Color buttons
                                optionButtons.forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const group = this.dataset.group;
                                        const value = this.dataset.value;
                                        
                                        // Update hidden input
                                        document.querySelector(`.product-option-hidden[data-group=\"\${group}\"]`).value = value;
                                        
                                        // Update button styles
                                        const siblings = this.parentElement.querySelectorAll('.option-btn');
                                        siblings.forEach(s => s.classList.remove('btn-primary', 'active'));
                                        this.classList.add('btn-primary', 'active');
                                        
                                        update();
                                    });
                                });

                                // Select dropdowns
                                selects.forEach(s => s.addEventListener('change', update));

                                // Hidden inputs for color
                                hiddenInputs.forEach(h => h.addEventListener('change', update));

                                window.addEventListener('DOMContentLoaded', function() {
                                    setTimeout(update, 100);
                                });

                                function update() {
                                    // Get selected values
                                    const sel = {};

                                    // Get select values
                                    selects.forEach(s => {
                                        const val = (s.value || '').trim();
                                        if (val) sel[s.dataset.group] = val;
                                    });

                                    // Get hidden input values (for color buttons)
                                    hiddenInputs.forEach(h => {
                                        const val = (h.value || '').trim();
                                        if (val) sel[h.dataset.group] = val;
                                    });

                                    // Build key
                                    const keyParts = [];
                                    let ready = true;
                                    groupOrder.forEach(gid => {
                                        const val = sel[gid];
                                        if (!val) {
                                            ready = false;
                                        } else {
                                            keyParts.push(val);
                                        }
                                    });
                                    const key = keyParts.join(',');

                                    if (!ready) {
                                        info.className = 'alert alert-info mb-3';
                                        info.textContent = 'Please select all options to see price and stock.';
                                        btn.disabled = true;
                                        vidInput.value = '';
                                        return;
                                    }

                                    if (variantMap.hasOwnProperty(key)) {
                                        const v = variantMap[key];
                                        const inStock = v.stock > 0;
                                        info.className = inStock ? 'alert alert-success mb-3' : 'alert alert-warning mb-3';
                                        info.innerHTML = '<strong>Price:</strong> £' + parseFloat(v.price).toFixed(2) + 
                                                         ' | <strong>Stock:</strong> ' + v.stock + 
                                                         ' | <strong>SKU:</strong> ' + v.sku;
                                        btn.disabled = !inStock;
                                        vidInput.value = v.id;
                                    } else {
                                        info.className = 'alert alert-danger mb-3';
                                        info.innerHTML = '<strong>This combination is not available.</strong> Please select a different combination.';
                                        btn.disabled = true;
                                        vidInput.value = '';
                                    }
                                }
                            ");
                            ?>

                        <?php else: ?>
                            <!-- Simple Product (No Options) -->
                            <form method="POST" action="<?= $url->to('shop/cart/add') ?>">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input type="hidden" name="variant_id" value="0">

                                <?php
                                // Get stock for simple product
                                $stock = $this->db->query("
                                    SELECT COALESCE(SUM(quantity), 0) as total
                                    FROM #__shop_inventory i
                                    JOIN #__shop_product_variant v ON v.id = i.variant_id
                                    WHERE v.product_id = ? AND v.deleted_at IS NULL
                                ", [$id])->row['total'] ?? 0;
                                ?>

                                <?php if ($stock > 0): ?>
                                    <div class="alert alert-success mb-3">
                                        <strong>In Stock</strong> - <?= $stock ?> units available
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger mb-3">
                                        <strong>Out of Stock</strong>
                                    </div>
                                <?php endif; ?>

                                <div class="input-group mb-3" style="max-width:160px;">
                                    <label class="input-group-text">Qty</label>
                                    <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?= $stock ?: 1 ?>" <?= $stock <= 0 ? 'disabled' : '' ?>>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100" <?= $stock <= 0 ? 'disabled' : '' ?>>
                                    Add to Cart
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            <?php
        }, 'main');
    }
}