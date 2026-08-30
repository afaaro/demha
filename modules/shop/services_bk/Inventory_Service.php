<?php

namespace App\Modules\Shop\Services;

use App\System\Library\Database;
use App\System\Library\Logger;

/**
 * Manages stock levels, reservations, and inventory logs.
 */
class Inventory_Service {
    protected Database $db;
    protected Logger $logger;

    public function __construct(Registry $registry)
    {
        $this->db     = $registry->get('db');
        $this->logger = $registry->get('logger');
    }

    /**
     * Returns current stock for a variant.
     */
    public function getStock(int $variantId): int
    {
        $row = $this->db->query(
            "SELECT quantity, reserved FROM #__shop_inventory WHERE variant_id = ?",
            [$variantId]
        )->row;

        if (!$row) {
            return 0;
        }

        return (int) $row['quantity'] - (int) $row['reserved'];
    }

    /**
     * Increases stock for a variant (restock / adjustment).
     */
    public function addStock(int $variantId, int $quantity, string $reason = null, int $userId = null, string $reference = null): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        try {
            $this->db->beginTransaction();

            // Get current stock
            $current = $this->db->query(
                "SELECT quantity FROM #__shop_inventory WHERE variant_id = ? FOR UPDATE",
                [$variantId]
            )->row;

            if (!$current) {
                // Create inventory row if missing
                $this->db->query(
                    "INSERT INTO #__shop_inventory (variant_id, quantity) VALUES (?, ?)",
                    [$variantId, $quantity]
                );
                $before = 0;
                $after  = $quantity;
            } else {
                $before = (int) $current['quantity'];
                $after  = $before + $quantity;
                $this->db->query(
                    "UPDATE #__shop_inventory SET quantity = ? WHERE variant_id = ?",
                    [$after, $variantId]
                );
            }

            // Log the change
            $this->logChange($variantId, $quantity, $reason, $userId, $reference, $before, $after);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("addStock failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decreases stock (sale, removal).
     */
    public function removeStock(int $variantId, int $quantity, string $reason = null, int $userId = null, string $reference = null): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        try {
            $this->db->beginTransaction();

            $current = $this->db->query(
                "SELECT quantity FROM #__shop_inventory WHERE variant_id = ? FOR UPDATE",
                [$variantId]
            )->row;

            if (!$current) {
                throw new RuntimeException('Variant has no inventory record.');
            }

            $before = (int) $current['quantity'];
            if ($before < $quantity) {
                throw new RuntimeException('Insufficient stock.');
            }

            $after = $before - $quantity;
            $this->db->query(
                "UPDATE #__shop_inventory SET quantity = ? WHERE variant_id = ?",
                [$after, $variantId]
            );

            $this->logChange($variantId, -$quantity, $reason, $userId, $reference, $before, $after);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("removeStock failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reserves stock for an order.
     */
    public function reserveStock(int $variantId, int $quantity, string $reference = null): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        try {
            $this->db->beginTransaction();

            $current = $this->db->query(
                "SELECT quantity, reserved FROM #__shop_inventory WHERE variant_id = ? FOR UPDATE",
                [$variantId]
            )->row;

            if (!$current) {
                throw new RuntimeException('Variant has no inventory record.');
            }

            $available = (int) $current['quantity'] - (int) $current['reserved'];
            if ($available < $quantity) {
                throw new RuntimeException('Not enough available stock to reserve.');
            }

            $newReserved = (int) $current['reserved'] + $quantity;
            $this->db->query(
                "UPDATE #__shop_inventory SET reserved = ? WHERE variant_id = ?",
                [$newReserved, $variantId]
            );

            // Log reservation as a positive change? Usually we log a "reserved" event
            $this->logChange($variantId, 0, 'Reserved for order', null, $reference, 0, 0, $quantity);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("reserveStock failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Releases reserved stock (e.g., order cancelled).
     */
    public function releaseReserved(int $variantId, int $quantity, string $reference = null): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        try {
            $this->db->beginTransaction();

            $current = $this->db->query(
                "SELECT reserved FROM #__shop_inventory WHERE variant_id = ? FOR UPDATE",
                [$variantId]
            )->row;

            if (!$current) {
                throw new RuntimeException('Variant has no inventory record.');
            }

            $reserved = (int) $current['reserved'];
            if ($reserved < $quantity) {
                throw new RuntimeException('Cannot release more than reserved.');
            }

            $newReserved = $reserved - $quantity;
            $this->db->query(
                "UPDATE #__shop_inventory SET reserved = ? WHERE variant_id = ?",
                [$newReserved, $variantId]
            );

            $this->logChange($variantId, 0, 'Reservation released', null, $reference, 0, 0, -$quantity);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("releaseReserved failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves inventory log entries for a variant.
     */
    public function getStockLogs(int $variantId, int $limit = 100): array
    {
        return $this->db->query(
            "SELECT * FROM #__shop_inventory_log
             WHERE variant_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$variantId, $limit]
        )->rows;
    }

    /**
     * Internal: logs a stock change.
     */
    protected function logChange(int $variantId, int $change, ?string $reason, ?int $userId, ?string $reference, int $before, int $after, int $reservedChange = 0): void
    {
        $this->db->query(
            "INSERT INTO #__shop_inventory_log
             (variant_id, change_qty, reason, user_id, reference, stock_before, stock_after)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$variantId, $change, $reason, $userId, $reference, $before, $after]
        );
    }
}