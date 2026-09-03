<?php

use System\Engine\Controller;

class ShopCart extends Controller
{
    /**
     * Display cart contents
     */
    public function indexAction(): void
    {
        $cart = $this->getCartItems();
        $total = array_sum(array_column($cart, 'subtotal'));
        $itemCount = count($cart);

        $this->view->assign('title', 'Shopping Cart');

        echo $this->view->inline(function ($view) use ($cart, $total, $itemCount) {
            ?>
            <div class="container py-4">
                <h2 class="mb-4">Shopping Cart</h2>

                <?php if (empty($cart)): ?>
                    <div class="alert alert-info">Your cart is empty.</div>
                    <a href="<?= $this->url->to('shop') ?>" class="btn btn-primary">Continue Shopping</a>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $item): ?>
                                    <?php
                                    $img = $this->url->asset('assets/img/no-image.png');
                                    $productName = escape($item['product_name']);
                                    if (!empty($item['attributes'])) {
                                        $productName .= " – <span class='text-muted'>" . escape($item['attributes']) . "</span>";
                                    }
                                    ?>
                                <tr>
                                    <td>
                                        <img src="<?= $img ?>" width="50" class="me-2" alt="<?= $productName ?>">
                                        <?= $productName ?>
                                    </td>
                                    <td>£<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <form method="POST" action="<?= $this->url->to('shop/cart/update') ?>" class="d-inline">
                                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" style="width:60px;" class="form-control form-control-sm d-inline-block">
                                            <button type="submit" class="btn btn-sm btn-secondary">Update</button>
                                        </form>
                                    </td>
                                    <td>£<?= number_format($item['subtotal'], 2) ?></td>
                                    <td>
                                        <a href="<?= $this->url->to('shop/cart/remove', ['id' => $item['cart_id']]) ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Remove item?')">Remove</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th>£<?= number_format($total, 2) ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= $this->url->to('shop') ?>" class="btn btn-secondary">Continue Shopping</a>
                        <a href="<?= $this->url->to('shop/checkout') ?>" class="btn btn-primary">Proceed to Checkout</a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }, 'main');
    }

    /**
     * Add item to cart (AJAX or standard POST)
     */
    public function addAction(): void
    {
        if (!$this->request->isPost()) {
            http_response_code(405);
            $this->json(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        $productId = (int) $this->request->post('product_id', 'int', 0);
        $quantity = (int) $this->request->post('quantity', 'int', 1);
        $variantId = (int) $this->request->post('variant_id', 'int', 0);

        if ($quantity < 1) {
            $quantity = 1;
        }

        // Get product details with prefix
        $product = $this->db->query("
            SELECT * FROM #__shop_product WHERE id = ? AND status = 'active' AND deleted_at IS NULL
        ", [$productId])->row;

        if (!$product) {
            if ($this->request->isAjax()) {
                $this->json(['status' => 'error', 'message' => 'Product not available']);
            } else {
                \System\Library\Notify::error('Product not available');
                redirect($this->url->to('shop'));
            }
            return;
        }

        // Determine price
        $price = (float)$product['price'];
        if ($variantId > 0) {
            $variant = $this->db->query("
                SELECT * FROM #__shop_product_variant WHERE id = ? AND product_id = ? AND deleted_at IS NULL
            ", [$variantId, $productId])->row;

            if ($variant && $variant['price'] !== null && $variant['price'] > 0) {
                $price = (float)$variant['price'];
            } else {
                // If variant exists but price is null, use product price
                // but still continue
            }
        }

        // Get or create cart
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();

        $cart = $this->db->query("
            SELECT * FROM #__shop_cart WHERE user_id = ? AND session_id = ?
        ", [$userId, $sessionId])->row;

        if (!$cart) {
            $this->db->query("
                INSERT INTO #__shop_cart (user_id, session_id, currency, expires_at, created_at)
                VALUES (?, ?, 'GBP', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
            ", [$userId, $sessionId]);
            $cartId = $this->db->insert_id();
        } else {
            $cartId = $cart['id'];
        }

        // Check if item already exists (by variant_id)
        $existing = $this->db->query("
            SELECT * FROM #__shop_cart_items WHERE cart_id = ? AND variant_id = ?
        ", [$cartId, $variantId])->row;

        if ($existing) {
            $newQty = (int)$existing['quantity'] + $quantity;
            $this->db->query("
                UPDATE #__shop_cart_items SET quantity = ? WHERE id = ?
            ", [$newQty, $existing['id']]);
        } else {
            $this->db->query("
                INSERT INTO #__shop_cart_items (cart_id, variant_id, quantity, price, added_at)
                VALUES (?, ?, ?, ?, NOW())
            ", [$cartId, $variantId, $quantity, $price]);
        }

        // Response
        if ($this->request->isAjax()) {
            $this->json([
                'status' => 'success',
                'message' => 'Item added to cart',
                'cart_count' => $this->getCartCount()
            ]);
        } else {
            \System\Library\Notify::success('Item added to cart');
            redirect($this->url->to('shop/cart'));
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateAction(): void
    {
        if (!$this->request->isPost()) {
            redirect($this->url->to('shop/cart'));
            return;
        }

        $cartItemId = (int) $this->request->post('cart_id', 'int', 0);
        $quantity = (int) $this->request->post('quantity', 'int', 1);

        if ($cartItemId <= 0 || $quantity < 1) {
            \System\Library\Notify::error('Invalid quantity');
            redirect($this->url->to('shop/cart'));
            return;
        }

        // Verify ownership
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();

        $item = $this->db->query("
            SELECT ci.*, c.user_id, c.session_id
            FROM #__shop_cart_items ci
            JOIN #__shop_cart c ON c.id = ci.cart_id
            WHERE ci.id = ?
        ", [$cartItemId])->row;

        if (!$item) {
            \System\Library\Notify::error('Item not found');
            redirect($this->url->to('shop/cart'));
            return;
        }

        // Check ownership (user or session)
        if (($userId > 0 && (int)$item['user_id'] !== $userId) || ($userId == 0 && $item['session_id'] !== $sessionId)) {
            \System\Library\Notify::error('Unauthorized');
            redirect($this->url->to('shop/cart'));
            return;
        }

        $this->db->query("
            UPDATE #__shop_cart_items SET quantity = ? WHERE id = ?
        ", [$quantity, $cartItemId]);

        \System\Library\Notify::success('Cart updated');
        redirect($this->url->to('shop/cart'));
    }

    /**
     * Remove item from cart
     */
    public function removeAction(): void
    {
        $cartItemId = (int) $this->request->get('id', 'int', 0);

        if ($cartItemId <= 0) {
            \System\Library\Notify::error('Invalid item');
            redirect($this->url->to('shop/cart'));
            return;
        }

        // Verify ownership
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();

        $item = $this->db->query("
            SELECT ci.*, c.user_id, c.session_id
            FROM #__shop_cart_items ci
            JOIN #__shop_cart c ON c.id = ci.cart_id
            WHERE ci.id = ?
        ", [$cartItemId])->row;

        if (!$item) {
            \System\Library\Notify::error('Item not found');
            redirect($this->url->to('shop/cart'));
            return;
        }

        // Check ownership
        if (($userId > 0 && (int)$item['user_id'] !== $userId) || ($userId == 0 && $item['session_id'] !== $sessionId)) {
            \System\Library\Notify::error('Unauthorized');
            redirect($this->url->to('shop/cart'));
            return;
        }

        $this->db->query("DELETE FROM #__shop_cart_items WHERE id = ?", [$cartItemId]);

        \System\Library\Notify::success('Item removed from cart');
        redirect($this->url->to('shop/cart'));
    }

    /**
     * Get cart item count (for AJAX header)
     */
    public function countAction(): void
    {
        $count = $this->getCartCount();
        $this->json(['count' => $count]);
    }

    /**
     * Get cart items with product and variant details
     */
    protected function getCartItems(): array
    {
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();

        $cart = $this->db->query("
            SELECT id FROM #__shop_cart WHERE user_id = ? AND session_id = ?
        ", [$userId, $sessionId])->row;

        if (!$cart) {
            return [];
        }

        $cartId = (int)$cart['id'];

        $rows = $this->db->query("
            SELECT 
                ci.id AS cart_id,
                ci.quantity,
                ci.price,
                (ci.price * ci.quantity) AS subtotal,
                p.id AS product_id,
                p.name AS product_name,
                v.id AS variant_id,
                v.sku AS variant_sku,
                GROUP_CONCAT(
                    CONCAT(og.name, ': ', ov.value) 
                    ORDER BY og.sort_order, og.id, ov.sort_order, ov.id
                    SEPARATOR ', '
                ) AS attributes
            FROM #__shop_cart_items ci
            JOIN #__shop_product_variant v ON v.id = ci.variant_id
            JOIN #__shop_product p ON p.id = v.product_id
            LEFT JOIN #__shop_product_variant_option pvo ON pvo.variant_id = v.id
            LEFT JOIN #__shop_option_value ov ON ov.id = pvo.value_id
            LEFT JOIN #__shop_option_group og ON og.id = ov.group_id
            WHERE ci.cart_id = ?
            GROUP BY ci.id
            ORDER BY ci.added_at ASC
        ", [$cartId])->rows;

        return $rows;
    }

    /**
     * Get total number of items in cart
     */
    protected function getCartCount(): int
    {
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();

        $cart = $this->db->query("
            SELECT id FROM #__shop_cart WHERE user_id = ? AND session_id = ?
        ", [$userId, $sessionId])->row;

        if (!$cart) {
            return 0;
        }

        $result = $this->db->query("
            SELECT SUM(quantity) AS total FROM #__shop_cart_items WHERE cart_id = ?
        ", [$cart['id']])->row;

        return (int)($result['total'] ?? 0);
    }

    /**
     * Helper to output JSON
     */
    protected function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}