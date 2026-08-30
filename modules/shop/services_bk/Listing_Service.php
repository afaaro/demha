<?php

namespace App\Modules\Shop\Services;

use App\System\Engine\Registry;
use App\System\Library\Database;
use App\System\Library\Logger;
use Exception;


class Listing_Service
{
    protected Database $db;
    protected Logger $logger;

    public function __construct(Registry $registry)
    {
        $this->db     = $registry->get('db');
        $this->logger = $registry->get('logger');
    }

    /**
     * Creates a channel listing for a product/variant.
     */
    public function createListing(int $channelId, int $productId, ?int $variantId = null, array $externalData = []): ?int
    {
        try {
            $this->db->query(
                "INSERT INTO #__shop_channel_product
                 (channel_id, product_id, variant_id, external_id, external_sku, external_url, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $channelId,
                    $productId,
                    $variantId,
                    $externalData['external_id'] ?? null,
                    $externalData['external_sku'] ?? null,
                    $externalData['external_url'] ?? null,
                    $externalData['status'] ?? 'pending'
                ]
            );
            $id = $this->db->insert_id();
            $this->logger->info("Listing created: ID $id for channel $channelId");
            return $id;
        } catch (Exception $e) {
            $this->logger->error("createListing failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates an existing listing (e.g., sync status).
     */
    public function updateListing(int $listingId, array $data): bool
    {
        try {
            $set = [];
            $params = [];
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $column = $this->sanitizeColumn($key);
                    if ($column === '') {
                        continue;
                    }
                    $set[] = "`{$column}` = ?";
                    $params[] = $value;
                }
            }
            $params[] = $listingId;
            if (!empty($set)) {
                $this->db->query(
                    "UPDATE #__shop_channel_product SET " . implode(', ', $set) . " WHERE id = ?",
                    $params
                );
            }
            $this->logger->info("Listing updated: ID $listingId");
            return true;
        } catch (Exception $e) {
            $this->logger->error("updateListing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a listing by ID.
     */
    public function getListing(int $listingId): ?array
    {
        return $this->db->query(
            "SELECT * FROM #__shop_channel_product WHERE id = ?",
            [$listingId]
        )->row ?: null;
    }

    /**
     * Returns all listings for a given channel.
     */
    public function getListingsByChannel(int $channelId): array
    {
        return $this->db->query(
            "SELECT * FROM #__shop_channel_product WHERE channel_id = ?",
            [$channelId]
        )->rows;
    }

    /**
     * Marks a listing as active / ended / error.
     */
    public function setListingStatus(int $listingId, string $status, ?string $error = null): bool
    {
        return $this->updateListing($listingId, [
            'status' => $status,
            'sync_error' => $error,
            'last_sync' => date('Y-m-d H:i:s')
        ]);
    }

    private function sanitizeColumn(string $column): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $column);
    }
}