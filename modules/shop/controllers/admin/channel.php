<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;
use System\Library\Tabs;

class ShopAdminChannel extends Controller {
    protected object $channel_manager;

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->channel_manager = $this->load->model('shop/channel_manager');
    }

    public function indexAction()
    {
        $channels = $this->channel_manager->getChannels();

        echo $this->view->inline(function ($view) use ($channels) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h3><i class='bi bi-broadcast me-2'></i>Sales Channels</h3>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('shop/admin/channel/create') . "'>
                    <i class='bi bi-plus-lg me-1'></i>Add Channel
                </a>";
            echo "</div>";

            if (empty($channels)) {
                echo "<div class='alert alert-info'>No channels configured for this store.</div>";
            } else {
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped table-hover'>";
                echo "<thead>
                    <tr>
                        <th>ID</th>
                        <th>Channel</th>
                        <th>Identifier</th>
                        <th>Type</th>
                        <th>Marketplace</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>";
                echo "<tbody>";
                foreach ($channels as $ch) {
                    $statusClass = $this->getStatusBadgeClass($ch['status']);
                    $settings = json_decode($ch['settings'] ?? '{}', true);
                    $isConnected = !empty($settings['access_token']);
                    
                    echo "<tr>";
                    echo "<td>" . (int) $ch['id'] . "</td>";
                    echo "<td><strong>" . escape($ch['name'] ?? '') . "</strong></td>";
                    echo "<td>" . escape($ch['channel_name'] ?? '') . "</td>";
                    echo "<td><span class='badge bg-info'>" . escape($ch['type'] ?? '') . "</span></td>";
                    echo "<td>" . escape($ch['marketplace'] ?? '-') . "</td>";
                    echo "<td><span class='badge bg-" . $statusClass . "'>" . escape($ch['status']) . "</span></td>";
                    echo "<td>";
                    
                    // Edit button
                    echo "<a class='btn btn-sm btn-outline-primary me-1' 
                            href='" . $view->url->to('shop/admin/channel/edit', ['id' => $ch['id']]) . "' 
                            title='Edit Channel'>
                            <i class='bi bi-pencil'></i>
                        </a>";
                    
                    // Delete button
                    echo "<a class='btn btn-sm btn-outline-danger me-1' 
                            onclick=\"return confirm('Delete this channel? This action cannot be undone.')\" 
                            href='" . $view->url->to('shop/admin/channel/delete', ['id' => $ch['id']]) . "' 
                            title='Delete Channel'>
                            <i class='bi bi-trash'></i>
                        </a>";
                    
                    // Connect/Sync button
                    if (!$isConnected) {
                        echo "<a class='btn btn-sm btn-success' 
                                href='" . $view->url->to('shop/admin/channel/connect', ['id' => $ch['id']]) . "' 
                                title='Connect Channel'>
                                <i class='bi bi-link-45deg'></i> Connect
                            </a>";
                    } else {
                        echo "<a class='btn btn-sm btn-info' 
                                href='" . $view->url->to('shop/admin/channel/sync', ['id' => $ch['id']]) . "' 
                                title='Sync Channel'>
                                <i class='bi bi-arrow-repeat'></i> Sync
                            </a>";
                    }
                    
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            }
        }, 'admin');
    }

    public function createAction()
    {
        $formConfig = $this->channel_manager->getFormConfig();
        $this->form->setRules($formConfig['rules'], $formConfig['messages']);

        echo $this->view->inline(function ($view) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h3><i class='bi bi-plus-circle me-2'></i>Add Sales Channel</h3>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('shop/admin/channel') . "'>
                    <i class='bi bi-arrow-left me-1'></i>Back to Channels
                </a>";
            echo "</div>";
            
            echo $this->form->start(['id' => 'channel-form']);
            
            echo $this->form->input('name', [
                'label' => 'Channel Name',
                'placeholder' => 'e.g., My eBay Store',
                'help' => 'Display name for this channel',
                'wrapper_class' => 'mb-3'
            ]);
            
            echo $this->form->input('channel_name', [
                'label' => 'Channel Identifier',
                'placeholder' => 'e.g., ebay_us_store',
                'help' => 'Unique identifier for this channel',
                'wrapper_class' => 'mb-3'
            ]);
            
            echo $this->form->select('type', $this->channel_manager->getTypeOptions(), null, [
                'label' => 'Channel Type',
                'placeholder' => '-- Select Channel Type --',
                'wrapper_class' => 'mb-3',
                'id' => 'channel_type'
            ]);
            
            echo $this->form->select('marketplace', $this->channel_manager->getMarketplaceOptions(), null, [
                'label' => 'Marketplace',
                'placeholder' => '-- Select Marketplace --',
                'wrapper_class' => 'mb-3',
                'id' => 'marketplace_select',
                'attrs' => [
                    'data-dependent' => 'channel_type',
                    'data-dependent-value' => 'marketplace'
                ]
            ]);
            
            echo $this->form->select('status', $this->channel_manager->getStatusOptions(), 'inactive', [
                'label' => 'Status',
                'wrapper_class' => 'mb-3'
            ]);
            
            echo $this->form->submit('Create Channel', ['class' => 'btn btn-primary']);
            echo $this->form->close();
            
            echo $this->getToggleMarketplaceJs();
        }, 'admin');

        if ($this->form->isValid()) {
            $channelData = $this->form->validated();
            $result = $this->channel_manager->saveChannel($channelData);
            
            if ($result['success']) {
                Notify::success($result['message']);
                redirect_to('shop/admin/channel');
            } else {
                $this->form->setErrors($result['errors']);
            }
        }
    }

    public function editAction()
    {
        $id = (int) $this->request->get('id', 'int');
        $channel = $this->channel_manager->getChannel($id);
        
        if (!$channel) {
            Notify::error('Channel not found.');
            redirect_to('shop/admin/channel');
            return;
        }

        $formConfig = $this->channel_manager->getFormConfig($channel);
        $this->form->setRules($formConfig['rules'], $formConfig['messages']);
        $channel['settings'] = json_encode($channel['settings'], JSON_PRETTY_PRINT);
        $this->form->fill($channel);

        $formHtml = $this->form->start(['id' => 'channel-form']);
        $formHtml .= $this->form->input('name', [
            'label' => 'Channel Name',
            'placeholder' => 'e.g., My eBay Store',
            'help' => 'Display name for this channel',
            'wrapper_class' => 'mb-3'
        ]);
        
        $formHtml .= $this->form->input('channel_name', [
            'label' => 'Channel Identifier',
            'placeholder' => 'e.g., ebay_us_store',
            'help' => 'Unique identifier for this channel',
            'wrapper_class' => 'mb-3'
        ]);
        
        $formHtml .= $this->form->select('type', $this->channel_manager->getTypeOptions(), $channel['type'] ?? null, [
            'label' => 'Channel Type',
            'placeholder' => '-- Select Channel Type --',
            'wrapper_class' => 'mb-3',
            'id' => 'channel_type'
        ]);
        
        $formHtml .= $this->form->select('marketplace', $this->channel_manager->getMarketplaceOptions(), $channel['marketplace'] ?? null, [
            'label' => 'Marketplace',
            'placeholder' => '-- Select Marketplace --',
            'wrapper_class' => 'mb-3',
            'id' => 'marketplace_select',
            'attrs' => [
                'data-dependent' => 'channel_type',
                'data-dependent-value' => 'marketplace'
            ]
        ]);
        
        $formHtml .= $this->form->textarea('settings', [
            'label' => 'Settings (JSON)',
            'placeholder' => '{"key":"value"}',
            'help' => 'Advanced settings in JSON format. Leave empty for default.',
            'wrapper_class' => 'mb-3',
            'rows' => 5
        ]);

        $formHtml .= $this->form->select('status', $this->channel_manager->getStatusOptions(), $channel['status'] ?? 'inactive', [
            'label' => 'Status',
            'wrapper_class' => 'mb-3'
        ]);
        
        $formHtml .= $this->form->submit('Update Channel', ['class' => 'btn btn-primary']);
        $formHtml .= $this->form->close();

        // Get adapter for connection status
        $adapter = $this->channel_manager->getAdapter($id);

        $tabs = Tabs::make('channel-edit-tabs', $this->registry)
            ->query('section')
            ->style('tabs')
            ->link(true, ['id', 'route']);

        $tabs->add('general', 'General Settings', 'bi bi-gear', $formHtml)
             ->add('connection', 'API Connection', 'bi bi-link-45deg', $this->renderConnectionStatusBox($channel, $adapter));
             
        echo $this->view->inline(function ($view) use ($channel, $tabs) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h3><i class='bi bi-pencil-square me-2'></i>Edit Channel: " . escape($channel['name'] ?? '') . "</h3>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('shop/admin/channel') . "'>
                    <i class='bi bi-arrow-left me-1'></i>Back to Channels
                </a>";
            echo "</div>";
            
            echo $tabs->render();
            echo $this->getToggleMarketplaceJs();
        }, 'admin');

        if ($this->form->isValid()) {
            $channelData = $this->form->validated();
            $channelData['id'] = $id;
            $result = $this->channel_manager->saveChannel($channelData);
            
            if ($result['success']) {
                Notify::success($result['message']);
                redirect_to('shop/admin/channel');
            } else {
                $this->form->setErrors($result['errors']);
            }
        }
    }

    public function deleteAction()
    {
        $id = (int) $this->request->get('id', 'int');
        
        if ($id <= 0) {
            Notify::error('Invalid channel ID.');
            redirect_to('shop/admin/channel');
            return;
        }
        
        $result = $this->channel_manager->deleteChannel($id);
        
        if ($result['success']) {
            Notify::success($result['message']);
        } else {
            Notify::error(implode(' ', $result['errors'] ?? ['Failed to delete channel.']));
        }
        
        redirect_to('shop/admin/channel');
    }

    public function connectAction()
    {
        $id = (int) $this->request->get('id', 'int');
        $channel = $this->channel_manager->getChannel($id);

        if (!$channel) {
            Notify::error('Channel not found.');
            redirect_to('shop/admin/channel');
            return;
        }

        // Ensure the adapter is loaded
        $adapter = $this->channel_manager->getAdapter($id);
        if (!$adapter) {
            Notify::error('No adapter available for this channel type.');
            redirect_to('shop/admin/channel/edit', ['id' => $id, 'section' => 'connection']);
            return;
        }

        // Check if this is the OAuth callback (contains 'code' parameter)
        $code = $this->request->get('code', 'raw', '');
        if ($code) {
            // Handle OAuth callback
            try {
                $result = $adapter->handleCallback($code);
                if ($result['success']) {
                    $this->channel_manager->updateChannelSettings($id, $result['settings']);
                    Notify::success('Channel connected successfully!');
                } else {
                    Notify::error($result['message'] ?? 'Failed to connect channel.');
                }
            } catch (Exception $e) {
                Notify::error('Connection error: ' . $e->getMessage());
            }

            redirect_to('shop/admin/channel/edit', ['id' => $id, 'section' => 'connection']);
            return;
        }

        // Start OAuth flow
        try {
            $authUrl = $adapter->getAuthorizationUrl();
            redirect($authUrl);
        } catch (Exception $e) {
            Notify::error('Failed to start authorization: ' . $e->getMessage());
            redirect_to('shop/admin/channel/edit', ['id' => $id, 'section' => 'connection']);
        }
    }

    public function syncAction()
    {
        $id = (int) $this->request->get('id', 'int');
        $channel = $this->channel_manager->getChannel($id);

        if (!$channel) {
            Notify::error('Channel not found.');
            redirect_to('shop/admin/channel');
            return;
        }

        $adapter = $this->channel_manager->getAdapter($id);

        if (!$adapter) {
            Notify::error('No adapter available for this channel type.');
            redirect_to('shop/admin/channel/edit', ['id' => $id, 'section' => 'connection']);
            return;
        }

        try {
            $result = $adapter->sync();

            if ($result['success']) {
                Notify::success('Sync completed successfully!');
                if (isset($result['stats'])) {
                    $this->session->set('info', 'Products: ' . ($result['stats']['products'] ?? 0) .
                                            ', Orders: ' . ($result['stats']['orders'] ?? 0));
                }
            } else {
                Notify::error($result['message'] ?? 'Sync failed.');
            }
        } catch (Exception $e) {
            Notify::error('Sync error: ' . $e->getMessage());
        }

        redirect_to('shop/admin/channel/edit', ['id' => $id, 'section' => 'connection']);
    }

    /**
     * AJAX endpoint to test connection
     */
    public function testConnectionAction()
    {
        $id = (int) $this->request->get('id', 'int');
        $channel = $this->channel_manager->getChannel($id);

        if (!$channel) {
            echo json_encode(['success' => false, 'message' => 'Channel not found.']);
            return;
        }

        $adapter = $this->channel_manager->getAdapter($id);

        if (!$adapter) {
            echo json_encode(['success' => false, 'message' => 'Adapter not available.']);
            return;
        }

        $result = $adapter->testConnection();
        echo json_encode($result);
        exit;
    }

    private function renderConnectionStatusBox(array $channel, ?object $adapter): string
    {
        $settings = $channel['settings'] ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $isConnected = !empty($settings['access_token']);
        $expires = $settings['token_expires'] ?? 0;
        $expiryText = $expires ? ' (expires ' . date('Y-m-d H:i:s', $expires) . ')' : '';

        $connectUrl = $this->url->to('shop/admin/channel/connect', ['id' => $channel['id']]);
        $syncUrl = $this->url->to('shop/admin/channel/sync', ['id' => $channel['id']]);
        $testUrl = $this->url->to('shop/admin/channel/test-connection', ['id' => $channel['id']]);

        return $this->view->inline(function () use ($isConnected, $connectUrl, $syncUrl, $testUrl, $adapter, $expiryText, $channel, $settings) {
            echo "<div class='card border-top-0 rounded-top-0'>";
            echo "<div class='card-body'>";
            
            echo "<h5><i class='bi bi-plug me-2'></i>API Authorization & Status</h5>";
            echo "<p class='text-muted'>Manage your external API integration state and tokens for " .
                 escape($channel['marketplace'] ?? $channel['type'] ?? 'channel') . ".</p>";
            
            if ($isConnected) {
                echo "<div class='alert alert-success'>";
                echo "<i class='bi bi-check-circle-fill me-2'></i> Channel is currently connected and authorized." . $expiryText;
                echo "</div>";
                
                echo "<div class='d-flex gap-2 flex-wrap'>";
                echo "<a href='" . $connectUrl . "' class='btn btn-outline-primary'>
                        <i class='bi bi-arrow-repeat me-1'></i> Re-authorize / Refresh
                    </a>";
                
                if ($adapter && method_exists($adapter, 'testConnection')) {
                    echo "<button class='btn btn-outline-info' onclick='testConnection(\"{$testUrl}\")'>
                            <i class='bi bi-plug me-1'></i> Test Connection
                        </button>";
                }
                
                echo "<a href='" . $syncUrl . "' class='btn btn-outline-success'>
                        <i class='bi bi-arrow-repeat me-1'></i> Sync Now
                    </a>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-warning'>";
                echo "<i class='bi bi-exclamation-triangle-fill me-2'></i> Channel requires authentication.";
                echo "</div>";
                
                if ($adapter) {
                    echo "<a href='" . $connectUrl . "' class='btn btn-success'>
                            <i class='bi bi-link-45deg me-1'></i> Connect Channel
                        </a>";
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "<i class='bi bi-x-circle-fill me-2'></i> No adapter available for this channel type.";
                    echo "</div>";
                }
            }
            
            if (!empty($settings['access_token'])) {
                echo "<hr>";
                echo "<div class='small text-muted'>";
                echo "<strong>Token Info:</strong><br>";
                echo "Access Token: " . substr($settings['access_token'], 0, 20) . "...<br>";
                if (!empty($settings['refresh_token'])) {
                    echo "Refresh Token: " . substr($settings['refresh_token'], 0, 20) . "...<br>";
                }
                if (!empty($settings['user_id'])) {
                    echo "User ID: " . escape($settings['user_id']) . "<br>";
                }
                echo "</div>";
            }
            
            echo "</div></div>";
            
            // JavaScript for test connection (using fetch)
            echo <<<JS
            <script>
            function testConnection(url) {
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Connection successful!');
                        } else {
                            alert('Connection failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error testing connection: ' + error);
                    });
            }
            </script>
            JS;
        }, false);
    }

    private function getStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'inactive' => 'secondary',
            'error' => 'danger',
            default => 'secondary',
        };
    }

    private function getToggleMarketplaceJs(): string
    {
        return <<<JS
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('channel_type');
            const marketplaceSelect = document.getElementById('marketplace_select');
            
            if (!typeSelect || !marketplaceSelect) return;
            
            const marketplaceGroup = marketplaceSelect.closest('.mb-3');
            if (!marketplaceGroup) return;
            
            function toggleMarketplace() {
                if (typeSelect.value === 'marketplace') {
                    marketplaceGroup.style.display = 'block';
                    marketplaceSelect.setAttribute('required', 'required');
                } else {
                    marketplaceGroup.style.display = 'none';
                    marketplaceSelect.removeAttribute('required');
                }
            }
            
            typeSelect.addEventListener('change', toggleMarketplace);
            toggleMarketplace();
        });
        </script>
        JS;
    }
}