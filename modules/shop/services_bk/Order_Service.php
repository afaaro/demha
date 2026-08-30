<?php

namespace App\Modules\Shop\Services;

use App\System\Engine\Registry;
use App\System\Library\Database;
use App\System\Library\Logger;
use Exception;
use RuntimeException;


class Order_Service
{
    protected Database $db;
    protected Logger $logger;

    public function __construct(Registry $registry)
    {
        $this->db     = $registry->get('db');
        $this->logger = $registry->get('logger');
    }

    /**
     * Creates an order from a cart.
     */
    public function createOrderFromCart(int $cartId): ?int
    {
        try {
            $this->db->beginTransaction();

            // Get cart details
            $cart = $this->db->query(
                "SELECT * FROM #__shop_cart WHERE id = ?",
                [$cartId]
            )->row;
            if (!$cart) {
                throw new RuntimeException('Cart not found.');
            }

            // Get cart items
            $items = $this->db->query(
                "SELECT ci.*, v.price as variant_price, p.name as product_name, p.sku
                 FROM #__shop_cart_items ci
                 JOIN #__shop_product_variant v ON ci.variant_id = v.id
                 JOIN #__shop_product p ON v.product_id = p.id
                 WHERE ci.cart_id = ?",
                [$cartId]
            )->rows;

            if (empty($items)) {
                throw new RuntimeException('Cart is empty.');
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $price = $item['price'] ?? $item['variant_price'];
                $subtotal += $price * $item['quantity'];
            }

            // Create order
            $this->db->query(
                "INSERT INTO #__shop_orders
                 (store_id, customer_id, status, subtotal, total, currency, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $cart['store_id'],
                    $cart['user_id'],
                    'pending',
                    $subtotal,
                    $subtotal,
                    $cart['currency']
                ]
            );
            $orderId = $this->db->lastInsertId();

            // Insert order items
            foreach ($items as $item) {
                $price = $item['price'] ?? $item['variant_price'];
                $sub = $price * $item['quantity'];
                $this->db->query(
                    "INSERT INTO #__shop_order_items
                     (order_id, variant_id, sku, name, quantity, price, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        $item['variant_id'],
                        $item['sku'],
                        $item['product_name'],
                        $item['quantity'],
                        $price,
                        $sub
                    ]
                );
            }

            // Reserve inventory (optional - could be done later)
            foreach ($items as $item) {
                $this->db->query(
                    "UPDATE #__shop_inventory SET reserved = reserved + ? WHERE variant_id = ?",
                    [$item['quantity'], $item['variant_id']]
                );
            }

            // Clear the cart
            $this->db->query("DELETE FROM #__shop_cart_items WHERE cart_id = ?", [$cartId]);
            $this->db->query("DELETE FROM #__shop_cart WHERE id = ?", [$cartId]);

            $this->db->commit();
            $this->logger->info("Order created: ID $orderId from cart $cartId");
            return $orderId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error("createOrderFromCart failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates order status.
     */
    public function updateOrderStatus(int $orderId, string $newStatus): bool
    {
        try {
            $this->db->query(
                "UPDATE #__shop_orders SET status = ? WHERE id = ?",
                [$newStatus, $orderId]
            );
            $this->logger->info("Order $orderId status changed to $newStatus");
            return true;
        } catch (Exception $e) {
            $this->logger->error("updateOrderStatus failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adds a shipment to an order.
     */
    public function addShipment(int $orderId, array $shipmentData): ?int
    {
        try {
            $this->db->query(
                "INSERT INTO #__shop_shipments
                 (order_id, carrier, service, tracking_number, status, shipped_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $shipmentData['carrier'] ?? null,
                    $shipmentData['service'] ?? null,
                    $shipmentData['tracking_number'] ?? null,
                    $shipmentData['status'] ?? 'pending',
                    $shipmentData['shipped_at'] ?? null
                ]
            );
            $id = $this->db->lastInsertId();
            $this->logger->info("Shipment added: ID $id for order $orderId");
            return $id;
        } catch (Exception $e) {
            $this->logger->error("addShipment failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Records a payment transaction.
     */
    public function addTransaction(int $orderId, array $txnData): ?int
    {
        try {
            $this->db->query(
                "INSERT INTO #__shop_transactions
                 (order_id, gateway, transaction_id, type, amount, currency, status, response)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $txnData['gateway'] ?? null,
                    $txnData['transaction_id'] ?? null,
                    $txnData['type'] ?? 'payment',
                    $txnData['amount'] ?? 0,
                    $txnData['currency'] ?? 'GBP',
                    $txnData['status'] ?? 'pending',
                    json_encode($txnData['response'] ?? [])
                ]
            );
            $id = $this->db->lastInsertId();
            $this->logger->info("Transaction recorded: ID $id for order $orderId");
            return $id;
        } catch (Exception $e) {
            $this->logger->error("addTransaction failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves an order with its items, shipments, and transactions.
     */
    public function getOrder(int $orderId): ?array
    {
        $order = $this->db->query(
            "SELECT * FROM #__shop_orders WHERE id = ? AND deleted_at IS NULL",
            [$orderId]
        )->row;

        if (!$order) {
            return null;
        }

        $order['items'] = $this->db->query(
            "SELECT * FROM #__shop_order_items WHERE order_id = ?",
            [$orderId]
        )->rows;

        $order['shipments'] = $this->db->query(
            "SELECT * FROM #__shop_shipments WHERE order_id = ?",
            [$orderId]
        )->rows;

        $order['transactions'] = $this->db->query(
            "SELECT * FROM #__shop_transactions WHERE order_id = ?",
            [$orderId]
        )->rows;

        return $order;
    }
}