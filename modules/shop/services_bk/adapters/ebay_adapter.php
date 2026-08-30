<?php

namespace App\Modules\Shop\Services\Adapters;

use App\Modules\Shop\Services\Http\OauthTrait;
use App\Modules\Shop\Services\Http\HttpResponse;
use App\Modules\Shop\Services\Adapters\Channel;

class EbayAdapter extends Channel {
    use OauthTrait;

    protected bool $sandbox = true;

    // Sandbox endpoints
    protected string $tokenUrl = 'https://api.sandbox.ebay.com/identity/v1/oauth2/token';
    protected string $authUrl  = 'https://auth.sandbox.ebay.com/oauth2/authorize';

    // Production endpoints – uncomment when ready
    // protected string $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    // protected string $authUrl  = 'https://auth.ebay.com/oauth2/authorize';

    // ========================================================================
    // Abstract Method Implementations
    // ========================================================================

    protected function getApiBase(): string
    {
        return $this->sandbox
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }

    public function authenticate(): bool
    {
        return $this->ensureAuthenticated();
    }

    public function getAuthorizationUrl(): string
    {
        $params = [
            'client_id'     => $this->config['client_id'] ?? '',
            'response_type' => 'code',
            'redirect_uri'  => $this->config['redirect_uri'] ?? $this->getCallbackUrl(),
            'scope'         => 'https://api.ebay.com/oauth/api_scope',
            'state'         => (string)$this->channelId,
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): bool
    {
        $decodedCode = urldecode($code);
        $redirectUri = $this->config['redirect_uri'] ?? $this->getCallbackUrl();

        $this->logger->debug('Token URL: ' . $this->tokenUrl);
        $this->logger->debug('Redirect URI set: ' . ($redirectUri !== '' ? 'yes' : 'no'));

        if (empty($redirectUri)) {
            $this->log('Exchange: redirect_uri is empty.', 'error');
            return false;
        }

        $response = $this->http->postForm($this->tokenUrl, [
            'grant_type'   => 'authorization_code',
            'code'         => $decodedCode,
            'redirect_uri' => $redirectUri,
        ], $this->basicHeaders());

        if ($response->failed()) {
            $this->log('Exchange failed: ' . $response->error, 'error');
            return false;
        }

        $data = $response->toArray();
        if (empty($data['access_token'])) {
            $this->log('No access token in exchange response.', 'error');
            return false;
        }

        $this->storeTokens($data);
        $this->log('Exchange successful.');
        return true;
    }

    protected function refreshToken(): bool
    {
        if (!$this->hasRefreshToken()) {
            return false;
        }

        $response = $this->http->postForm($this->tokenUrl, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshTokenValue(),
        ], $this->basicHeaders());

        if ($response->failed()) {
            $this->log('Refresh failed: ' . $response->error, 'error');
            return false;
        }

        $data = $response->toArray();
        if (empty($data['access_token'])) {
            $this->log('No access token in refresh response.', 'error');
            return false;
        }

        $this->storeTokens($data);
        $this->log('Token refreshed successfully.');
        return true;
    }

    /**
     * Helper for Basic Auth headers (client_id:client_secret).
     */
    protected function basicHeaders(): array
    {
        return [
            'Authorization: Basic ' . base64_encode(
                $this->config['client_id'] . ':' . $this->config['client_secret']
            ),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];
    }

    // ========================================================================
    // Business Methods (Listing, Stock, Orders, Messages)
    // ========================================================================

    public function listProduct(int $productId, int $variantId = 0): array
    {
        $product = $this->getProductData($productId, $variantId);
        if (!$product) {
            return ['error' => 'Product not found'];
        }

        $sku = $this->buildSku($productId, $variantId);
        $inventoryItem = $this->createInventoryItem($sku, $product);
        if (!$inventoryItem) {
            return ['error' => 'Failed to create inventory item'];
        }

        $offer = $this->createOffer($sku, $product);
        if (!$offer) {
            return ['error' => 'Failed to create offer'];
        }

        $this->publishOffer($offer['offerId']);

        return [
            'listing_id' => $offer['offerId'],
            'sku'        => $sku,
            'url'        => $this->getListingUrl($offer['offerId']),
        ];
    }

    public function updateStock(int $productId, int $variantId = 0, int $stock = 0): bool
    {
        $sku = $this->buildSku($productId, $variantId);
        return $this->updateInventoryItem($sku, ['availability' => ['quantity' => $stock]]);
    }

    public function fetchOrders(\DateTime $since): array
    {
        $url = $this->getApiBase() . '/sell/order/v1/order';
        $sinceStr = $since->format('Y-m-d\TH:i:s\Z');
        $query = http_build_query([
            'filter' => "creationdate:[{$sinceStr}..]",
            'limit'  => 50,
            'sort'   => 'creationdate asc',
        ]);

        $response = $this->http->get(
            $url . '?' . $query,
            $this->bearerHeaders(['Accept: application/json'])
        );

        if ($response->failed()) {
            $this->log('Fetch orders failed: ' . $response->error, 'error');
            return [];
        }

        $data = $response->toArray();
        if (empty($data['orders'])) {
            return [];
        }

        return array_map([$this, 'mapOrder'], $data['orders']);
    }

