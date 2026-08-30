<?php

namespace App\Modules\Shop\Services\Adapters;

use App\System\Engine\Registry;
use App\System\Library\Database;
use App\System\Library\Logger;
use App\Modules\Shop\Services\ChannelManager;
use App\Modules\Shop\Services\Http\HttpClient;

abstract class Channel
{
    /**
     * @var array<string, mixed> Channel configuration (API keys, tokens, etc.)
     */
    protected array $config = [];

    /**
     * @var int The store ID this channel belongs to.
     */
    protected int $storeId = 0;

    /**
     * @var int The channel ID (from the #__shop_channel table).
     */
    protected int $channelId = 0;

    /**
     * @var ChannelManager The channel manager instance.
     */
    protected ChannelManager $channelManager;

    /**
     * @var Database Database instance for queries.
     */
    protected Database $db;

    /**
     * @var Logger Logger instance for logging errors/debug info.
     */
    protected Logger $logger;

    /**
     * @var Registry The container (for fetching other services).
     */
    protected Registry $registry;

    /**
     * @var HttpClient HTTP client for API requests.
     */
    protected HttpClient $http;

    // ========================================================================
    // Setters (called by ChannelManager)
    // ========================================================================

    /**
     * Set the channel ID (from the #__shop_channel table).
     */
    public function setChannelId(int $channelId): void
    {
        $this->channelId = $channelId;
    }

    /**
     * Set the channel manager instance.
     */
    public function setChannelManager(ChannelManager $manager): void
    {
        $this->channelManager = $manager;
    }

    /**
     * Set the configuration array (decoded from the 'settings' JSON column).
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Set the store ID.
     */
    public function setStoreId(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    /**
     * Inject the database instance.
     */
    public function setDatabase(Database $db): void
    {
        $this->db = $db;
    }

    /**
     * Inject the logger instance.
     */
    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Inject the container instance.
     */
    public function setRegistry(Registry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * Inject the HTTP client instance.
     */
    public function setHttp(HttpClient $http): void
    {
        $this->http = $http;
    }

    // ========================================================================
    // Getters
    // ========================================================================

    /**
     * Get the channel configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the store ID.
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * Get the channel ID.
     */
    public function getChannelId(): int
    {
        return $this->channelId;
    }

    /**
     * Get a specific configuration value.
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    // ========================================================================
    // Abstract Methods – Must be implemented by each channel adapter
    // ========================================================================

    /**
     * Authenticate with the channel's API.
     * Should return true on success, false on failure.
     */
    abstract public function authenticate(): bool;

    /**
     * Get the authorization URL for the initial OAuth2 flow.
     * Returns the URL to redirect the user to for authorization.
     */
    abstract public function getAuthorizationUrl(): string;

    /**
     * Exchange an authorization code for access/refresh tokens.
     * Called by the OAuth2 callback endpoint.
     */
    abstract public function exchangeCodeForToken(string $code): bool;

    /**
     * List a product (or variant) on the channel.
     * Returns an array with 'listing_id', 'url', etc.
     */
    abstract public function listProduct(int $productId, int $variantId = 0): array;

    /**
     * Update the stock for a product/variant on the channel.
     */
    abstract public function updateStock(int $productId, int $variantId = 0, int $stock = 0): bool;

    /**
     * Fetch new orders from the channel since a given date.
     * Returns an array of standardised order data.
     */
    abstract public function fetchOrders(\DateTime $since): array;

    /**
     * Fetch messages from the channel.
     * Returns an array of standardised message data.
     */
    abstract public function fetchMessages(): array;

    /**
     * Send a reply to a message on the channel.
     */
    abstract public function sendMessage(string $messageId, string $reply): bool;

    // ========================================================================
    // Helper Methods (can be overridden or used by child classes)
    // ========================================================================

    /**
     * Update the channel settings in the database.
     * Used to store new tokens, etc.
     */
    protected function updateSettings(): void
    {
        if (!isset($this->channelId) || !isset($this->channelManager)) {
            $this->logger?->error('Cannot update settings: channel ID or manager not set.');
            return;
        }
        $this->channelManager->updateChannelSettings($this->channelId, $this->config);
    }

    /**
     * Log a message (shortcut).
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->$level($message);
        }
    }

    /**
     * Make an HTTP request (to be used by child classes).
     * This is a basic cURL wrapper – child classes can override or use their own.
     */
    protected function httpRequest(string $method, string $url, $data = null, array $headers = []): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $this->log('Invalid URL scheme in channel request: ' . $url, 'error');
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($data !== null) {
            $json = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErrno !== 0) {
            $this->log('cURL error: ' . $curlError, 'error');
            return null;
        }

        if ($httpCode >= 400) {
            $this->log("HTTP error $httpCode: $response", 'error');
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * HTTP POST helper.
     */
    protected function httpPost(string $url, array $data, array $headers = []): ?array
    {
        return $this->httpRequest('POST', $url, $data, $headers);
    }

    /**
     * HTTP PUT helper.
     */
    protected function httpPut(string $url, array $data, array $headers = []): ?array
    {
        return $this->httpRequest('PUT', $url, $data, $headers);
    }

    /**
     * HTTP PATCH helper.
     */
    protected function httpPatch(string $url, array $data, array $headers = []): ?array
    {
        return $this->httpRequest('PATCH', $url, $data, $headers);
    }

    /**
     * HTTP GET helper.
     */
    protected function httpGet(string $url, array $headers = []): ?array
    {
        return $this->httpRequest('GET', $url, null, $headers);
    }

    /**
     * Build a unique SKU for eBay (or other channels) based on product/variant.
     */
    protected function buildSku(int $productId, int $variantId = 0): string
    {
        if ($variantId) {
            return 'product-' . $productId . '-var-' . $variantId;
        }
        return 'product-' . $productId;
    }

    /**
     * Fetch product data (with variant info) from the database.
     */
    protected function getProductData(int $productId, int $variantId = 0): ?array
    {
        $product = $this->db->findOne('shop_product', $productId);
        if (!$product) {
            return null;
        }

        $data = [
            'id'          => $product['id'],
            'name'        => $product['name'],
            'price'       => (float)$product['price'],
            'description' => $product['description'] ?? '',
        ];

        if ($variantId) {
            $variant = $this->db->findOne('shop_product_variant', $variantId);
            if ($variant) {
                // Get stock from inventory
                $inventory = $this->db->query(
                    "SELECT quantity FROM #__shop_inventory WHERE variant_id = ?",
                    [$variantId]
                )->row;
                $data['stock'] = (int)($inventory['quantity'] ?? 0);
                $data['price'] = (float)($variant['price'] ?? $data['price']);
                // Fetch option values for variation specifics if needed
            }
        } else {
            // Sum stock across all variants
            $data['stock'] = (int) $this->db->query(
                "SELECT SUM(i.quantity) FROM #__shop_inventory i
                 JOIN #__shop_product_variant v ON i.variant_id = v.id
                 WHERE v.product_id = ?",
                [$productId]
            )->value ?? 0;
        }

        // Fetch images
        $images = $this->db->find('shop_product_image', ['product_id' => $productId], 'sort_order ASC');
        $data['images'] = array_column($images, 'url');

        return $data;
    }
}