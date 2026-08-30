<?php

return [
    'shop/admin/dashboard' => [
        'controller' => 'shop/admin/dashboard',
        'action'     => 'index',
    ],
    // ============================================================
    // Store management
    // ============================================================
    'shop/admin/store' => [
        'controller' => 'shop/admin/store',
        'action'     => 'index',
        'params'     => ['page', 'status'],
    ],
    'shop/admin/store/toggle' => [
        'controller' => 'shop/admin/store',
        'action'     => 'toggle',
        'params'     => ['id'],
    ],
    'shop/admin/store/view' => [
        'controller' => 'shop/admin/store',
        'action'     => 'view',
        'params'     => ['id'],
    ],
    'shop/admin/store/add' => [
        'controller' => 'shop/admin/store',
        'action'     => 'add',
        'params'     => [],
    ],
    'shop/admin/store/edit' => [
        'controller' => 'shop/admin/store',
        'action'     => 'edit',
        'params'     => ['store_id'],
    ],
    'shop/admin/store/delete' => [
        'controller' => 'shop/admin/store',
        'action'     => 'delete',
        'params'     => ['store_id'],
    ],

    // ============================================================
    // Seller roles
    // ============================================================
    'shop/admin/seller_role/add' => [
        'controller' => 'shop/admin/seller_role',
        'action'     => 'add',
        'params'     => [],
    ],
    'shop/admin/seller_role/edit' => [
        'controller' => 'shop/admin/seller_role',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'shop/admin/seller_role/delete' => [
        'controller' => 'shop/admin/seller_role',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // ============================================================
    // User‑seller mapping
    // ============================================================
    'shop/admin/user_seller' => [
        'controller' => 'shop/admin/user_seller',
        'action'     => 'index',
    ],
    'shop/admin/user_seller/invite' => [
        'controller' => 'shop/admin/user_seller',
        'action'     => 'invite',
        'params'     => [],
    ],
    'shop/admin/user_seller/edit' => [
        'controller' => 'shop/admin/user_seller',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'shop/admin/user_seller/delete' => [
        'controller' => 'shop/admin/user_seller',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // ============================================================
    // User‑store creation (?) – maybe a separate controller
    // ============================================================
    'shop/admin/user_store' => [
        'controller' => 'shop/admin/user_store',
        'action'     => 'index',
        'params'     => [],
    ],

    'shop/admin/user_store/create' => [
        'controller' => 'shop/admin/user_store',
        'action'     => 'create',
        'params'     => [],
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

    // ============================================================
    // Front‑end product view
    // ============================================================
    'shop' => [
        'controller' => 'shop/home',
        'action'     => 'index',
    ],
    'shop/product' => [
        'controller' => 'shop/product',
        'action'     => 'view',
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
    'shop/checkout' => [
        'controller' => 'shop/checkout',
        'action'     => 'index',
    ],
    'shop/checkout/place_order' => [
        'controller' => 'shop/checkout',
        'action'     => 'place_order',
    ],
];