    public function fetchMessages(): array
    {
        // eBay Trading API is XML – not implemented in this example.
        // Could use the REST Notification API if configured.
        return [];
    }

    public function sendMessage(string $messageId, string $reply): bool
    {
        // Requires Trading API – not implemented.
        return false;
    }

    // ========================================================================
    // Internal eBay API Methods
    // ========================================================================

    protected function createInventoryItem(string $sku, array $product): ?array
    {
        $url = $this->getApiBase() . '/sell/inventory/v1/inventory_item/' . $sku;
        $payload = [
            'availability' => [
                'shipToLocationAvailability' => [
                    [
                        'quantity' => (int)($product['stock'] ?? 0),
                        'shipToLocation' => ['country' => 'US'],
                    ]
                ]
            ],
            'condition' => 'NEW',
            'product' => [
                'title' => $product['name'],
                'description' => $product['description'] ?? '',
                'imageUrls' => $product['images'] ?? [],
                'gtin' => $product['gtin'] ?? '',
                'brand' => $product['brand'] ?? '',
            ],
            'sku' => $sku,
        ];

        $response = $this->http->put(
            $url,
            $payload,
            $this->bearerHeaders(['Content-Type: application/json'])
        );

        if ($response->failed()) {
            $this->log('Create inventory item failed: ' . $response->error, 'error');
            return null;
        }

        return ['sku' => $sku];
    }

    protected function updateInventoryItem(string $sku, array $data): bool
    {
        $url = $this->getApiBase() . '/sell/inventory/v1/inventory_item/' . $sku;
        $response = $this->http->patch(
            $url,
            $data,
            $this->bearerHeaders(['Content-Type: application/json'])
        );

        if ($response->failed()) {
            $this->log('Update inventory failed: ' . $response->error, 'error');
            return false;
        }

        return true;
    }

    protected function createOffer(string $sku, array $product): ?array
    {
        $url = $this->getApiBase() . '/sell/inventory/v1/offer';
        $payload = [
            'sku' => $sku,
            'marketplaceId' => 'EBAY_US',
            'format' => 'FIXED_PRICE',
            'price' => [
                'currency' => 'USD',
                'value' => (string)($product['price'] ?? 0),
            ],
            'quantity' => (int)($product['stock'] ?? 0),
            'listingDescription' => $product['description'] ?? '',
        ];

        $response = $this->http->post(
            $url,
            $payload,
            $this->bearerHeaders(['Content-Type: application/json'])
        );

        if ($response->failed()) {
            $this->log('Create offer failed: ' . $response->error, 'error');
            return null;
        }

        $data = $response->toArray();
        return isset($data['offerId']) ? ['offerId' => $data['offerId']] : null;
    }

    protected function publishOffer(string $offerId): bool
    {
        $url = $this->getApiBase() . '/sell/inventory/v1/offer/' . $offerId . '/publish';
        $response = $this->http->post(
            $url,
            [],
            $this->bearerHeaders(['Content-Type: application/json'])
        );

        if ($response->failed()) {
            $this->log('Publish offer failed: ' . $response->error, 'error');
            return false;
        }

        return true;
    }

    protected function getListingUrl(string $offerId): string
    {
        // eBay does not return a direct URL; construct a typical one.
        return 'https://www.ebay.com/itm/' . $offerId;
    }

    protected function mapOrder(array $ebayOrder): array
    {
        return [
            'external_id'   => $ebayOrder['orderId'] ?? '',
            'customer_name' => $ebayOrder['buyer']['username'] ?? 'Guest',
            'email'         => $ebayOrder['buyer']['email'] ?? '',
            'total'         => (float)($ebayOrder['pricingSummary']['total']['value'] ?? 0),
            'currency'      => $ebayOrder['pricingSummary']['total']['currency'] ?? 'USD',
            'status'        => $ebayOrder['orderPaymentStatus'] ?? 'pending',
            'items'         => array_map(fn($item) => [
                'sku'      => $item['sku'] ?? '',
                'quantity' => $item['quantity'] ?? 0,
                'price'    => (float)($item['price']['value'] ?? 0),
                'title'    => $item['title'] ?? '',
            ], $ebayOrder['lineItems'] ?? []),
            'created_at'    => $ebayOrder['creationDate'] ?? '',
        ];
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    protected function getCallbackUrl(): string
    {
        if ($this->registry->has('url')) {
            return $this->registry->get('url')->to('shop/admin/channel/callback', [], true);
        }
        return '';
    }
}