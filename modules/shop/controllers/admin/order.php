<?php
// modules/shop/controllers/admin/OrderController.php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;
use System\Library\Pagination;

class ShopAdminOrder extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * List all orders with filters and pagination
     */
    public function indexAction(): void
    {
        $page = (int)$this->request->get('page', 'int', 1);
        $limit = (int)$this->request->get('limit', 'int', 20);
        $status = $this->request->get('status', 'string', '');
        $platform = $this->request->get('platform', 'string', '');
        $dateFrom = $this->request->get('date_from', 'string', '');
        $dateTo = $this->request->get('date_to', 'string', '');
        $search = $this->request->get('search', 'string', '');

        $where = ["o.deleted_at IS NULL"];
        $params = [];

        if (!empty($status)) {
            $where[] = "o.status = ?";
            $params[] = $status;
        }

        if (!empty($platform)) {
            $where[] = "o.platform = ?";
            $params[] = $platform;
        }

        if (!empty($dateFrom)) {
            $where[] = "DATE(o.created_at) >= ?";
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where[] = "DATE(o.created_at) <= ?";
            $params[] = $dateTo;
        }

        if (!empty($search)) {
            $where[] = "(o.order_number LIKE ? OR o.billing_name LIKE ? OR o.billing_address LIKE ? OR o.id IN (SELECT order_id FROM #__shop_order_items WHERE name LIKE ?))";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereClause = implode(" AND ", $where);
        $offset = ($page - 1) * $limit;

        // Get total count
        $countResult = $this->db->query(
            "SELECT COUNT(*) as total FROM #__shop_orders o WHERE {$whereClause}",
            $params
        );
        $total = (int)($countResult->row['total'] ?? 0);

        // Get orders with item counts
        $query = "SELECT o.*, 
                         (SELECT COUNT(*) FROM #__shop_order_items WHERE order_id = o.id) as item_count,
                         (SELECT SUM(quantity) FROM #__shop_order_items WHERE order_id = o.id) as total_items
                  FROM #__shop_orders o
                  WHERE {$whereClause}
                  ORDER BY o.created_at DESC
                  LIMIT {$offset}, {$limit}";

        $result = $this->db->query($query, $params);

        $pagination = new Pagination(
            $total,
            $page,
            $limit,
            $this->url->cleanRequest(['page' => '{page}'])
        );

        echo $this->view->render([
            'orders' => $result->rows,
            'pagination' => $pagination,
            'filters' => [
                'status' => $status,
                'platform' => $platform,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search
            ],
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ], 'admin');
    }

    /**
     * View order details
     */
    public function viewAction(): void
    {
        $id = (int)$this->request->get('id', 'int', 0);

        if (!$id) {
            Notify::error('Order ID is required');
            $this->response->redirect($this->url->to('shop/admin/order'));
            return;
        }

        $order = $this->getOrderWithDetails($id);

        if (!$order) {
            Notify::error('Order not found');
            $this->response->redirect($this->url->to('shop/admin/order'));
            return;
        }

        // Get order items with variant info
        $items = $this->db->query("
            SELECT oi.*, 
                   v.sku as variant_sku,
                   p.name as product_name,
                   p.sku as product_sku
            FROM #__shop_order_items oi
            LEFT JOIN #__shop_product_variant v ON v.id = oi.variant_id
            LEFT JOIN #__shop_product p ON p.id = v.product_id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ", [$id])->rows;

        // Get shipments
        $shipments = $this->db->query("
            SELECT * FROM #__shop_shipments WHERE order_id = ? ORDER BY created_at DESC
        ", [$id])->rows;

        // Get transactions
        $transactions = $this->db->query("
            SELECT * FROM #__shop_transactions WHERE order_id = ? ORDER BY created_at DESC
        ", [$id])->rows;

        echo $this->view->render([
            'order' => $order,
            'items' => $items,
            'shipments' => $shipments,
            'transactions' => $transactions,
            'statuses' => $this->getOrderStatuses(),
            'payment_statuses' => ['pending', 'paid', 'failed', 'refunded'],
            'carriers' => $this->getAvailableCarriers(),
            'shipping_statuses' => ['pending', 'processing', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled']
        ], 'admin');
    }

    /**
     * Update order status (AJAX or POST)
     */
    public function updateStatusAction(): void
    {
        $id = (int)$this->request->get('id', 'int', 0);
        $status = $this->request->post('status', 'string', '');

        if (!$id || empty($status)) {
            if ($this->request->isAjax()) {
                $this->json(['success' => false, 'message' => 'Invalid request']);
            } else {
                Notify::error('Invalid request');
                $this->response->redirect($this->url->to('shop/admin/order'));
            }
            return;
        }

        try {
            $this->db->query("START TRANSACTION");

            $oldStatus = $this->db->query(
                "SELECT status FROM #__shop_orders WHERE id = ?",
                [$id]
            )->row['status'] ?? '';

            $this->db->query(
                "UPDATE #__shop_orders SET status = ?, updated_at = NOW() WHERE id = ?",
                [$status, $id]
            );

            $this->logOrderStatusChange($id, $oldStatus, $status);

            $this->db->query("COMMIT");

            if ($this->request->isAjax()) {
                $this->json([
                    'success' => true,
                    'message' => 'Order status updated successfully',
                    'new_status' => $status,
                    'status_label' => ucfirst($status),
                    'status_color' => $this->getStatusColor($status)
                ]);
            } else {
                Notify::success('Order status updated successfully');
                $this->response->redirect($this->url->to('shop/admin/order/view', ['id' => $id]));
            }

        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            if ($this->request->isAjax()) {
                $this->json(['success' => false, 'message' => $e->getMessage()]);
            } else {
                Notify::error($e->getMessage());
                $this->response->redirect($this->url->to('shop/admin/order/view', ['id' => $id]));
            }
        }
    }

    /**
     * Create fulfillment/shipment
     */
    public function createFulfillmentAction(): void
    {
        $id = (int)$this->request->get('id', 'int', 0);

        if (!$id) {
            Notify::error('Order ID is required');
            $this->response->redirect($this->url->to('shop/admin/order'));
            return;
        }

        $order = $this->getOrderWithDetails($id);
        if (!$order) {
            Notify::error('Order not found');
            $this->response->redirect($this->url->to('shop/admin/order'));
            return;
        }

        if ($this->request->isPost()) {
            try {
                $carrier = $this->request->post('carrier', 'string', '');
                $trackingNumber = $this->request->post('tracking_number', 'string', '');
                $shippedDate = $this->request->post('shipped_date', 'string', date('Y-m-d H:i:s'));
                $service = $this->request->post('service', 'string', '');

                if (empty($carrier)) {
                    throw new \Exception('Carrier is required');
                }

                $this->db->query("START TRANSACTION");

                // Create shipment record
                $this->db->query("
                    INSERT INTO #__shop_shipments 
                    (order_id, carrier, service, tracking_number, status, shipped_at, created_at) 
                    VALUES (?, ?, ?, ?, 'shipped', ?, NOW())
                ", [$id, $carrier, $service, $trackingNumber, $shippedDate]);

                // Update order status to shipped
                $this->db->query("
                    UPDATE #__shop_orders SET status = 'shipped', updated_at = NOW() WHERE id = ?
                ", [$id]);

                $this->db->query("COMMIT");

                Notify::success('Fulfillment created successfully');
                $this->response->redirect($this->url->to('shop/admin/order/view', ['id' => $id]));

            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                Notify::error($e->getMessage());
                $this->response->redirect($this->url->to('shop/admin/order/view', ['id' => $id]));
            }
        }

        echo $this->view->render([
            'order' => $order,
            'carriers' => $this->getAvailableCarriers()
        ], 'admin');
    }

    /**
     * Update shipment tracking
     */
    public function updateShipmentAction(): void
    {
        $id = (int)$this->request->get('id', 'int', 0);
        $shipmentId = (int)$this->request->post('shipment_id', 'int', 0);
        $trackingNumber = $this->request->post('tracking_number', 'string', '');
        $status = $this->request->post('status', 'string', '');

        if (!$id || !$shipmentId) {
            Notify::error('Invalid request');
            $this->response->redirect($this->url->to('shop/admin/order/view', ['id' => $id]));
            return;
        }

        try {
            $this->db->query("
                UPDATE #__shop_shipments 
                SET tracking_number = ?, status = ?, updated_at = NOW() 
                WHERE id = ? AND order_id = ?
            ", [$trackingNumber, $status, $shipmentId, $id]);

            Notify::success('Shipment updated successfully');
        } catch (\Exception $e) {
            Notify::error($e->getMessage());
        }

        $this->response->redirect($this->url->to('shop/admin/order/view', ['id' => $id]));
    }

    /**
     * Export orders to CSV
     */
    public function exportAction(): void
    {
        $status = $this->request->get('status', 'string', '');
        $dateFrom = $this->request->get('date_from', 'string', '');
        $dateTo = $this->request->get('date_to', 'string', '');
        $platform = $this->request->get('platform', 'string', '');

        $where = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($status)) {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if (!empty($platform)) {
            $where[] = "platform = ?";
            $params[] = $platform;
        }

        if (!empty($dateFrom)) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
        }

        $whereClause = implode(" AND ", $where);
        $orders = $this->db->query(
            "SELECT * FROM #__shop_orders WHERE {$whereClause} ORDER BY created_at DESC",
            $params
        )->rows;

        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Order #', 'Customer', 'Email', 'Phone', 'Total', 'Currency',
            'Status', 'Payment Status', 'Payment Method', 'Platform',
            'Items', 'Date', 'Shipping Name', 'Shipping Address',
            'Billing Name', 'Billing Address', 'Notes'
        ]);

        foreach ($orders as $order) {
            $items = $this->db->query(
                "SELECT COUNT(*) as count, SUM(quantity) as qty FROM #__shop_order_items WHERE order_id = ?",
                [$order['id']]
            )->row;

            fputcsv($output, [
                $order['order_number'],
                $order['billing_name'] ?? 'Guest',
                $order['billing_address'] ?? '',
                $order['billing_phone'] ?? '',
                number_format($order['total'], 2),
                $order['currency'] ?? 'GBP',
                $order['status'],
                $order['payment_status'],
                $order['payment_method'] ?? '',
                $order['platform'] ?? 'web',
                ($items['qty'] ?? 0) . ' items',
                date('Y-m-d H:i', strtotime($order['created_at'])),
                $order['shipping_name'] ?? '',
                $order['shipping_address'] ?? '',
                $order['billing_name'] ?? '',
                $order['billing_address'] ?? '',
                $order['notes'] ?? ''
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Cancel/delete order
     */
    public function deleteAction(): void
    {
        $id = (int)$this->request->get('id', 'int', 0);

        if (!$id) {
            Notify::error('Order ID is required');
            $this->response->redirect($this->url->to('shop/admin/order'));
            return;
        }

        try {
            // Check if order can be cancelled
            $order = $this->getOrderWithDetails($id);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            if (in_array($order['status'], ['shipped', 'completed', 'refunded'])) {
                throw new \Exception('Cannot cancel an order that is shipped, completed, or refunded');
            }

            $this->db->query("START TRANSACTION");

            $this->db->query("
                UPDATE #__shop_orders SET status = 'cancelled', deleted_at = NOW(), updated_at = NOW() WHERE id = ?
            ", [$id]);

            // Restore stock if needed
            $this->restoreOrderStock($id);

            $this->db->query("COMMIT");
            Notify::success('Order cancelled successfully');

        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            Notify::error($e->getMessage());
        }

        $this->response->redirect($this->url->to('shop/admin/order'));
    }

    /**
     * Bulk status update
     */
    public function bulkAction(): void
    {
        $action = $this->request->post('action', 'string', '');
        $orderIds = $this->request->post('order_ids', 'array', []);

        if (empty($orderIds) || empty($action)) {
            Notify::error('No orders selected or action missing');
            $this->response->redirect($this->url->to('shop/admin/order'));
            return;
        }

        $count = 0;
        foreach ($orderIds as $id) {
            try {
                $this->db->query("
                    UPDATE #__shop_orders SET status = ?, updated_at = NOW() WHERE id = ?
                ", [$action, (int)$id]);
                $count++;
            } catch (\Exception $e) {
                // Skip failed updates
                continue;
            }
        }

        Notify::success("Updated {$count} orders to '{$action}'");
        $this->response->redirect($this->url->to('shop/admin/order'));
    }

    /**
     * Get order with all details
     */
    private function getOrderWithDetails(int $orderId): ?array
    {
        $order = $this->db->query("
            SELECT * FROM #__shop_orders WHERE id = ? AND deleted_at IS NULL
        ", [$orderId])->row;

        if (!$order) {
            return null;
        }

        // Decode JSON fields if needed
        if (!empty($order['billing_address']) && is_string($order['billing_address'])) {
            $order['billing_address'] = json_decode($order['billing_address'], true);
        }
        if (!empty($order['shipping_address']) && is_string($order['shipping_address'])) {
            $order['shipping_address'] = json_decode($order['shipping_address'], true);
        }

        return $order;
    }

    /**
     * Log order status change
     */
    private function logOrderStatusChange(int $orderId, string $oldStatus, string $newStatus): void
    {
        $userId = $this->registry->has('auth') ? $this->registry->get('auth')->data('id') : 0;

        $this->db->query("
            INSERT INTO #__shop_status_history 
            (order_id, old_status, new_status, user_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ", [$orderId, $oldStatus, $newStatus, $userId]);
    }

    /**
     * Restore order stock when cancelled
     */
    private function restoreOrderStock(int $orderId): void
    {
        $items = $this->db->query("
            SELECT variant_id, quantity FROM #__shop_order_items WHERE order_id = ?
        ", [$orderId])->rows;

        foreach ($items as $item) {
            if ($item['variant_id']) {
                $this->db->query("
                    UPDATE #__shop_inventory 
                    SET quantity = quantity + ?, updated_at = NOW() 
                    WHERE variant_id = ?
                ", [(int)$item['quantity'], (int)$item['variant_id']]);

                // Log stock restoration
                $this->db->query("
                    INSERT INTO #__shop_inventory_log 
                    (variant_id, change_qty, reason, reference, created_at) 
                    VALUES (?, ?, 'Order cancelled', ?, NOW())
                ", [(int)$item['variant_id'], (int)$item['quantity'], 'ORDER-' . $orderId]);
            }
        }
    }

    /**
     * Get available order statuses
     */
    private function getOrderStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded'
        ];
    }

    /**
     * Get available carriers
     */
    private function getAvailableCarriers(): array
    {
        return [
            'Royal Mail' => 'Royal Mail',
            'DPD' => 'DPD',
            'DHL' => 'DHL',
            'FedEx' => 'FedEx',
            'UPS' => 'UPS',
            'Hermes' => 'Hermes',
            'Evri' => 'Evri',
            'Parcelforce' => 'Parcelforce',
            'Yodel' => 'Yodel',
            'Other' => 'Other'
        ];
    }

    /**
     * Get status color for badges
     */
    public function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => 'warning',
            'paid' => 'info',
            'processing' => 'primary',
            'shipped' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Helper to output JSON
     */
    private function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get order status helper (available in views)
     */
    public function getStatusLabel(string $status): string
    {
        $labels = $this->getOrderStatuses();
        return $labels[$status] ?? ucfirst($status);
    }
}