<?php

use App\Modules\Shop\Controllers\Base;

class ShopCart extends Base {
    public function indexAction(): void
    {
        $cart = $this->getCartItems();
        $total = array_sum(array_column($cart, 'subtotal'));

        $this->view->assign('title', 'Shopping Cart');

        echo $this->view->inline(function ($view) use ($cart, $total) {
            echo '<h2>Shopping Cart</h2>';
            if (empty($cart)) {
                echo '<div class="alert alert-info">Your cart is empty.</div>';
                echo '<a href="' . $this->url->to('shop') . '" class="btn btn-primary">Continue Shopping</a>';
            } else {
                echo '<table class="table table-striped">';
                echo '<thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>';
                echo '<tbody>';
                foreach ($cart as $item) {
                    // Use fallback image (no primary_image available)
                    $img = $this->url->asset('assets/img/no-image.png');

                    // Build product name with variation attributes
                    $productName = htmlspecialchars($item['product_name']);
                    if (!empty($item['attributes'])) {
                        $productName .= ' – <span class="text-muted">' . htmlspecialchars($item['attributes']) . '</span>';
                    }

                    echo '<tr>';
                    echo '<td><img src="' . $img . '" width="50" class="me-2"> ' . $productName . '</td>';
                    echo '<td>£' . number_format($item['price'], 2) . '</td>';
                    echo '<td>
                            <form method="POST" action="' . $this->url->to('shop/cart/update') . '" class="d-inline">
                                <input type="hidden" name="cart_id" value="' . $item['cart_id'] . '">
                                <input type="number" name="quantity" value="' . $item['quantity'] . '" min="1" style="width:60px;">
                                <button type="submit" class="btn btn-sm btn-secondary">Update</button>
                            </form>
                        </td>';
                    echo '<td>£' . number_format($item['subtotal'], 2) . '</td>';
                    echo '<td><a href="' . $this->url->to('shop/cart/remove', ['id' => $item['cart_id']]) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Remove item?\')">Remove</a></td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '<tfoot><tr><th colspan="3" class="text-end">Total:</th><th>£' . number_format($total, 2) . '</th><th></th></tr></tfoot>';
                echo '</table>';
                echo '<a href="' . $this->url->to('shop') . '" class="btn btn-secondary">Continue Shopping</a> ';
                echo '<a href="' . $this->url->to('shop/checkout') . '" class="btn btn-primary">Proceed to Checkout</a>';
            }
        }, 'shop/shop');
    }

    public function addAction(): void
    {
        if (!$this->request->isPost()) {
            http_response_code(405);
            return;
        }

        $productId = (int) $this->request->post('product_id');
        $quantity = (int) $this->request->post('quantity', 'int', 1);
        $variantId = (int) $this->request->post('variant_id', 'int', 0);

        // Get product details
        $product = $this->db->findOne('shop_product', $productId);
        if (!$product || $product['status'] !== 'active') {
            echo json_encode(['status' => 'error', 'message' => 'Product not available']);
            return;
        }

        // Determine price (check variant)
        $price = (float)$product['price'];
        if ($variantId) {
            $variant = $this->db->findOne('shop_product_variant', $variantId);
            if ($variant && $variant['product_id'] == $productId && $variant['price'] !== null) {
                $price = (float)$variant['price'];
            }
        }

        // Add to cart (using session if not logged in)
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();

        // Find existing cart for user or session
        $cart = $this->db->first('shop_cart', ['user_id' => $userId, 'session_id' => $sessionId]);
        if (!$cart) {
            $this->db->insert('shop_cart', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'store_id' => $product['store_id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $cartId = $this->db->insert_id();
        } else {
            $cartId = $cart['id'];
        }

        // Check if item already exists (by variant_id)
        $existing = $this->db->first('shop_cart_items', [
            'cart_id' => $cartId,
            'variant_id' => $variantId,
        ]);
        if ($existing) {
            $this->db->update('shop_cart_items', ['quantity' => $existing['quantity'] + $quantity], ['id' => $existing['id']]);
        } else {
            $this->db->insert('shop_cart_items', [
                'cart_id' => $cartId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $price,
            ]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Item added to cart']);
    }

    public function updateAction(): void
    {
        if (!$this->request->isPost()) {
            redirect($this->url->to('shop/cart'));
            return;
        }
        $cartId = (int) $this->request->post('cart_id');
        $quantity = (int) $this->request->post('quantity');
        if ($quantity < 1) $quantity = 1;
        $this->db->update('shop_cart_items', ['quantity' => $quantity], ['id' => $cartId]);
        redirect($this->url->to('shop/cart'));
    }

    public function removeAction(): void
    {
        $id = (int) $this->request->route('id');
        $this->db->delete('shop_cart_items', ['id' => $id]);
        redirect($this->url->to('shop/cart'));
    }

    public function countAction(): void
    {
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();
        $cart = $this->db->first('shop_cart', ['user_id' => $userId, 'session_id' => $sessionId]);
        $count = 0;
        if ($cart) {
            $count = (int) $this->db->query("SELECT SUM(quantity) AS total FROM #__shop_cart_items WHERE cart_id = ?", [$cart['id']])->value;
        }
        echo $count;
    }

    /**
     * Get cart items with product and variant details, including attributes.
     */
    protected function getCartItems(): array
    {
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();
        $cart = $this->db->first('shop_cart', ['user_id' => $userId, 'session_id' => $sessionId]);
        if (!$cart) return [];

        return $this->db->query("
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
        ", [$cart['id']])->rows;
    }
}