<?php

use System\Engine\Model;

class ShopChannelManagerModel extends Model {
    protected $table = '#__shop_channel';
    protected $primaryKey = 'id';
    
    // Channel type constants
    public const TYPE_MARKETPLACE = 'marketplace';
    public const TYPE_SOCIAL = 'social';
    public const TYPE_WEBSITE = 'website';
    public const TYPE_OTHER = 'other';
    
    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ERROR = 'error';

    /**
     * Get all channels with optional filters
     */
    public function getChannels(array $filters = []): array
    {
        $sql = "SELECT * FROM #__shop_channel";
        $conditions = [];
        $params = [];
        
        if (!empty($filters['type'])) {
            $conditions[] = "type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['marketplace'])) {
            $conditions[] = "marketplace = ?";
            $params[] = $filters['marketplace'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(name LIKE ? OR channel_name LIKE ? OR marketplace LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY name ASC";
        
        return $this->db->query($sql, $params)->rows;
    }

    /**
     * Get a single channel by ID
     */
    public function getChannel($id): ?array
    {
        $result = $this->db->query(
            "SELECT * FROM #__shop_channel WHERE id = ?", 
            [$id]
        );
        
        $channel = $result->row;
        
        if ($channel && !empty($channel['settings'])) {
            $channel['settings'] = json_decode($channel['settings'], true) ?? [];
        }
        
        return $channel;
    }

    /**
     * Get channel by name (for validation)
     */
    public function getChannelByName(string $name, ?int $excludeId = null): ?array
    {
        $sql = "SELECT * FROM #__shop_channel WHERE name = ?";
        $params = [$name];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        return $this->db->query($sql, $params)->row;
    }

    /**
     * Get channel by channel_name (for validation)
     */
    public function getChannelByChannelName(string $channelName, ?int $excludeId = null): ?array
    {
        $sql = "SELECT * FROM #__shop_channel WHERE channel_name = ?";
        $params = [$channelName];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        return $this->db->query($sql, $params)->row;
    }

    /**
     * Save channel with validation
     */
    public function saveChannel(array $data): array
    {
        $errors = $this->validateChannel($data);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $now = date('Y-m-d H:i:s');
            $settings = $data['settings'] ?? [];
            
            $channelData = [
                'name' => trim($data['name']),
                'channel_name' => trim($data['channel_name']),
                'type' => $data['type'] ?? self::TYPE_MARKETPLACE,
                'marketplace' => !empty($data['marketplace']) ? trim($data['marketplace']) : null,
                'status' => $data['status'] ?? self::STATUS_INACTIVE,
                'updated_at' => $now,
            ];
            
            // Only include settings if not empty
            if (!empty($settings)) {
                $channelData['settings'] = json_encode($settings);
            }
            
            if (isset($data['id']) && !empty($data['id'])) {
                // Update existing channel
                $this->db->update('shop_channel', $channelData, ['id' => $data['id']]);
                $id = $data['id'];
                $action = 'updated';
            } else {
                // Insert new channel
                $channelData['created_at'] = $now;
                $id = $this->db->insert('shop_channel', $channelData);
                $action = 'created';
            }
            
            return [
                'success' => true,
                'id' => $id,
                'action' => $action,
                'message' => "Channel {$action} successfully."
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['database' => $e->getMessage()]
            ];
        }
    }

    /**
     * Validate channel data
     */
    public function validateChannel(array $data): array
    {
        $errors = [];
        
        // Required fields
        $required = ['name', 'channel_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Name validation
        if (!empty($data['name'])) {
            if (strlen($data['name']) < 3) {
                $errors['name'] = 'Name must be at least 3 characters.';
            }
            if (strlen($data['name']) > 50) {
                $errors['name'] = 'Name must not exceed 50 characters.';
            }
            
            // Check uniqueness
            $existing = $this->getChannelByName(
                $data['name'], 
                $data['id'] ?? null
            );
            
            if ($existing) {
                $errors['name'] = 'This channel name is already taken.';
            }
        }
        
        // Channel name validation
        if (!empty($data['channel_name'])) {
            if (strlen($data['channel_name']) < 3) {
                $errors['channel_name'] = 'Channel identifier must be at least 3 characters.';
            }
            if (strlen($data['channel_name']) > 100) {
                $errors['channel_name'] = 'Channel identifier must not exceed 100 characters.';
            }
            
            // Check uniqueness
            $existing = $this->getChannelByChannelName(
                $data['channel_name'], 
                $data['id'] ?? null
            );
            
            if ($existing) {
                $errors['channel_name'] = 'This channel identifier is already taken.';
            }
        }
        
        // Type validation
        if (!empty($data['type'])) {
            $validTypes = $this->getAvailableTypes();
            if (!in_array($data['type'], $validTypes)) {
                $errors['type'] = 'Invalid channel type.';
            }
        }
        
        // Marketplace validation (required for marketplace type)
        if (!empty($data['type']) && $data['type'] === self::TYPE_MARKETPLACE) {
            if (empty($data['marketplace'])) {
                $errors['marketplace'] = 'Marketplace name is required for marketplace type.';
            } else {
                $validMarketplaces = $this->getAvailableMarketplaces();
                if (!in_array($data['marketplace'], $validMarketplaces)) {
                    $errors['marketplace'] = 'Invalid marketplace selected.';
                }
            }
        }
        
        // Status validation
        if (!empty($data['status'])) {
            $validStatuses = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ERROR];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = 'Invalid status value.';
            }
        }
        
        return $errors;
    }

    /**
     * Delete a channel
     */
    public function deleteChannel(int $id): array
    {
        try {
            // Check if channel exists
            $channel = $this->getChannel($id);
            if (!$channel) {
                return [
                    'success' => false,
                    'errors' => ['Channel not found.']
                ];
            }
            
            // Check if channel has orders (soft delete)
            $orderCount = $this->db->query(
                "SELECT COUNT(*) AS count FROM #__shop_orders WHERE channel_id = ?",
                [$id]
            )->row['count'] ?? 0;
            
            if ($orderCount > 0) {
                return [
                    'success' => false,
                    'errors' => ['Cannot delete channel with existing orders. Deactivate it instead.']
                ];
            }
            
            $this->db->query("DELETE FROM #__shop_channel WHERE id = ?", [$id]);
            
            return [
                'success' => true,
                'message' => 'Channel deleted successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['database' => $e->getMessage()]
            ];
        }
    }

    /**
     * Update channel settings
     */
    public function updateChannelSettings(int $channelId, array $settings): array
    {
        try {
            $channel = $this->getChannel($channelId);
            if (!$channel) {
                return ['success' => false, 'errors' => ['Channel not found.']];
            }
            
            $this->db->update('shop_channel', [
                'settings' => !empty($settings) ? json_encode($settings) : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $channelId]);
            
            return [
                'success' => true,
                'message' => 'Settings updated successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['database' => $e->getMessage()]
            ];
        }
    }

    /**
     * Update channel status
     */
    public function updateStatus(int $channelId, string $status): array
    {
        $validStatuses = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ERROR];
        
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'errors' => ['Invalid status.']];
        }
        
        try {
            $this->db->update('shop_channel', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $channelId]);
            
            return [
                'success' => true,
                'message' => "Channel status updated to {$status}."
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['database' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get channels by type
     */
    public function getChannelsByType(string $type): array
    {
        return $this->getChannels(['type' => $type]);
    }

    /**
     * Get channels by status
     */
    public function getChannelsByStatus(string $status): array
    {
        return $this->getChannels(['status' => $status]);
    }

    /**
     * Get active channels
     */
    public function getActiveChannels(): array
    {
        return $this->getChannels(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Get channel statistics
     */
    public function getChannelStats(int $channelId): array
    {
        $stats = [
            'total_orders' => 0,
            'total_products' => 0,
            'total_revenue' => 0,
            'last_sync' => null,
        ];
        
        // Get order stats (if orders table exists)
        $orderStats = $this->db->query(
            "SELECT 
                COUNT(*) AS total_orders,
                COALESCE(SUM(total), 0) AS total_revenue,
                MAX(created_at) AS last_order
            FROM #__shop_orders 
            WHERE channel_id = ?",
            [$channelId]
        )->row ?? null;
        
        if ($orderStats) {
            $stats['total_orders'] = (int) ($orderStats['total_orders'] ?? 0);
            $stats['total_revenue'] = (float) ($orderStats['total_revenue'] ?? 0);
            $stats['last_order'] = $orderStats['last_order'] ?? null;
        }
        
        return $stats;
    }

    /**
     * Get available channel types
     */
    public function getAvailableTypes(): array
    {
        return [
            self::TYPE_MARKETPLACE,
            self::TYPE_SOCIAL,
            self::TYPE_WEBSITE,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get channel types with labels for dropdown
     */
    public function getTypeOptions(): array
    {
        return [
            self::TYPE_MARKETPLACE => 'Marketplace',
            self::TYPE_SOCIAL => 'Social Media',
            self::TYPE_WEBSITE => 'Website',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get available marketplaces
     */
    public function getAvailableMarketplaces(): array
    {
        return [
            'amazon',
            'ebay',
            'etsy',
            'walmart',
            'shopee',
            'lazada',
            'tiktok',
            'facebook',
            'instagram',
            'shopify',
            'woocommerce',
            'magento',
            'bigcommerce',
            'other',
        ];
    }

    /**
     * Get marketplace options for dropdown
     */
    public function getMarketplaceOptions(): array
    {
        return [
            'amazon' => 'Amazon',
            'ebay' => 'eBay',
            'etsy' => 'Etsy',
            'walmart' => 'Walmart',
            'shopee' => 'Shopee',
            'lazada' => 'Lazada',
            'tiktok' => 'TikTok Shop',
            'facebook' => 'Facebook Marketplace',
            'instagram' => 'Instagram Shopping',
            'shopify' => 'Shopify',
            'woocommerce' => 'WooCommerce',
            'magento' => 'Magento',
            'bigcommerce' => 'BigCommerce',
            'other' => 'Other',
        ];
    }

    /**
     * Get status options for dropdown
     */
    public function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ERROR => 'Error',
        ];
    }

    /**
     * Get form configuration for channel
     */
    public function getFormConfig(array $channel = []): array
    {
        $rules = [
            'name' => 'required|min:3|max:50',
            'channel_name' => 'required|min:3|max:100',
            'type' => 'required|in:' . implode(',', $this->getAvailableTypes()),
            'status' => 'required|in:' . implode(',', [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ERROR]),
        ];
        
        // Add marketplace validation if type is marketplace
        if (!empty($channel['type']) && $channel['type'] === self::TYPE_MARKETPLACE) {
            $rules['marketplace'] = 'required|in:' . implode(',', $this->getAvailableMarketplaces());
        }
        
        return [
            'rules' => $rules,
            'messages' => [
                'name.required' => 'Channel name is required.',
                'name.min' => 'Channel name must be at least 3 characters.',
                'name.max' => 'Channel name must not exceed 50 characters.',
                'channel_name.required' => 'Channel identifier is required.',
                'channel_name.min' => 'Channel identifier must be at least 3 characters.',
                'channel_name.max' => 'Channel identifier must not exceed 100 characters.',
                'type.required' => 'Please select a channel type.',
                'type.in' => 'Invalid channel type selected.',
                'marketplace.required' => 'Please select a marketplace.',
                'marketplace.in' => 'Invalid marketplace selected.',
                'status.required' => 'Please select a status.',
                'status.in' => 'Invalid status selected.',
            ]
        ];
    }

    /**
     * Get adapter for a channel (if needed)
     */
    public function getAdapter(int $channelId): ?object
    {
        $channel = $this->getChannel($channelId);
        if (!$channel) {
            return null;
        }

        try {
            // Use marketplace as adapter type if available, otherwise use type
            $adapterType = $channel['marketplace'];
            $adapterClass = $this->load->library("shop/adapter/{$adapterType}"); 
            if (!$adapterClass) {
                throw new Exception("No adapter found for channel type: {$adapterType}");
            }
            return new $adapterClass($channel);
        } catch (Exception $e) {
            error_log("Failed to load adapter for channel {$channelId}: " . $e->getMessage());
            return null;
        }
    }
}