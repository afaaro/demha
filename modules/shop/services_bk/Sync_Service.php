<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Services\Http\HttpClient;
use App\Modules\Shop\Services\Http\HttpResponse;
use App\System\Engine\Registry;
use App\System\Library\Database;
use App\System\Library\Logger;

class Sync_Service
{
    protected Database $db;
    protected Logger $logger;
    protected OAuth_Service $oauth;
    protected Listing_Service $listing;
    protected HttpClient $http;

    public function __construct(Registry $registry)
    {
        $this->db      = $registry->get('db');
        $this->logger  = $registry->get('logger');
        $this->oauth   = new OAuth_Service($registry);
        $this->listing = new Listing_Service($registry);
        $this->http    = new HttpClient($this->logger);
    }

    /**
     * Pushes a product to a channel.
     */
    public function syncProduct(int $listingId): bool
    {
        try {
            $listing = $this->listing->getListing($listingId);
            if (!$listing) {
                throw new \RuntimeException('Listing not found.');
            }

            $channel = $this->db->query(
                "SELECT * FROM #__shop_channel WHERE id = ?",
                [$listing['channel_id']]
            )->row;
            if (!$channel) {
                throw new \RuntimeException('Channel not found.');
            }

            // Get product data
            $product = $this->db->query(
                "SELECT * FROM #__shop_product WHERE id = ?",
                [$listing['product_id']]
            )->row;
            if (!$product) {
                throw new \RuntimeException('Product not found for listing.');
            }

            $variants = $this->db->query(
                "SELECT * FROM #__shop_product_variant WHERE product_id = ? AND deleted_at IS NULL",
                [$product['id']]
            )->rows;

            $payload = [
                'name'        => $product['name'],
                'description' => $product['description'],
                'sku'         => $listing['external_sku'] ?? $product['sku'],
                'price'       => $product['price'],
                'variants'    => $variants,
            ];

            // Get access token
            $token = $this->oauth->getAccessToken($channel['id']);
            if (!$token) {
                throw new \RuntimeException('Unable to obtain access token.');
            }

            // Build API endpoint from channel settings
            $settings = json_decode($channel['settings'] ?? '{}', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $apiUrl = $settings['api_base_url'] ?? null;
            if (!$apiUrl) {
                throw new \RuntimeException('API base URL not configured.');
            }

            // Make the HTTP request
            $response = $this->http->post(
                $apiUrl . '/products',
                $payload,
                $this->http->bearer($token)
            );

            if ($response->failed()) {
                $error = $response->error ?: 'Unknown error';
                $this->listing->setListingStatus($listingId, 'error', $error);
                throw new \RuntimeException('Channel API error: ' . $error);
            }

            // Optionally update external_id from response
            $data = $response->toArray();
            if (!empty($data['id'])) {
                $this->listing->updateListing($listingId, [
                    'external_id' => $data['id'],
                    'status'      => 'active',
                    'last_sync'   => date('Y-m-d H:i:s')
                ]);
            } else {
                $this->listing->setListingStatus($listingId, 'active');
            }

            $this->logger->info("Product sync successful for listing $listingId");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("syncProduct failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Pushes inventory to a channel.
     */
    public function syncInventory(int $listingId): bool
    {
        try {
            $listing = $this->listing->getListing($listingId);
            if (!$listing) {
                throw new \RuntimeException('Listing not found.');
            }

            $channel = $this->db->query(
                "SELECT * FROM #__shop_channel WHERE id = ?",
                [$listing['channel_id']]
            )->row;
            if (!$channel) {
                throw new \RuntimeException('Channel not found.');
            }

            $settings = json_decode($channel['settings'] ?? '{}', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $apiUrl = $settings['api_base_url'] ?? null;
            if (!$apiUrl) {
                throw new \RuntimeException('API base URL not configured.');
            }

            $token = $this->oauth->getAccessToken($channel['id']);
            if (!$token) {
                throw new \RuntimeException('Unable to obtain access token.');
            }

            // If listing is for whole product, sync each variant
            if (empty($listing['variant_id'])) {
                $variants = $this->db->query(
                    "SELECT id FROM #__shop_product_variant WHERE product_id = ?",
                    [$listing['product_id']]
                )->rows;
                $success = true;
                foreach ($variants as $v) {
                    $success = $this->syncVariantInventory($v['id'], $channel, $apiUrl, $token) && $success;
                }
                return $success;
            }

            return $this->syncVariantInventory($listing['variant_id'], $channel, $apiUrl, $token);
        } catch (\Exception $e) {
            $this->logger->error("syncInventory failed: " . $e->getMessage());
            return false;
        }
    }

    protected function syncVariantInventory(int $variantId, array $channel, string $apiUrl, string $token): bool
    {
        $inventory = $this->db->query(
            "SELECT quantity, reserved FROM #__shop_inventory WHERE variant_id = ?",
            [$variantId]
        )->row;

        $available = $inventory ? (int) $inventory['quantity'] - (int) $inventory['reserved'] : 0;

        // Usually channel expects a SKU or external ID; we need to find the listing
        $listing = $this->db->query(
            "SELECT id, external_sku FROM #__shop_channel_product
             WHERE variant_id = ? AND channel_id = ?",
            [$variantId, $channel['id']]
        )->row;

        if (!$listing) {
            $this->logger->warning("No listing found for variant $variantId on channel {$channel['id']}");
            return false;
        }

        $payload = [
            'sku'      => $listing['external_sku'] ?? 'SKU_' . $variantId,
            'quantity' => $available,
        ];

        $response = $this->http->put(
            $apiUrl . '/inventory',
            $payload,
            $this->http->bearer($token)
        );

        if ($response->failed()) {
            $this->listing->setListingStatus($listing['id'], 'error', $response->error);
            throw new \RuntimeException('Inventory sync failed: ' . $response->error);
        }

        $this->listing->setListingStatus($listing['id'], 'active');
        $this->logger->info("Inventory sync successful for listing {$listing['id']}");
        return true;
    }

    /**
     * Pulls orders from a channel.
     */
    public function syncOrders(int $channelId): bool
    {
        try {
            $channel = $this->db->query(
                "SELECT * FROM #__shop_channel WHERE id = ?",
                [$channelId]
            )->row;
            if (!$channel) {
                throw new \RuntimeException('Channel not found.');
            }

            $settings = json_decode($channel['settings'] ?? '{}', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $apiUrl = $settings['api_base_url'] ?? null;
            if (!$apiUrl) {
                throw new \RuntimeException('API base URL not configured.');
            }

            $token = $this->oauth->getAccessToken($channelId);
            if (!$token) {
                throw new \RuntimeException('Unable to obtain access token.');
            }

            // Fetch orders from channel
            $response = $this->http->get(
                $apiUrl . '/orders',
                $this->http->bearer($token)
            );

            if ($response->failed()) {
                throw new \RuntimeException('Failed to fetch orders: ' . $response->error);
            }

            $orders = $response->toArray();
            if (!array_is_list($orders)) {
                throw new \RuntimeException('Invalid orders payload from channel.');
            }

            foreach ($orders as $externalOrder) {
                // Check if already imported
                $existing = $this->db->query(
                    "SELECT id FROM #__shop_channel_order
                     WHERE channel_id = ? AND external_order_id = ?",
                    [$channelId, $externalOrder['id']]
                )->row;

                if ($existing) {
                    continue; // already imported
                }

                // Map external order to internal format
                $orderData = [
                    'store_id'      => $channel['store_id'],
                    'customer_id'   => null,
                    'status'        => 'pending',
                    'subtotal'      => $externalOrder['total'] ?? 0,
                    'total'         => $externalOrder['total'] ?? 0,
                    'currency'      => $externalOrder['currency'] ?? 'GBP',
                    'payment_status' => 'pending',
                    'shipping_address' => $externalOrder['shipping_address'] ?? null,
                ];

                // Insert order
                $this->db->query(
                    "INSERT INTO #__shop_orders
                     (store_id, status, subtotal, total, currency, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [
                        $orderData['store_id'],
                        $orderData['status'],
                        $orderData['subtotal'],
                        $orderData['total'],
                        $orderData['currency']
                    ]
                );
                $internalOrderId = $this->db->insert_id();

                // Insert order items
                foreach ($externalOrder['items'] ?? [] as $item) {
                    $this->db->query(
                        "INSERT INTO #__shop_order_items
                         (order_id, sku, name, quantity, price, subtotal)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $internalOrderId,
                            $item['sku'] ?? '',
                            $item['name'] ?? '',
                            $item['quantity'] ?? 1,
                            $item['price'] ?? 0,
                            ($item['price'] ?? 0) * ($item['quantity'] ?? 1)
                        ]
                    );
                }

                // Record mapping
                $this->db->query(
                    "INSERT INTO #__shop_channel_order
                     (channel_id, order_id, external_order_id, status, raw_data)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $channelId,
                        $internalOrderId,
                        $externalOrder['id'],
                        $externalOrder['status'] ?? 'unknown',
                        json_encode($externalOrder, JSON_UNESCAPED_SLASHES)
                    ]
                );

                $this->logger->info("Imported external order {$externalOrder['id']} as internal order $internalOrderId");
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error("syncOrders failed: " . $e->getMessage());
            return false;
        }
    }
}