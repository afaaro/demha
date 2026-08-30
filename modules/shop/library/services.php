<?php

// ============================================================
// Register the ChannelManager service
// ============================================================
$registry->set('channel_manager', function($c) {
    return new App\Modules\Shop\Services\ChannelManager($c);
});

$registry->set('http', function($c) {
    return new App\Modules\Shop\Services\Http\HttpClient($c->get('logger'));
});

$registry->set('ebay', function($c) {
    $adapter = new App\Modules\Shop\Services\Adapters\EbayAdapter();
    $adapter->setDatabase($c->get('db'));
    $adapter->setLogger($c->get('logger'));
    $adapter->setRegistry($c);
    $adapter->setHttp($c->get('http'));
    return $adapter;
});

// ============================================================
// You can add more adapters here
// ============================================================
// $registry->set('amazon', function($c) { ... });
// $registry->set('etsy', function($c) { ... });