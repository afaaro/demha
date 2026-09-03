<?php

return [
    'shop/admin/dashboard' => [
        'controller' => 'shop/admin/dashboard',
        'action'     => 'index',
    ],

    // ============================================================
    // Categories
    // ============================================================
    'shop/admin/category' => [
        'controller' => 'shop/admin/category',
        'action'     => 'index',
    ],
    'shop/admin/category/add' => [
        'controller' => 'shop/admin/category',
        'action'     => 'add',
        'params'     => [],
    ],
    'shop/admin/category/edit' => [
        'controller' => 'shop/admin/category',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'shop/admin/category/delete' => [
        'controller' => 'shop/admin/category',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // ============================================================
    // Products
    // ============================================================
    'shop/admin/product' => [
        'controller' => 'shop/admin/product',
        'action'     => 'index',
    ],
    'shop/admin/product/add' => [
        'controller' => 'shop/admin/product',
        'action'     => 'add',
        'params'     => [],
    ],
    'shop/admin/product/edit' => [
        'controller' => 'shop/admin/product',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'shop/admin/product/delete' => [
        'controller' => 'shop/admin/product',
        'action'     => 'delete',
        'params'     => ['id'],
    ],
    // ✅ NEW – AJAX endpoints
    'shop/admin/product/getValues' => [
        'controller' => 'shop/admin/product',
        'action'     => 'getValues',
    ],
    'shop/admin/product/updateStockAjax' => [
        'controller' => 'shop/admin/product',
        'action'     => 'updateStockAjax',
    ],

    // ============================================================
    // Attributes
    // ============================================================
    'shop/admin/attribute' => [
        'controller' => 'shop/admin/attribute',
        'action'     => 'index',
    ],
    'shop/admin/attribute/add' => [
        'controller' => 'shop/admin/attribute',
        'action'     => 'add',
    ],
    'shop/admin/attribute/edit' => [
        'controller' => 'shop/admin/attribute',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'shop/admin/attribute/delete' => [
        'controller' => 'shop/admin/attribute',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // Channel management
    'shop/admin/channel' => [
        'controller' => 'shop/admin/channel',
        'action'     => 'index',
    ],
    'shop/admin/channel/create' => [
        'controller' => 'shop/admin/channel',
        'action'     => 'create',
    ],
    'shop/admin/channel/edit' => [
        'controller' => 'shop/admin/channel',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'shop/admin/channel/delete' => [
        'controller' => 'shop/admin/channel',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    'shop/admin/channel/connect' => [
        'controller' => 'shop/admin/channel',
        'action'     => 'connect',
        'params'     => ['id'],
    ],
    'shop/admin/channel/callback' => [
        'controller' => 'shop/admin/channel',
        'action'     => 'callback'
    ],

    'shop/admin/message' => [
        'controller' => 'shop/admin/message',
        'action'     => 'index',
    ],
    'shop/admin/message/view' => [
        'controller' => 'shop/admin/message',
        'action'     => 'view',
        'params'     => ['id']
    ],

    // Admin Order Routes
    'shop/admin/order' => [
        'controller' => 'shop/admin/Order',
        'action' => 'index',
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/view' => [
        'controller' => 'shop/admin/Order',
        'action' => 'view',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/update-status' => [
        'controller' => 'shop/admin/Order',
        'action' => 'updateStatus',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/create-fulfillment' => [
        'controller' => 'shop/admin/Order',
        'action' => 'createFulfillment',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/update-shipment' => [
        'controller' => 'shop/admin/Order',
        'action' => 'updateShipment',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/export' => [
        'controller' => 'shop/admin/Order',
        'action' => 'export',
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/delete' => [
        'controller' => 'shop/admin/Order',
        'action' => 'delete',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],
    'shop/admin/order/bulk' => [
        'controller' => 'shop/admin/Order',
        'action' => 'bulk',
        'auth' => true,
        'permission' => 'shop.manage_orders'
    ],

    // ============================================================
    // Front‑end product view
    // ============================================================
    'shop' => [
        'controller' => 'shop/home',
        'action'     => 'index',
    ],
    'shop/product' => [
        'controller' => 'shop/product',
        'action'     => 'index',
        'params'     => ['id'],
    ],
    'shop/cart' => [
        'controller' => 'shop/cart',
        'action'     => 'index',
    ],
    'shop/cart/add' => [
        'controller' => 'shop/cart',
        'action'     => 'add',
    ],
    'shop/cart/update' => [
        'controller' => 'shop/cart',
        'action'     => 'update',
    ],
    'shop/cart/remove' => [
        'controller' => 'shop/cart',
        'action'     => 'remove',
        'params'     => ['id'],
    ],
    'shop/cart/count' => [
        'controller' => 'shop/cart',
        'action'     => 'count',
    ],
    'shop/checkout' => [
        'controller' => 'shop/checkout',
        'action'     => 'index',
    ],
    'shop/checkout/place_order' => [
        'controller' => 'shop/checkout',
        'action'     => 'place_order',
    ],

    
];