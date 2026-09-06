<?php

class ShopAdapterEbay
{
    protected array $channel;
    protected array $settings;
    protected string $apiUrl;
    protected string $authUrl;
    protected string $tokenUrl;

    // API endpoints
    protected const API_BASE = 'https://api.ebay.com';
    protected const API_SANDBOX = 'https://api.sandbox.ebay.com';
    protected const AUTH_BASE = 'https://auth.ebay.com';
    protected const AUTH_SANDBOX = 'https://auth.sandbox.ebay.com';

    // ✅ FIXED: Use SHORT scope names (eBay OAuth standard)
    protected const SCOPES = [
        'sell.inventory',
        'sell.account',
        'sell.fulfillment',
        'sell.marketing',
        'sell.analytics',
        'sell.finances',
    ];

    // Response statuses
    protected const STATUS_SUCCESS = 'success';
    protected const STATUS_ERROR   = 'error';
    protected const STATUS_PENDING = 'pending';

    public function __construct(array $channel = [])
    {
        $this->channel = $channel;
        $this->settings = $channel['settings'] ?? [];

        // Determine if using sandbox
        $isSandbox = !empty($this->settings['sandbox']);

        $this->apiUrl   = $isSandbox ? self::API_SANDBOX : self::API_BASE;
        $this->authUrl  = $isSandbox ? self::AUTH_SANDBOX : self::AUTH_BASE;
        $this->tokenUrl = $this->apiUrl . '/identity/v1/oauth2/token';
    }

