<?php

use Modules\Shop\Services\Adapters\Channel;
use System\Engine\Registry;
use System\Library\Database;
use System\Library\Logger;

class ShopChannelManagerModel extends \System\Engine\Model {
    protected Registry $registry;
    protected Database $db;
    protected Logger $logger;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->db       = $registry->get('db');
        $this->logger   = $registry->get('logger');
    }

    public function getChannelsForStore(int $storeId): array
    {
        if ($storeId === null) {
            return [];
        }
        return $this->db->find('shop_channel', ['store_id' => $storeId, 'status' => 'active']);
    }

    public function getChannel(int $channelId): ?array
    {
        try {
            $result = $this->db->query("SELECT * FROM #__shop_channel WHERE id = ?", [$channelId]);
            if ($result && $result->num_rows > 0) {
                $row = $result->row;
                return is_array($row) ? $row : (array) $row;
            }
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch channel: ' . $e->getMessage());
            return null;
        }
    }

    public function getChannelByName(int $storeId, string $name): ?array
    {
        return $this->db->first('shop_channel', ['store_id' => $storeId, 'name' => $name]);
    }

    public function saveChannel(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $channelData = [
            'store_id'     => (int)$data['store_id'],
            'name'         => $data['name'],
            'channel_name' => $data['channel_name'] ?? $data['name'],
            'type'         => $data['type'] ?? 'marketplace',
            'status'       => $data['status'] ?? 'inactive',
            'settings'     => is_string($data['settings']) ? $data['settings'] : json_encode($data['settings'] ?? []),
        ];

        if ($id > 0) {
            $this->db->update('shop_channel', $channelData, ['id' => $id]);
            return $id;
        }
        $channelData['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('shop_channel', $channelData);
        return $this->db->insert_id();
    }

    public function deleteChannel(int $channelId): void
    {
        $this->db->delete('shop_channel', ['id' => $channelId]);
    }

    public function updateChannelSettings(int $channelId, array $settings): void
    {
        $this->db->update('shop_channel', [
            'settings'   => json_encode($settings),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $channelId]);
    }

    /**
     * Get the adapter instance for a channel – using registry.
     */
    public function getAdapter(int $channelId): ?Channel
    {
        $channel = $this->getChannel($channelId);
        if (!is_array($channel) || empty($channel)) {
            $this->logger->error('Invalid channel data for ID ' . $channelId);
            return null;
        }

        $adapterName = strtolower($channel['channel_name'] ?? $channel['name'] ?? '');
        if (empty($adapterName)) {
            $this->logger->error('Channel name is empty for ID ' . $channelId);
            return null;
        }

        if (!$this->registry->has($adapterName)) {
            $this->logger->error('Channel adapter not registered: ' . $adapterName);
            return null;
        }
        
        $adapter = $this->registry->get($adapterName);
        if (!$adapter instanceof Channel) {
            $this->logger->error('Invalid adapter instance for channel: ' . $adapterName);
            return null;
        }

        $settingsRaw = $channel['settings'] ?? '{}';
        if (!is_string($settingsRaw)) {
            $settingsRaw = '{}';
        }
        $settings = json_decode($settingsRaw, true) ?: [];

        // Set channel‑specific data
        $adapter->setChannelId($channelId);
        $adapter->setStoreId((int)($channel['store_id'] ?? 0));
        $adapter->setConfig($settings);
        $adapter->setChannelManager($this);
        
        return $adapter;
    }
}