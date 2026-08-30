<?php

use System\Engine\Controller;
use System\Library\Notify;

class ShopAdminProduct extends Controller {
    public function indexAction(): void
    {

        $sql = "SELECT p.*, c.name as category_name,
                (SELECT SUM(i.quantity) FROM #__shop_inventory i JOIN #__shop_product_variant v ON i.variant_id = v.id WHERE v.product_id = p.id) as total_stock
                FROM #__shop_product p
                LEFT JOIN #__shop_categories c ON p.category_id = c.id
                ORDER BY p.id DESC";
        $products = $this->db->query($sql)->rows;

        echo $this->view->inline(function ($view) use ($products) {
            echo "<div class='d-flex justify-content-between mb-3'>";
            echo "<h3><i class='bi bi-cubes me-2'></i>Product Catalog</h3>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('shop/admin/product/add') . "'>Add Product</a>";
            echo "</div>";

            if (empty($products)) {
                echo "<div class='alert alert-info'>No products found.</div>";
            } else {
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped'>";
                echo "<thead><tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Total Stock</th>
                    <th>Actions</th>
                </tr></thead>";
                echo "<tbody>";
                foreach ($products as $p) {
                    $firstVariant = $this->db->query(
                        "SELECT id FROM #__shop_product_variant WHERE product_id = ? LIMIT 1",
                        [$p['id']]
                    )->row;
                    $vId = $firstVariant['id'] ?? 0;

                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($p['name']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($p['category_name'] ?? 'Uncategorized') . "</td>";
                    echo "<td>£" . number_format($p['price'], 2) . "</td>";
                    echo "<td>
                        <div class='input-group input-group-sm' style='width: 140px;'>
                            <input type='number' class='form-control quick-stock' data-id='{$vId}' value='" . (int)($p['total_stock'] ?? 0) . "'>
                            <button class='btn btn-outline-secondary btn-save-stock' type='button' " . (!$vId ? 'disabled' : '') . ">
                                <i class='bi bi-save'></i>
                            </button>
                        </div>
                    </td>";
                    echo "<td>
                        <a class='btn btn-sm btn-outline-primary' href='" . $view->url->to('shop/admin/product/edit', ['id' => $p['id']]) . "'><i class='bi bi-pencil'></i></a>
                        <a class='btn btn-sm btn-outline-danger' onclick=\"return confirm('Delete product and variants?')\" href='" . $view->url->to('shop/admin/product/delete', ['id' => $p['id']]) . "'><i class='bi bi-trash'></i></a>
                    </td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            }

            // Inline JS for stock update
            $ajaxUrl = $view->url->to('shop/admin/product/updateStockAjax');
            $view->doc->addInlineJs("
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('.btn-save-stock');
                    if (btn) {
                        const input = btn.parentElement.querySelector('.quick-stock');
                        const id = input.dataset.id;
                        const qty = input.value;
                        if (!id) return;

                        fetch('{$ajaxUrl}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'variant_id=' + encodeURIComponent(id) + '&quantity=' + encodeURIComponent(qty)
                        })
                        .then(res => res.json())
                        .then(data => {
                            if(data.status === 'success') {
                                btn.classList.replace('btn-outline-secondary', 'btn-success');
                                const icon = btn.querySelector('i');
                                icon.classList.replace('bi-save', 'bi-check');
                                setTimeout(() => {
                                    btn.classList.replace('btn-success', 'btn-outline-secondary');
                                    icon.classList.replace('bi-check', 'bi-save');
                                }, 1500);
                            }
                        });
                    }
                });
            ");
        }, 'admin');
    }

    // ==============================
    // Add Product (redirects to edit)
    // ==============================

    public function addAction(): void
    {
        $this->editAction();
    }

    // ==============================
    // Edit / Create Product
    // ==============================
    public function editAction(): void
    {
        $id = (int) $this->request->route('id', 'int', 0);

        if ($this->request->isPost()) {
            $this->saveProduct($id);
            return;
        }

        $product = $id ? $this->db->findOne('shop_product', $id) : [];
        if ($id && !$product) {
            Notify::error('Product not found.');
            redirect($this->url->to('shop/admin/product'));
            return;
        }

        $variants = $this->getVariantsWithAttributes($id);
        $groups = $this->db->query("
            SELECT * FROM #__shop_option_group
            ORDER BY name ASC
        ")->rows;

        $selectedGroups = [];
        if ($id) {
            $rows = $this->db->query("
                SELECT group_id FROM #__shop_product_option_group WHERE product_id = ?
            ", [$id])->rows;
            $selectedGroups = array_column($rows, 'group_id');
        }

        $categoryOptions = $this->getCategoryOptions();

        // Capture form and controller instance
        $form = $this->form;
        $controller = $this;

        echo $this->view->inline(function ($view) use ($product, $variants, $groups, $id, $categoryOptions, $selectedGroups, $form, $controller) {
            echo Notify::read();

            echo "<div class='card shadow-sm'>";
            echo "<div class='card-header bg-white py-3'>";
            echo "<h5 class='mb-0'>" . ($id ? 'Edit Product: ' . htmlspecialchars($product['name']) : 'Add New Product') . "</h5>";
            echo "</div>";
            echo "<div class='card-body'>";
            echo $form->start(['method' => 'POST']);

            // ---- Basic Product Fields ----
            echo "<div class='row mb-4'>";
            echo "<div class='col-md-6'>";
            echo $form->input('name', [
                'label' => 'Product Name',
                'rules' => 'required',
                'value' => $product['name'] ?? '',
            ]);
            echo "</div>";
            echo "<div class='col-md-6'>";
            echo $form->select('category_id', $categoryOptions, $product['category_id'] ?? '', [
                'label' => 'Category',
            ]);
            echo "</div>";
            echo "<div class='col-md-3'>";
            echo $form->input('sku', [
                'label' => 'Base SKU',
                'value' => $product['sku'] ?? '',
            ]);
            echo "</div>";
            echo "<div class='col-md-3'>";
            echo $form->input('price', [
                'label' => 'Base Price',
                'type' => 'number',
                'step' => '0.01',
                'value' => $product['price'] ?? '',
            ]);
            echo "</div>";
            echo "</div>";

            // ---- Option Groups Selection ----
            if (!empty($groups)) {
                echo "<hr>";
                echo "<h5 class='mb-3'>Option Groups (Attributes)</h5>";
                echo "<div class='row'>";
                foreach ($groups as $g) {
                    $checked = in_array($g['id'], $selectedGroups) ? 'checked' : '';
                    echo "<div class='col-md-3'>";
                    echo "<div class='form-check'>";
                    echo "<input class='form-check-input' type='checkbox' name='option_groups[]' value='{$g['id']}' id='group_{$g['id']}' {$checked}>";
                    echo "<label class='form-check-label' for='group_{$g['id']}'>" . htmlspecialchars($g['name']) . "</label>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";
                echo "<p class='text-muted small'>Select the attribute groups that apply to this product.</p>";
            }

            // ---- Variants Section ----
            echo "<hr>";
            echo "<div id='variant-area'>";
            echo "<h5 class='mb-3'>SKU Variants & Parameters</h5>";

            if (!empty($variants)) {
                foreach ($variants as $vIndex => $v) {
                    // Call the method via $controller
                    echo $controller->renderVariantRow($vIndex, $v, $groups);
                }
            }

            echo "</div>"; // end variant-area

            echo "<div class='mt-4 d-flex justify-content-between'>";
            echo "<button type='button' class='btn btn-outline-dark' onclick='addNewVariantRow()'>+ Add New SKU Variant</button>";
            echo "<div>";
            echo "<a class='btn btn-light me-2' href='" . $view->url->to('shop/admin/product') . "'>Cancel</a>";
            echo $form->submit('Save Product Changes', ['class' => 'btn btn-success']);
            echo "</div>";
            echo "</div>";

            echo $form->end();
            echo "</div></div>";

            // ---- JavaScript ----
            $groupJson = json_encode($groups);
            $view->doc->addInlineJs("
                window.vCounter = " . (empty($variants) ? 0 : max(array_keys($variants)) + 1) . ";
                const groupData = {$groupJson};
                const groupOptionsHtml = groupData.map(g => `<option value=\"\${g.id}\">\${g.name}</option>`).join('');

                function addNewVariantRow() {
                    const container = document.getElementById('variant-area');
                    const idx = window.vCounter;
                    const html = `
                        <div class='variant-item border rounded p-3 mb-3 bg-white shadow-sm'>
                            <div class='row g-3'>
                                <div class='col-md-3'>
                                    <label class='small fw-bold'>Variant SKU</label>
                                    <input type='text' name='variants[\${idx}][sku]' class='form-control form-control-sm' placeholder='Unique SKU'>
                                </div>
                                <div class='col-md-2'>
                                    <label class='small fw-bold'>Price Override</label>
                                    <input type='number' step='0.01' name='variants[\${idx}][price]' class='form-control form-control-sm'>
                                </div>
                                <div class='col-md-2'>
                                    <label class='small fw-bold'>Stock</label>
                                    <input type='number' name='variants[\${idx}][stock]' class='form-control form-control-sm' value='0'>
                                </div>
                                <div class='col-md-5'>
                                    <label class='small fw-bold'>Attributes</label>
                                    <div class='param-list'></div>
                                    <button type='button' class='btn btn-link btn-sm p-0 mt-1' onclick='addParamToVariant(this, \${idx})'>+ Add Parameter</button>
                                </div>
                            </div>
                            <div class='text-end mt-2'>
                                <button type='button' class='btn btn-outline-danger btn-sm' onclick='this.closest(\".variant-item\").remove()'>Remove Variant</button>
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', html);
                    window.vCounter++;
                }

                function addParamToVariant(btn, idx) {
                    const list = btn.closest('.col-md-5').querySelector('.param-list');
                    const html = `
                        <div class='d-flex gap-1 mb-1'>
                            <select class='form-select form-select-sm' onchange='updateValueDropdown(this)'>
                                <option value=''>-- Param --</option>
                                \${groupOptionsHtml}
                            </select>
                            <select name='variants[\${idx}][values][]' class='form-select form-select-sm'>
                                <option value=''>-- Value --</option>
                            </select>
                            <button type='button' class='btn-close ms-1' onclick='this.parentElement.remove()'></button>
                        </div>
                    `;
                    list.insertAdjacentHTML('beforeend', html);
                }

                async function updateValueDropdown(el) {
                    const valSelect = el.parentElement.querySelector('select[name*=\"[values][]\"]');
                    const gid = el.value;
                    if(!gid) return;
                    valSelect.innerHTML = '<option>...</option>';
                    try {
                        const response = await fetch('" . $view->url->to('shop/admin/product/getValues') . "?group_id=' + gid);
                        const data = await response.json();
                        valSelect.innerHTML = data.map(v => `<option value=\"\${v.id}\">\${v.value}</option>`).join('');
                    } catch (err) {
                        valSelect.innerHTML = '<option value=\"\">Error</option>';
                    }
                }
            ");
        }, 'admin');
    }

    // ==============================
    // Fetch Option Values (AJAX)
    // ==============================

    public function getValuesAction(): void
    {
        $groupId = (int) $this->request->get('group_id', 'int', 0);
        if (!$groupId) {
            $this->json(['error' => 'Invalid group ID']);
            return;
        }
        $values = $this->db->find('shop_option_value', ['group_id' => $groupId], 'value ASC');
        $this->json($values);
    }

    // ==============================
    // Delete Product
    // ==============================

    public function deleteAction(): void
    {
        $id = (int) $this->request->route('id');
        if (!$id) {
            Notify::error('Invalid product ID.');
            redirect($this->url->to('shop/admin/product'));
            return;
        }

        // Soft delete (set deleted_at)
        $this->db->update('shop_product', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        Notify::success('Product deleted.');
        redirect($this->url->to('shop/admin/product'));
    }

    // ==============================
    // AJAX: Quick Stock Update
    // ==============================
    public function updateStockAjaxAction(): void
    {
        $variantId = (int) $this->request->post('variant_id', 'int', 0);
        $newQty = (int) $this->request->post('quantity', 'int', 0);

        if (!$variantId) {
            $this->json(['status' => 'error', 'message' => 'Invalid variant']);
            return;
        }

        $current = $this->db->query(
            "SELECT quantity FROM #__shop_inventory WHERE variant_id = ?",
            [$variantId]
        )->row;
        if (!$current) {
            $this->json(['status' => 'error', 'message' => 'Variant not found in inventory']);
            return;
        }

        $diff = $newQty - (int)$current['quantity'];
        if ($diff !== 0) {
            $this->adjustStock($variantId, $diff, 'Quick update from list');
        }

        $this->json(['status' => 'success', 'new_stock' => $newQty]);
    }

    // ==============================
    // Helpers
    // ==============================

    /**
     * Fetch product variants with their attribute values.
     */
    private function getVariantsWithAttributes(int $productId): array
    {
        if (!$productId) return [];

        $variants = $this->db->find('shop_product_variant', ['product_id' => $productId], 'id ASC');
        foreach ($variants as &$v) {
            $v['attributes'] = $this->db->query("
                SELECT v.group_id, v.id as value_id, v.value as value_name
                FROM #__shop_product_variant_option pvo
                JOIN #__shop_option_value v ON pvo.value_id = v.id
                WHERE pvo.variant_id = ?
            ", [$v['id']])->rows;
        }
        return $variants;
    }

    /**
     * Render a single variant row (used in edit view).
     */
    private function renderVariantRow(int $index, array $variant, array $groups): string
    {
        // Get stock from inventory table
        $stock = $this->db->query(
            "SELECT quantity FROM #__shop_inventory WHERE variant_id = ?",
            [$variant['id']]
        )->value ?? 0;

        $html = '<div class="variant-item border rounded p-3 mb-3 bg-light">';
        $html .= '<div class="row g-3 align-items-start">';
        $html .= '<div class="col-md-3">';
        $html .= '<label class="small fw-bold">Variant SKU</label>';
        $html .= '<input type="text" name="variants[' . $index . '][sku]" class="form-control form-control-sm" value="' . htmlspecialchars($variant['sku'] ?? '') . '">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="small fw-bold">Price Override</label>';
        $html .= '<input type="number" step="0.01" name="variants[' . $index . '][price]" class="form-control form-control-sm" value="' . htmlspecialchars($variant['price'] ?? '') . '">';
        $html .= '</div>';
        $html .= '<div class="col-md-2">';
        $html .= '<label class="small fw-bold">Current Stock</label>';
        $html .= '<input type="number" name="variants[' . $index . '][stock]" class="form-control form-control-sm" value="' . (int)$stock . '">';
        $html .= '</div>';
        $html .= '<div class="col-md-5">';
        $html .= '<label class="small fw-bold">Attributes (Params)</label>';
        $html .= '<div class="param-list">';
        foreach ($variant['attributes'] as $attr) {
            $html .= '<div class="d-flex gap-1 mb-1">';
            $html .= '<select class="form-select form-select-sm" onchange="updateValueDropdown(this)">';
            foreach ($groups as $g) {
                $selected = ($g['id'] == $attr['group_id']) ? 'selected' : '';
                $html .= '<option value="' . $g['id'] . '" ' . $selected . '>' . htmlspecialchars($g['name']) . '</option>';
            }
            $html .= '</select>';
            $html .= '<select name="variants[' . $index . '][values][]" class="form-select form-select-sm">';
            $html .= '<option value="' . $attr['value_id'] . '" selected>Current: ' . htmlspecialchars($attr['value_name']) . '</option>';
            $html .= '</select>';
            $html .= '<button type="button" class="btn-close ms-1" onclick="this.parentElement.remove()"></button>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="addParamToVariant(this, ' . $index . ')">+ Add Parameter</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="text-end mt-2">';
        $html .= '<button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.variant-item\').remove()">Remove Variant</button>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Save product and variants (create or update).
     */
    private function saveProduct(int $productId): void
    {
        try {
            $this->db->beginTransaction();

            $name = $this->request->post('name', 'string', '');
            $baseSku = $this->request->post('sku', 'string', '');
            $basePrice = (float) $this->request->post('price', 'float', 0);
            $categoryId = (int) $this->request->post('category_id', 'int', 0);
            $slug = $this->generateSlug($name, $productId ?: null);

            if (!$name) {
                throw new \Exception('Product name is required.');
            }

            // Insert or update product
            if ($productId) {
                $this->db->update('shop_product', [
                    'name' => $name,
                    'slug' => $slug,
                    'sku' => $baseSku,
                    'price' => $basePrice,
                    'category_id' => $categoryId ?: null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => $productId]);
            } else {
                $this->db->insert('shop_product', [
                    'name' => $name,
                    'slug' => $slug,
                    'sku' => $baseSku,
                    'price' => $basePrice,
                    'category_id' => $categoryId ?: null,
                    'status' => 'draft',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $productId = $this->db->insert_id();
            }

            // ---- Save option groups ----
            $optionGroups = $this->request->post('option_groups', 'array', []);
            $this->db->delete('shop_product_option_group', ['product_id' => $productId]);
            foreach ($optionGroups as $gid) {
                if ((int)$gid > 0) {
                    $this->db->insert('shop_product_option_group', [
                        'product_id' => $productId,
                        'group_id'   => (int)$gid,
                    ]);
                }
            }

            // ---- Process variants ----
            $variants = $this->request->post('variants', 'array', []);
            $processedIds = [];

            foreach ($variants as $v) {
                if (empty($v['sku'])) continue;

                // Check SKU collision
                $existing = $this->db->first('shop_product_variant', ['sku' => $v['sku']]);
                if ($existing && (int)$existing['product_id'] !== $productId) {
                    throw new \Exception("SKU collision: '{$v['sku']}' belongs to another product.");
                }

                $price = !empty($v['price']) ? (float)$v['price'] : null;
                $stock = (int)($v['stock'] ?? 0);

                if ($existing && (int)$existing['product_id'] === $productId) {
                    $variantId = $existing['id'];
                    // Update variant (no stock column)
                    $this->db->update('shop_product_variant', [
                        'price' => $price,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], ['id' => $variantId]);
                    // Update inventory
                    $currentStock = $this->db->query(
                        "SELECT quantity FROM #__shop_inventory WHERE variant_id = ?",
                        [$variantId]
                    )->value ?? 0;
                    $diff = $stock - (int)$currentStock;
                    if ($diff !== 0) {
                        $this->adjustStock($variantId, $diff, 'Manual adjustment via edit');
                    }
                } else {
                    // Insert new variant
                    $this->db->insert('shop_product_variant', [
                        'product_id' => $productId,
                        'sku' => $v['sku'],
                        'price' => $price,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $variantId = $this->db->insert_id();
                    // Insert inventory row
                    $this->db->insert('shop_inventory', [
                        'variant_id' => $variantId,
                        'quantity' => $stock,
                        'reserved' => 0,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    if ($stock > 0) {
                        $this->adjustStock($variantId, $stock, 'Initial stock creation');
                    }
                }

                $processedIds[] = $variantId;

                // Sync attribute values
                $this->db->delete('shop_product_variant_option', ['variant_id' => $variantId]);
                if (!empty($v['values'])) {
                    foreach ($v['values'] as $valueId) {
                        if (!$valueId) continue;
                        $this->db->insert('shop_product_variant_option', [
                            'variant_id' => $variantId,
                            'value_id' => (int)$valueId,
                        ]);
                    }
                }
            }

            // Delete variants that were removed (hard delete)
            if (!empty($processedIds)) {
                $placeholders = implode(',', array_fill(0, count($processedIds), '?'));
                $this->db->query(
                    "DELETE FROM #__shop_product_variant WHERE product_id = ? AND id NOT IN ($placeholders)",
                    array_merge([$productId], $processedIds)
                );
            } else {
                $this->db->delete('shop_product_variant', ['product_id' => $productId]);
            }

            $this->db->commit();
            Notify::success('Product saved successfully.');
            redirect($this->url->to('shop/admin/product'));
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger?->error('Product save failed: ' . $e->getMessage());
            Notify::error($e->getMessage());
            redirect($this->url->to($productId ? 'shop/admin/product/edit' : 'shop/admin/product/add', $productId ? ['id' => $productId] : []));
        }
    }

    /**
     * Adjust stock and log the change.
     */
    private function adjustStock(int $variantId, int $change, string $reason = 'Manual Adjustment'): void
    {
        $current = $this->db->query(
            "SELECT quantity FROM #__shop_inventory WHERE variant_id = ?",
            [$variantId]
        )->row;
        if ($current) {
            $newQty = (int)$current['quantity'] + $change;
            $this->db->update('shop_inventory', [
                'quantity' => $newQty,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['variant_id' => $variantId]);
        } else {
            // If no inventory row exists, create one
            $this->db->insert('shop_inventory', [
                'variant_id' => $variantId,
                'quantity' => $change,
                'reserved' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->insert('shop_inventory_log', [
            'variant_id' => $variantId,
            'change_qty' => $change,
            'reason' => $reason,
            'stock_before' => $current ? $current['quantity'] : 0,
            'stock_after' => ($current ? $current['quantity'] : 0) + $change,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate a unique slug.
     */
    private function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $original = $slug;
        $counter = 1;
        while (true) {
            $existing = $this->db->first('shop_product', ['slug' => $slug]);
            if (!$existing || ($excludeId && (int)$existing['id'] === $excludeId)) {
                break;
            }
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }

    // ==============================
    // Category Helpers
    // ==============================

    /**
     * Get category options for select dropdown (indented for hierarchy).
     */
    private function getCategoryOptions(): array
    {
        $categories = $this->db->find('shop_categories', [], 'sort_order ASC, name ASC');
        $tree = $this->buildCategoryTree($categories);
        $options = ['' => '— None —'];
        $this->flattenCategoryTree($tree, $options);
        return $options;
    }

    /**
     * Build a hierarchical tree from a flat list of categories.
     */
    private function buildCategoryTree(array $categories, int $parentId = 0): array
    {
        $branch = [];
        foreach ($categories as $cat) {
            if ((int)$cat['parent_id'] === $parentId && empty($cat['deleted_at'])) {
                $children = $this->buildCategoryTree($categories, (int)$cat['id']);
                if ($children) {
                    $cat['children'] = $children;
                }
                $branch[] = $cat;
            }
        }
        return $branch;
    }

    /**
     * Flatten tree into options array with indentation.
     */
    private function flattenCategoryTree(array $tree, array &$options, int $depth = 0): void
    {
        $indent = str_repeat('—', $depth) . ' ';
        foreach ($tree as $cat) {
            $options[(int)$cat['id']] = $indent . $cat['name'];
            if (!empty($cat['children'])) {
                $this->flattenCategoryTree($cat['children'], $options, $depth + 1);
            }
        }
    }

    /**
     * Helper to output JSON.
     */
    private function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}