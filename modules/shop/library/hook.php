<?php

return [
    'menu.admin_sidebar' => function(array $items, $view): array {
        // ============================================================
        // Top‑level Shop item
        // ============================================================
        $items[] = [
            'id'     => 'shop',
            'title'  => 'Shop',
            'icon'   => 'bi bi-cart',
            'sort'   => 60, // Adjust to position in sidebar
        ];

        // ============================================================
        // Sub‑items (ordered by sort)
        // ============================================================

        // Dashboard
        $items[] = [
            'id'         => 'shop_dashboard',
            'parent'     => 'shop',
            'title'      => 'Dashboard',
            'route'      => 'shop/admin/dashboard',
            'icon'       => 'bi bi-speedometer2',
            'permission' => 'shop.admin.dashboard.index',
            'sort'       => 10,
        ];

        // Stores
        $items[] = [
            'id'         => 'shop_stores',
            'parent'     => 'shop',
            'title'      => 'Stores',
            'route'      => 'shop/admin/store',
            'icon'       => 'bi bi-shop',
            'permission' => 'shop.admin.store.index',
            'sort'       => 20,
        ];

        $items[] = [
            'id'         => 'shop_channels',
            'parent'     => 'shop',
            'title'      => 'Sales Channels',
            'route'      => 'shop/admin/channel',
            'icon'       => 'bi bi-broadcast',
            'permission' => 'shop.admin.channel.index',
            'sort'       => 80,
        ];
        
        $items[] = [
            'id'         => 'shop_categories',
            'parent'     => 'shop',
            'title'      => 'Categories',
            'route'      => 'shop/admin/category',
            'icon'       => 'bi bi-tags',
            'permission' => 'shop.admin.category.index',
            'sort'       => 30,
        ];

        // Products
        $items[] = [
            'id'         => 'shop_products',
            'parent'     => 'shop',
            'title'      => 'Products',
            'route'      => 'shop/admin/product',
            'icon'       => 'bi bi-box',
            'permission' => 'shop.admin.product.index',
            'sort'       => 40,
        ];

        // Variation Options
        $items[] = [
            'id'         => 'shop_attribute',
            'parent'     => 'shop',
            'title'      => 'Attributes',
            'route'      => 'shop/admin/attribute',
            'icon'       => 'bi bi-option',
            'permission' => 'shop.admin.attribute.index',
            'sort'       => 50,
        ];

        // Sellers (User‑Store Management)
        $items[] = [
            'id'         => 'shop_sellers',
            'parent'     => 'shop',
            'title'      => 'Sellers',
            'route'      => 'shop/admin/user_seller',
            'icon'       => 'bi bi-people',
            'permission' => 'shop.admin.user_seller.index',
            'sort'       => 60,
        ];

        // Create User & Store (a specific action)
        $items[] = [
            'id'         => 'shop_create_user_store',
            'parent'     => 'shop',
            'title'      => 'Create User & Store',
            'route'      => 'shop/admin/user_store/create',
            'icon'       => 'bi bi-person-plus',
            'permission' => 'shop.admin.user_store.create',
            'sort'       => 70,
        ];

        // Create User & Store (a specific action)
        $items[] = [
            'id'         => 'shop_messages',
            'parent'     => 'shop',
            'title'      => 'Messages',
            'route'      => 'shop/admin/message',
            'icon'       => 'bi bi-envelope',
            'permission' => 'shop.admin.message.index',
            'sort'       => 80,
        ];

        // 8. Orders (if you have an orders controller)
        // Uncomment when you have `shop/orders` controller
        /*
        $items[] = [
            'id'         => 'shop_orders',
            'parent'     => 'shop',
            'title'      => 'Orders',
            'route'      => 'shop/orders',
            'icon'       => 'bi bi-bag',
            'permission' => 'shop.orders.index',
            'sort'       => 80,
        ];
        */

        return $items;
    },

    'menu.admin_toolbar' => function(array $items, $view): array {
        // Add a "Shop" button to the admin toolbar
        $items[] = [
            'id'         => 'shop',
            'title'      => 'Shop',
            'icon'       => 'bi bi-cart',
            'permission' => 'shop.admin.dashboard.index',
        ];

        $items[] = [
            'id'         => 'shop_channels',
            'parent'     => 'shop',
            'title'      => 'Sales Channels',
            'route'      => 'shop/admin/channel',
            'icon'       => 'bi bi-broadcast',
            'permission' => 'shop.admin.channel.index',
            'sort'       => 80,
        ];

        return $items;
    }
];