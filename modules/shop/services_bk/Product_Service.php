<?php

namespace App\Modules\Shop\Services;

use App\System\Engine\Registry;
use App\System\Library\Database;
use App\System\Library\Logger;
use Exception;

class Product_Service
{
    protected Database $db;
    protected Logger $logger;

    public function __construct(Registry $registry)
    {
        $this->db     = $registry->get('db');
        $this->logger = $registry->get('logger');
    }

    /**
     * Creates a new product.
     */
    public function createProduct(array $data): ?int
    {
        try {
            $this->db->query(
                "INSERT INTO #__shop_product
                 (store_id, category_id, name, slug, sku, description, price, currency, tax_class, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['store_id'],
                    $data['category_id'] ?? null,
                    $data['name'],
                    $data['slug'],
                    $data['sku'],
                    $data['description'] ?? null,
                    $data['price'] ?? 0,
                    $data['currency'] ?? 'GBP',
                    $data['tax_class'] ?? null,
                    $data['status'] ?? 'draft'
                ]
            );

            $id = $this->db->lastInsertId();

            if (!empty($data['images'])) {
                $this->addImages($id, $data['images']);
            }

            if (!empty($data['options'])) {
                $this->assignOptionGroups($id, $data['options']);
            }

            $this->logger->info("Product created: ID $id");
            return $id;
        } catch (Exception $e) {
            $this->logger->error("createProduct failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates an existing product.
     */
    public function updateProduct(int $productId, array $data): bool
    {
        try {
            $set = [];
            $params = [];
            foreach ($data as $key => $value) {
                if ($key !== 'product_id' && $key !== 'images' && $key !== 'options') {
                    $column = $this->sanitizeColumn($key);
                    if ($column === '') {
                        continue;
                    }
                    $set[] = "`{$column}` = ?";
                    $params[] = $value;
                }
            }
            $params[] = $productId;

            if (!empty($set)) {
                $this->db->query(
                    "UPDATE #__shop_product SET " . implode(', ', $set) . " WHERE id = ?",
                    $params
                );
            }

            if (isset($data['images'])) {
                $this->updateImages($productId, $data['images']);
            }

            if (isset($data['options'])) {
                $this->assignOptionGroups($productId, $data['options']);
            }

            $this->logger->info("Product updated: ID $productId");
            return true;
        } catch (Exception $e) {
            $this->logger->error("updateProduct failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a product by ID.
     */
    public function getProduct(int $productId): ?array
    {
        $row = $this->db->query(
            "SELECT * FROM #__shop_product WHERE id = ? AND deleted_at IS NULL",
            [$productId]
        )->row;

        if ($row) {
            $row['images'] = $this->getImages($productId);
            $row['options'] = $this->getOptionGroups($productId);
        }

        return $row ?: null;
    }

    /**
     * Soft-deletes a product.
     */
    public function deleteProduct(int $productId): bool
    {
        try {
            $this->db->query(
                "UPDATE #__shop_product SET deleted_at = NOW() WHERE id = ?",
                [$productId]
            );
            $this->logger->info("Product deleted (soft): ID $productId");
            return true;
        } catch (Exception $e) {
            $this->logger->error("deleteProduct failed: " . $e->getMessage());
            return false;
        }
    }

    // ---- Variants ----

    public function addVariant(int $productId, array $data): ?int
    {
        try {
            $this->db->query(
                "INSERT INTO #__shop_product_variant
                 (product_id, sku, barcode, price, cost, weight, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $productId,
                    $data['sku'],
                    $data['barcode'] ?? null,
                    $data['price'] ?? null,
                    $data['cost'] ?? null,
                    $data['weight'] ?? null,
                    $data['status'] ?? 'active'
                ]
            );
            $variantId = $this->db->lastInsertId();

            // Link option values if provided
            if (!empty($data['option_values'])) {
                foreach ($data['option_values'] as $valueId) {
                    $this->db->query(
                        "INSERT INTO #__shop_product_variant_option (variant_id, value_id) VALUES (?, ?)",
                        [$variantId, $valueId]
                    );
                }
            }

            $this->logger->info("Variant added: ID $variantId for product $productId");
            return $variantId;
        } catch (Exception $e) {
            $this->logger->error("addVariant failed: " . $e->getMessage());
            return null;
        }
    }

    public function getVariants(int $productId): array
    {
        return $this->db->query(
            "SELECT v.*,
                    GROUP_CONCAT(vo.value_id) as option_value_ids
             FROM #__shop_product_variant v
             LEFT JOIN #__shop_product_variant_option vo ON v.id = vo.variant_id
             WHERE v.product_id = ? AND v.deleted_at IS NULL
             GROUP BY v.id",
            [$productId]
        )->rows;
    }

    // ---- Helpers ----

    protected function addImages(int $productId, array $images): void
    {
        $sort = 0;
        foreach ($images as $img) {
            $this->db->query(
                "INSERT INTO #__shop_product_image (product_id, url, alt, sort_order, is_primary)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $productId,
                    $img['url'],
                    $img['alt'] ?? null,
                    $img['sort_order'] ?? $sort++,
                    $img['is_primary'] ?? 0
                ]
            );
        }
    }

    protected function getImages(int $productId): array
    {
        return $this->db->query(
            "SELECT * FROM #__shop_product_image WHERE product_id = ? ORDER BY sort_order",
            [$productId]
        )->rows;
    }

    protected function updateImages(int $productId, array $images): void
    {
        // Remove existing images
        $this->db->query("DELETE FROM #__shop_product_image WHERE product_id = ?", [$productId]);
        $this->addImages($productId, $images);
    }

    protected function assignOptionGroups(int $productId, array $groupIds): void
    {
        // Remove existing assignments
        $this->db->query("DELETE FROM #__shop_product_option_group WHERE product_id = ?", [$productId]);
        foreach ($groupIds as $groupId) {
            $this->db->query(
                "INSERT INTO #__shop_product_option_group (product_id, group_id) VALUES (?, ?)",
                [$productId, $groupId]
            );
        }
    }

    protected function getOptionGroups(int $productId): array
    {
        return $this->db->query(
            "SELECT group_id FROM #__shop_product_option_group WHERE product_id = ?",
            [$productId]
        )->rows;
    }

    private function sanitizeColumn(string $column): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $column);
    }
}

// CREATE TABLE #__shop_product_listing (

//     id INT AUTO_INCREMENT PRIMARY KEY,

//     product_id INT NOT NULL,

//     channel_id INT NOT NULL,

//     listing_id VARCHAR(150),

//     url TEXT,

//     created_at DATETIME,

//     updated_at DATETIME,

//     UNIQUE KEY(product_id, channel_id)

// );