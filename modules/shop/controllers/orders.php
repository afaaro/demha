<?php
use Modules\Shop\Controllers\Base;
use System\Library\Notify;

class ShopOrders extends Base
{
    public function indexAction()
    {
        if (!$this->auth->check()) {
            Notify::error("You must be logged in to view your orders.");
                redirect($this->url->to('user/login'));
            return;
        }

        // Fetch user orders
        $orders = $this->db->query("
            SELECT o.*, COUNT(oi.id) AS item_count
            FROM #__shop_orders o
            LEFT JOIN #__shop_order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            ", [(int) $this->auth->data('id', 0)])->rows;

        $this->doc->setTitle("My Orders");
        echo opentable("My Orders");

        if (empty($orders)) {
            echo '<p>You have no orders yet.</p>';
        } else {
            echo '<table class="table table-bordered">';
            echo '<thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Items</th>
                        <th></th>
                    </tr>
                  </thead>
                  <tbody>';

            foreach ($orders as $order) {
                echo '<tr>
                        <td>#' . (int)$order['id'] . '</td>
                        <td>' . date('Y-m-d H:i', strtotime($order['created_at'])) . '</td>
                        <td>' . ucfirst($order['status']) . '</td>
                        <td>£' . number_format((float) ($order['total_amount'] ?? $order['total'] ?? 0), 2) . '</td>
                        <td>' . (int)$order['item_count'] . '</td>
                        <td>
                            <a href="' . $this->url->to('shop/orders/view', ['id' => (int) $order['id']]) . '" class="btn btn-sm text-bg-primary">View</a>
                            <a href="' . $this->url->to('shop/orders/reorder', ['id' => (int) $order['id']]) . '" class="btn btn-sm text-bg-success" onclick="return confirm(\'Reorder all items from this order?\');">Order Again</a>
                        </td>
                      </tr>';
            }

            echo '</tbody></table>';
        }

        echo closetable();
    }

    public function viewAction()
    {
        if (!$this->auth->check()) {
            Notify::error("You must be logged in to view this order.");
                redirect($this->url->to('user/login'));
            return;
        }

            $orderId = (int) ($this->request->route('id') ?? $this->request->get('id', 'int', 0));

        $order = $this->db->query("
            SELECT * FROM #__shop_orders WHERE id = ? AND user_id = ?
            ", [$orderId, (int) $this->auth->data('id', 0)])->row;

        if (!$order) {
            Notify::error("Order not found.");
                redirect($this->url->to('shop/orders'));
            return;
        }

        $items = $this->db->query("
                SELECT oi.*, p.name AS product_name, v.sku AS variation_name
            FROM #__shop_order_items oi
                LEFT JOIN #__shop_product_variant v ON v.id = oi.variant_id
                LEFT JOIN #__shop_product p ON p.id = v.product_id
            WHERE oi.order_id = ?
            ", [$orderId])->rows;

        $shipping = json_decode($order['shipping_address'], true);

        Template::setTitle("Order #{$orderId}");
        echo opentable("Order #{$orderId}");

        echo '<p><strong>Status:</strong> ' . ucfirst($order['status']) . '</p>';
        echo '<p><strong>Placed On:</strong> ' . date('Y-m-d H:i', strtotime($order['created_at'])) . '</p>';
            echo '<p><strong>Total:</strong> £' . number_format((float) ($order['total_amount'] ?? $order['total'] ?? 0), 2) . '</p>';
        echo '<p><strong>Shipping:</strong><br>'
            . htmlspecialchars($shipping['name'] ?? '') . '<br>'
            . htmlspecialchars($shipping['address'] ?? '') . '<br>'
            . htmlspecialchars($shipping['phone'] ?? '') . '</p>';

        echo '<h4>Order Items</h4>';
        echo '<table class="table table-bordered">';
        echo '<thead><tr>
                <th>Product</th>
                <th>Variation</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
              </tr></thead><tbody>';

        foreach ($items as $item) {
            $lineTotal = $item['price'] * $item['quantity'];
            echo '<tr>
                    <td>' . htmlspecialchars($item['product_name']) . '</td>
                    <td>' . htmlspecialchars($item['variation_name'] ?? '-') . '</td>
                    <td>£' . number_format($item['price'], 2) . '</td>
                    <td>' . (int)$item['quantity'] . '</td>
                    <td>£' . number_format($lineTotal, 2) . '</td>
                  </tr>';
        }

        echo '</tbody></table>';
            echo '<form action="' . $this->url->to('shop/orders/reorder', ['id' => $orderId]) . '" method="post">';
        echo '<button type="submit" class="btn btn-success">Reorder All Items</button>';
        echo '</form>';
            echo '<a href="' . $this->url->to('shop/orders') . '" class="btn btn-secondary">Back to Orders</a>';

        echo closetable();
        Template::theme()->render();
    }

    public function reorderAction()
    {
        if (!$this->auth->check()) {
            Notify::error("You must be logged in to reorder.");
                redirect($this->url->to('user/login'));
            return;
        }

            if (!$this->request->isPost()) {
                Notify::error('Invalid reorder request.');
                redirect($this->url->to('shop/orders'));
                return;
            }

            $orderId = (int) ($this->request->route('id') ?? $this->request->get('id', 'int', 0));
        $orderItems = $this->db->query("
                SELECT variant_id, price, quantity
            FROM #__shop_order_items
            WHERE order_id = ?
            ", [$orderId])->rows;

        if (!$orderItems) {
            Notify::error("No items found for this order.");
                redirect($this->url->to('shop/orders'));
            return;
        }

        $cart = new ShopCart($this->registry); // Assuming ShopCart is accessible
        $cartId = $cart->getUserCartId();

        foreach ($orderItems as $item) {
            // Check if this product+variation is already in cart
            $existing = $this->db->query("
                SELECT id, quantity FROM #__shop_cart_items
                    WHERE cart_id=? AND variant_id=?
                ", [$cartId, $item['variant_id']])->row;

            if ($existing) {
                // Increase quantity
                $this->db->update('shop_cart_items', [
                    'quantity' => $existing['quantity'] + $item['quantity']
                ], ['id' => $existing['id']]);
            } else {
                // Insert new item
                $this->db->insert('shop_cart_items', [
                    'cart_id' => $cartId,
                        'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
            }
        }

        Notify::success("Items from Order #{$orderId} have been added to your cart.");
            redirect($this->url->to('shop/cart'));
    }
}