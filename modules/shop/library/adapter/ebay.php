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
    
    // Scopes needed for basic operations
    protected const SCOPES = [
        'https://api.ebay.com/oauth/api_scope/sell.inventory',
        'https://api.ebay.com/oauth/api_scope/sell.account',
        'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
        'https://api.ebay.com/oauth/api_scope/sell.marketing',
        'https://api.ebay.com/oauth/api_scope/sell.analytics',
        'https://api.ebay.com/oauth/api_scope/sell.finances',
    ];
    
    // Response statuses
    protected const STATUS_SUCCESS = 'success';
    protected const STATUS_ERROR = 'error';
    protected const STATUS_PENDING = 'pending';

    public function __construct(array $channel = [])
    {
        $logger = registry('logger');
        $this->channel = $channel;

        $this->settings = $channel['settings'] ?? [];

        // Determine if using sandbox
        $isSandbox = $this->settings['sandbox'] ?? false;
        
        $this->apiUrl = $isSandbox ? self::API_SANDBOX : self::API_BASE;
        $this->authUrl = $isSandbox ? self::AUTH_SANDBOX : self::AUTH_BASE;
        $this->tokenUrl = $this->apiUrl . '/identity/v1/oauth2/token';
    }

    /**
     * Get the authorization URL for OAuth flow
     */
    public function getAuthorizationUrl(): string
    {
        $clientId = $this->settings['client_id'] ?? '';
        $redirectUri = $this->settings['redirect_uri'] ?? '';
        $state = bin2hex(random_bytes(16));
        
        // Store state in session for verification
        $_SESSION['ebay_oauth_state'] = $state;
        
        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
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
            $state = $_GET['state'] ?? '';
            $expectedState = $_SESSION['ebay_oauth_state'] ?? '';
            unset($_SESSION['ebay_oauth_state']);
            
            if ($state !== $expectedState) {
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
                'success' => true,
                'settings' => [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires' => time() + ($tokens['expires_in'] ?? 7200),
                    'user_id' => $tokens['user_id'] ?? null,
                    'user_name' => $tokens['user_name'] ?? null,
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
        $clientId = $this->settings['client_id'] ?? '';
        $clientSecret = $this->settings['client_secret'] ?? '';
        $redirectUri = $this->settings['redirect_uri'] ?? '';
        
        $auth = base64_encode($clientId . ':' . $clientSecret);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new Exception('Token exchange failed: ' . ($error['error_description'] ?? 'Unknown error'));
        }
        
        $data = json_decode($response, true);
        
        return [
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 7200,
            'user_id' => $data['user_id'] ?? null,
            'user_name' => $data['username'] ?? null,
        ];
    }

    /**
     * Refresh the access token
     */
    public function refreshToken(): bool
    {
        $refreshToken = $this->settings['refresh_token'] ?? '';
        $clientId = $this->settings['client_id'] ?? '';
        $clientSecret = $this->settings['client_secret'] ?? '';
        
        if (!$refreshToken) {
            return false;
        }
        
        $auth = base64_encode($clientId . ':' . $clientSecret);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $auth,
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
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
        $this->settings['access_token'] = $data['access_token'] ?? null;
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
            // Get user info to test connection
            $response = $this->apiRequest('GET', '/sell/account/v1/user');
            
            if ($response && isset($response['accountId'])) {
                return [
                    'success' => true,
                    'message' => 'Connection successful!',
                    'data' => [
                        'account_id' => $response['accountId'],
                        'account_type' => $response['accountType'] ?? 'Unknown',
                    ]
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to get user information.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync products from eBay
     */
    public function sync(): array
    {
        try {
            // Check token validity
            if (!$this->isTokenValid()) {
                if (!$this->refreshToken()) {
                    return [
                        'success' => false,
                        'message' => 'Authentication failed. Please reconnect the channel.'
                    ];
                }
            }
            
            $stats = [
                'products' => 0,
                'orders' => 0,
                'inventory' => 0,
            ];
            
            // Get inventory items
            $inventory = $this->getInventory();
            $stats['products'] = count($inventory ?? []);
            $stats['inventory'] = $stats['products'];
            
            // Get orders
            $orders = $this->getOrders();
            $stats['orders'] = count($orders ?? []);
            
            // Process orders
            if (!empty($orders)) {
                $this->processOrders($orders);
            }
            
            // Update last sync time
            $this->updateLastSync();
            
            return [
                'success' => true,
                'message' => 'Sync completed successfully.',
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory items from eBay
     */
    protected function getInventory(): array
    {
        $response = $this->apiRequest('GET', '/sell/inventory/v1/inventory_item');
        
        return $response['inventoryItems'] ?? [];
    }

    /**
     * Get orders from eBay
     */
    protected function getOrders(array $filters = []): array
    {
        $params = [
            'limit' => $filters['limit'] ?? 100,
            'offset' => $filters['offset'] ?? 0,
        ];
        
        if (!empty($filters['date_from'])) {
            $params['creation_date_range_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $params['creation_date_range_to'] = $filters['date_to'];
        }
        
        $response = $this->apiRequest('GET', '/sell/fulfillment/v1/order', $params);
        
        return $response['orders'] ?? [];
    }

    /**
     * Process orders (save to local database)
     */
    protected function processOrders(array $orders): void
    {
        // Implement order processing logic here
        // Save orders to your database
        foreach ($orders as $order) {
            // Process each order
            $this->saveOrder($order);
        }
    }

    /**
     * Save a single order to database
     */
    protected function saveOrder(array $orderData): void
    {
        // Implement order saving logic
        // You would call your order model here
    }

    /**
     * Update the last sync timestamp
     */
    protected function updateLastSync(): void
    {
        $this->settings['last_sync'] = date('Y-m-d H:i:s');
        $this->saveSettings();
    }

    /**
     * Save settings back to database
     */
    protected function saveSettings(): void
    {
        // Get channel manager model
        $channel_manager = registry('loader')->model('shop/channel_manager');
        $channel_manager->updateChannelSettings($this->channel['id'], $this->settings);
        
        // Update local settings
        $this->channel['settings'] = json_encode($this->settings);
    }

    /**
     * Check if current token is valid
     */
    protected function isTokenValid(): bool
    {
        $expires = $this->settings['token_expires'] ?? 0;
        
        // Check if token exists and hasn't expired (with 5 minute buffer)
        return !empty($this->settings['access_token']) && ($expires - time()) > 300;
    }

    /**
     * Make an API request to eBay
     */
    protected function apiRequest(string $method, string $endpoint, array $params = [], $body = null): array
    {
        // Check token validity
        if (!$this->isTokenValid()) {
            if (!$this->refreshToken()) {
                throw new Exception('Invalid or expired access token');
            }
        }
        
        $url = $this->apiUrl . $endpoint;
        
        if (!empty($params) && $method === 'GET') {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'Authorization: Bearer ' . $this->settings['access_token'],
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Language: en-US',
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL error: ' . $error);
        }
        
        if ($httpCode === 401) {
            // Token may have expired, try refreshing
            if ($this->refreshToken()) {
                // Retry the request
                return $this->apiRequest($method, $endpoint, $params, $body);
            }
            throw new Exception('Authentication failed. Token refresh unsuccessful.');
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorData = json_decode($response, true);
            $message = $errorData['error_description'] ?? $errorData['message'] ?? 'API request failed with HTTP ' . $httpCode;
            throw new Exception($message);
        }
        
        return json_decode($response, true) ?? [];
    }

    /**
     * Get product by SKU
     */
    public function getProduct(string $sku): ?array
    {
        try {
            $response = $this->apiRequest('GET', '/sell/inventory/v1/inventory_item/' . $sku);
            return $response;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Update product inventory
     */
    public function updateInventory(string $sku, int $quantity): array
    {
        try {
            $response = $this->apiRequest('POST', '/sell/inventory/v1/inventory_item/' . $sku . '/update_availability', [], [
                'availability' => [
                    'availableQuantity' => $quantity,
                ]
            ]);
            
            return [
                'success' => true,
                'sku' => $sku,
                'quantity' => $quantity,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get order by ID
     */
    public function getOrder(string $orderId): ?array
    {
        try {
            $response = $this->apiRequest('GET', '/sell/fulfillment/v1/order/' . $orderId);
            return $response;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(string $orderId, string $status): array
    {
        try {
            // Implement order status update logic
            // This would depend on eBay's fulfillment API
            return [
                'success' => true,
                'order_id' => $orderId,
                'status' => $status,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get order fulfillment status
     */
    public function getFulfillmentStatus(string $orderId): ?array
    {
        try {
            $response = $this->apiRequest('GET', '/sell/fulfillment/v1/order/' . $orderId . '/fulfillment_status');
            return $response;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get the channel ID
     */
    public function getChannelId(): int
    {
        return (int) $this->channel['id'];
    }

    /**
     * Get channel settings
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get channel name
     */
    public function getChannelName(): string
    {
        return $this->channel['name'] ?? '';
    }

    /**
     * Check if channel is connected
     */
    public function isConnected(): bool
    {
        return !empty($this->settings['access_token']);
    }
}