<?php

use App\Modules\Shop\Controllers\Base;

class ShopCheckout extends Base {
    public function indexAction(): void
    {
        $cartItems = $this->getCartItems();

        if (empty($cartItems)) {
            Notify::error('Your cart is empty.');
            redirect($this->url->to('shop/cart'));
            return;
        }

        $grandTotal = array_sum(array_column($cartItems, 'subtotal'));

        $this->view->assign('title', 'Checkout');

        echo $this->view->inline(function ($view) use ($cartItems, $grandTotal) {
            echo Notify::read();

            echo '<div class="card">';
            echo '<div class="card-header"><h5>Checkout</h5></div>';
            echo '<div class="card-body">';
            echo $view->form->start(['method' => 'POST', 'action' => $view->url->to('shop/checkout/place_order')]);

            echo '<h5>Shipping Information</h5>';
            echo $view->form->input('shipping_name', [
                'label' => 'Full Name',
                'rules' => 'required',
                'placeholder' => 'John Doe',
            ]);
            echo $view->form->textarea('shipping_address', [
                'label' => 'Address',
                'rules' => 'required',
                'rows' => 3,
                'placeholder' => '123 Main St',
            ]);
            echo $view->form->input('shipping_city', [
                'label' => 'City',
                'rules' => 'required',
                'placeholder' => 'London',
            ]);
            echo $view->form->input('shipping_postcode', [
                'label' => 'Postcode',
                'rules' => 'required',
                'placeholder' => 'SW1A 1AA',
            ]);
            echo $view->form->select('payment_method', [
                'label' => 'Payment Method',
                'options' => ['cod' => 'Cash on Delivery', 'paypal' => 'PayPal'],
                'rules' => 'required',
            ]);

            echo '<hr>';
            echo '<h5>Order Summary</h5>';
            echo '<ul class="list-group mb-3">';
            foreach ($cartItems as $item) {
                $productDisplay = htmlspecialchars($item['product_name']);
                if (!empty($item['attributes'])) {
                    $productDisplay .= ' <span class="text-muted small">(' . htmlspecialchars($item['attributes']) . ')</span>';
                }
                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                echo $productDisplay . ' × ' . $item['quantity'];
                echo '<span>£' . number_format($item['subtotal'], 2) . '</span>';
                echo '</li>';
            }
            echo '<li class="list-group-item d-flex justify-content-between"><strong>Total</strong><strong>£' . number_format($grandTotal, 2) . '</strong></li>';
            echo '</ul>';

            echo '<div class="mt-3">';
            echo $view->form->submit('Place Order', ['class' => 'btn btn-success']);
            echo '</div>';

            echo $view->form->end();
            echo '</div></div>';
        }, 'shop/shop');
    }

    public function place_orderAction(): void
    {
        if (!$this->request->isPost()) {
            redirect($this->url->to('shop/checkout'));
            return;
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid form token. Please try again.');
            redirect($this->url->to('shop/checkout'));
            return;
        }

        $cartItems = $this->getCartItems();

        if (empty($cartItems)) {
            Notify::error('Your cart is empty.');
            redirect($this->url->to('shop/cart'));
            return;
        }

        // Validate POST
        $rules = [
            'shipping_name'     => 'required|min:2',
            'shipping_address'  => 'required|min:5',
            'shipping_city'     => 'required|min:2',
            'shipping_postcode' => 'required|min:3',
            'payment_method'    => 'required|in:cod,paypal',
        ];
        $this->form->setRules($rules);
        $errors = $this->form->validate($this->request->post());

        if (!empty($errors)) {
            $this->form->setErrors($errors);
            Notify::error('Please correct the errors below.');
            redirect($this->url->to('shop/checkout'));
            return;
        }

        // Compute total
        $totalAmount = array_sum(array_column($cartItems, 'subtotal'));

        // Get user ID (if logged in)
        $userId = $this->auth->data('id') ?: 0;

        // Insert order
        $orderData = [
            'user_id'          => $userId ?: null,
            'store_id'         => $this->store_id,
            'status'           => 'pending',
            'total_amount'     => $totalAmount,
            'payment_method'   => $this->request->post('payment_method'),
            'shipping_address' => json_encode([
                'name'     => $this->request->post('shipping_name'),
                'address'  => $this->request->post('shipping_address'),
                'city'     => $this->request->post('shipping_city'),
                'postcode' => $this->request->post('shipping_postcode'),
            ]),
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('shop_orders', $orderData);
        $orderId = $this->db->insert_id();

        // Insert order items (snapshot of variant data)
        foreach ($cartItems as $item) {
            // Fetch variant SKU (if available)
            $sku = $item['variant_sku'] ?? 'N/A';
            $this->db->insert('shop_order_items', [
                'order_id'    => $orderId,
                'variant_id'  => $item['variant_id'] ?? null,
                'sku'         => $sku,
                'price'       => $item['price'],
                'quantity'    => $item['quantity'],
                'subtotal'    => $item['subtotal'],
            ]);
        }

        // Clear the cart
        $this->clearCart();

        Notify::success('Your order has been placed! Order #' . $orderId);
        redirect($this->url->to('shop/orders', ['id' => $orderId]));
    }

    /**
     * Get cart items (reusing logic from ShopCart).
     */
    private function getCartItems(): array
    {
        // We can either instantiate ShopCart or duplicate the query.
        // For simplicity, we'll reuse the same query logic as ShopCart::getCartItems().
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

    /**
     * Clear the current user's cart.
     */
    private function clearCart(): void
    {
        $userId = $this->auth->data('id') ?: 0;
        $sessionId = session_id();
        $cart = $this->db->first('shop_cart', ['user_id' => $userId, 'session_id' => $sessionId]);
        if ($cart) {
            $this->db->delete('shop_cart_items', ['cart_id' => $cart['id']]);
        }
    }
}