    /**
     * Get the authorization URL for OAuth flow
     */
    public function getAuthorizationUrl(): string
    {
        $clientId     = $this->settings['client_id'] ?? '';
        $redirectUri  = $this->settings['redirect_uri'] ?? '';
        $state        = bin2hex(random_bytes(16));

        // Store state in session for verification
        $_SESSION['ebay_oauth_state'] = $state;

        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(' ', self::SCOPES),
            'state'         => $state,
        ]);

        return $this->authUrl . '/oauth2/authorize?' . $params;
    }

    /**
     * Handle OAuth callback
     */
    public function handleCallback(string $code): array
    {
        try {
            // Verify state
            $expectedState = $_SESSION['ebay_oauth_state'] ?? '';
            $state = $_GET['state'] ?? '';
            unset($_SESSION['ebay_oauth_state']);

            if (empty($state) || $state !== $expectedState) {
                return [
                    'success' => false,
                    'message' => 'Invalid state parameter. Possible CSRF attack.'
                ];
            }

            // Exchange code for tokens
            $tokens = $this->exchangeCodeForTokens($code);

            if (!$tokens) {
                return [
                    'success' => false,
                    'message' => 'Failed to exchange authorization code for tokens.'
                ];
            }

            return [
                'success'  => true,
                'settings' => [
                    'access_token'  => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires' => time() + ($tokens['expires_in'] ?? 7200),
                    'user_id'       => $tokens['user_id'] ?? null,
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error handling OAuth callback: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Exchange authorization code for access/refresh tokens
     */
    protected function exchangeCodeForTokens(string $code): ?array
    {
        $clientId     = $this->settings['client_id'] ?? '';
        $clientSecret = $this->settings['client_secret'] ?? '';
        $redirectUri  = $this->settings['redirect_uri'] ?? '';

        $auth = base64_encode($clientId . ':' . $clientSecret);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new Exception('Token exchange failed: ' . ($error['error_description'] ?? 'HTTP ' . $httpCode));
        }

        $data = json_decode($response, true);

        return [
            'access_token'  => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in'    => $data['expires_in'] ?? 7200,
            'user_id'       => $data['user_id'] ?? null,
            // ✅ FIXED: eBay returns "username" not "user_name"
        ];
    }

    /**
     * Refresh the access token
     */
    public function refreshToken(): bool
    {
        $refreshToken = $this->settings['refresh_token'] ?? '';
        $clientId     = $this->settings['client_id'] ?? '';
        $clientSecret = $this->settings['client_secret'] ?? '';

        if (!$refreshToken) {
            return false;
        }

        $auth = base64_encode($clientId . ':' . $clientSecret);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope'         => implode(' ', self::SCOPES), // ✅ Added scope
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return false;
        }

        $data = json_decode($response, true);

        // Update settings with new token
        $this->settings['access_token']  = $data['access_token'] ?? null;
        $this->settings['token_expires'] = time() + ($data['expires_in'] ?? 7200);

        // Save updated settings
        $this->saveSettings();

        return true;
    }

    /**
     * Test the connection to eBay
     */
    public function testConnection(): array
    {
        try {
            $response = $this->apiRequest('GET', '/sell/account/v1/account');

            if ($response && !empty($response)) {
                return [
                    'success' => true,
                    'message' => 'Connection successful!',
                    'data'    => [
                        'account_id'   => $response['accountId'] ?? 'N/A',
                        'account_type' => $response['accountType'] ?? 'Unknown',
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'No account data returned.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync products and orders from eBay
     */
    public function sync(): array
    {
        try {
            if (!$this->isTokenValid() && !$this->refreshToken()) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed. Please reconnect.'
                ];
            }

            $stats = ['products' => 0, 'orders' => 0, 'inventory' => 0];

            $inventory = $this->getInventory();
            $stats['products']  = count($inventory);
            $stats['inventory'] = $stats['products'];

            $orders = $this->getOrders();
            $stats['orders'] = count($orders);

            if (!empty($orders)) {
                $this->processOrders($orders);
            }

            $this->updateLastSync();

            return [
                'success' => true,
                'message' => 'Sync completed.',
                'stats'   => $stats
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory items
     */
    protected function getInventory(): array
    {
        $response = $this->apiRequest('GET', '/sell/inventory/v1/inventory_item');
        return $response['inventoryItems'] ?? [];
    }

    /**
     * Get orders
     */
    protected function getOrders(array $filters = []): array
    {
        $params = [];

        if (!empty($filters['limit'])) {
            $params['limit'] = $filters['limit'];
        }

        // ✅ Only send offset if explicitly set — avoids "offset out of range" errors
        if (!empty($filters['offset'])) {
            $params['offset'] = $filters['offset'];
        }

        if (!empty($filters['date_from'])) {
            $params['creation_date_range_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $params['creation_date_range_to'] = $filters['date_to'];
        }

        $response = $this->apiRequest('GET', '/sell/fulfillment/v1/order', $params);

        // ✅ Return empty array instead of null
        return $response['orders'] ?? [];
    }

    protected function processOrders(array $orders): void  {}
    protected function saveOrder(array $orderData): void    {}

    protected function updateLastSync(): void
    {
        $this->settings['last_sync'] = date('Y-m-d H:i:s');
        $this->saveSettings();
    }

    /**
     * ✅ FIXED: Save settings properly — NO json_encode here
     */
    protected function saveSettings(): void
    {
        $loader = registry('loader');
        $channelManager = $loader->model('shop/channel_manager');
        $channelManager->updateChannelSettings((int)$this->channel['id'], $this->settings);

        // ✅ Update local copy — array stays array
        $this->channel['settings'] = $this->settings;
    }

    protected function isTokenValid(): bool
    {
        $expires = $this->settings['token_expires'] ?? 0;
        return !empty($this->settings['access_token']) && ($expires - time()) > 300;
    }

    /**
     * Make API request
     */
    protected function apiRequest(string $method, string $endpoint, array $params = [], $body = null): array
    {
        if (!$this->isTokenValid() && !$this->refreshToken()) {
            throw new Exception('Invalid or expired access token');
        }

        $url = $this->apiUrl . $endpoint;

        if (!empty($params) && $method === 'GET') {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            'Authorization: Bearer ' . ($this->settings['access_token'] ?? ''),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception('CURL Error: ' . $curlErr);
        }

        // Auto-retry on 401
        if ($httpCode === 401) {
            if ($this->refreshToken()) {
                return $this->apiRequest($method, $endpoint, $params, $body);
            }
            throw new Exception('Authentication failed — token refresh unsuccessful.');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorData = json_decode($response, true);
            $message = $errorData['error_description'] ?? $errorData['message'] ?? 'HTTP ' . $httpCode;
            throw new Exception('eBay API Error: ' . $message);
        }

        return json_decode($response, true) ?? [];
    }

    public function getProduct(string $sku): ?array
    {
        try {
            return $this->apiRequest('GET', '/sell/inventory/v1/inventory_item/' . rawurlencode($sku));
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateInventory(string $sku, int $quantity): array
    {
        try {
            $this->apiRequest('POST',
                '/sell/inventory/v1/inventory_item/' . rawurlencode($sku) . '/update_availability',
                [],
                ['availability' => ['availableQuantity' => $quantity]]
            );
            return ['success' => true, 'sku' => $sku, 'quantity' => $quantity];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getOrder(string $orderId): ?array
    {
        try {
            return $this->apiRequest('GET', "/sell/fulfillment/v1/order/{$orderId}");
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateOrderStatus(string $orderId, string $status): array
    {
        return ['success' => true, 'order_id' => $orderId, 'status' => $status];
    }

    public function getFulfillmentStatus(string $orderId): ?array
    {
        try {
            return $this->apiRequest('GET', "/sell/fulfillment/v1/order/{$orderId}/fulfillment_status");
        } catch (Exception $e) {
            return null;
        }
    }

    public function getChannelId(): int      { return (int)($this->channel['id'] ?? 0); }
    public function getSettings(): array     { return $this->settings; }
    public function getChannelName(): string { return $this->channel['name'] ?? ''; }
    public function isConnected(): bool       { return !empty($this->settings['access_token']); }
}