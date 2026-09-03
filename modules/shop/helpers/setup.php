<?php

use System\Engine\Registry;
use System\Library\Database;

return new class {
    public function install(Registry $registry, Database $db): void
    {
        try {
            // Start transaction using PDO directly to avoid wrapper issues
            $db->query("START TRANSACTION");

            // ============================================================
            // 1. Categories
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_categories (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    parent_id INT UNSIGNED DEFAULT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL,
                    description TEXT DEFAULT NULL,
                    image VARCHAR(255) DEFAULT NULL,
                    icon VARCHAR(100) DEFAULT NULL,
                    status TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    FOREIGN KEY (parent_id) REFERENCES #__shop_categories(id) ON DELETE SET NULL,
                    UNIQUE KEY category_slug (slug),
                    INDEX idx_parent (parent_id),
                    INDEX idx_status (status),
                    INDEX idx_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 2. Products
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_product (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    category_id INT UNSIGNED DEFAULT NULL,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL,
                    sku VARCHAR(255) NOT NULL,
                    description LONGTEXT DEFAULT NULL,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    weight DECIMAL(10,2) DEFAULT NULL,
                    dimensions JSON DEFAULT NULL,
                    currency CHAR(3) NOT NULL DEFAULT 'GBP',
                    tax_class VARCHAR(50) DEFAULT NULL,
                    meta_title VARCHAR(255) DEFAULT NULL,
                    meta_description VARCHAR(500) DEFAULT NULL,
                    meta_keywords VARCHAR(255) DEFAULT NULL,
                    status ENUM('draft','active','archived') DEFAULT 'draft',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    FOREIGN KEY (category_id) REFERENCES #__shop_categories(id) ON DELETE SET NULL,
                    UNIQUE KEY store_product_slug (slug),
                    UNIQUE KEY store_product_sku (sku),
                    INDEX idx_category (category_id),
                    INDEX idx_status (status),
                    INDEX idx_deleted (deleted_at),
                    FULLTEXT KEY idx_fulltext (name, description)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 3. Product Images
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_product_image (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    product_id BIGINT UNSIGNED NOT NULL,
                    url VARCHAR(500) NOT NULL,
                    alt VARCHAR(255) DEFAULT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    is_primary TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (product_id) REFERENCES #__shop_product(id) ON DELETE CASCADE,
                    INDEX idx_product (product_id),
                    INDEX idx_primary (product_id, is_primary)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 4. Option Groups
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_option_group (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    type ENUM('select','radio','color','text') DEFAULT 'select',
                    required TINYINT(1) NOT NULL DEFAULT 0,
                    status TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_name (name),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 5. Option Values
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_option_value (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    group_id INT UNSIGNED NOT NULL,
                    value VARCHAR(100) NOT NULL,
                    color_code VARCHAR(20) DEFAULT NULL,
                    image VARCHAR(255) DEFAULT NULL,
                    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    price_prefix ENUM('+','-') DEFAULT '+',
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (group_id) REFERENCES #__shop_option_group(id) ON DELETE CASCADE,
                    INDEX idx_group (group_id),
                    INDEX idx_sort (group_id, sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 6. Product ↔ Option Group Link
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_product_option_group (
                    product_id BIGINT UNSIGNED NOT NULL,
                    group_id INT UNSIGNED NOT NULL,
                    required TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (product_id, group_id),
                    FOREIGN KEY (product_id) REFERENCES #__shop_product(id) ON DELETE CASCADE,
                    FOREIGN KEY (group_id) REFERENCES #__shop_option_group(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 7. Product Variants
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_product_variant (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    product_id BIGINT UNSIGNED NOT NULL,
                    sku VARCHAR(100) NOT NULL,
                    barcode VARCHAR(100) DEFAULT NULL,
                    price DECIMAL(10,2) DEFAULT NULL,
                    cost DECIMAL(10,2) DEFAULT NULL,
                    weight DECIMAL(10,2) DEFAULT NULL,
                    status ENUM('active','inactive') DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    FOREIGN KEY (product_id) REFERENCES #__shop_product(id) ON DELETE CASCADE,
                    UNIQUE KEY variant_sku (sku),
                    INDEX idx_product (product_id),
                    INDEX idx_barcode (barcode),
                    INDEX idx_status (status),
                    INDEX idx_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 8. Variant ↔ Option Value Link
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_product_variant_option (
                    variant_id BIGINT UNSIGNED NOT NULL,
                    value_id INT UNSIGNED NOT NULL,
                    PRIMARY KEY (variant_id, value_id),
                    FOREIGN KEY (variant_id) REFERENCES #__shop_product_variant(id) ON DELETE CASCADE,
                    FOREIGN KEY (value_id) REFERENCES #__shop_option_value(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 9. Inventory
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_inventory (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    quantity INT NOT NULL DEFAULT 0,
                    reserved INT NOT NULL DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY variant_inventory (variant_id),
                    FOREIGN KEY (variant_id) REFERENCES #__shop_product_variant(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 10. Inventory Log
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_inventory_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    change_qty INT NOT NULL,
                    reason VARCHAR(255) DEFAULT NULL,
                    user_id INT UNSIGNED DEFAULT NULL,
                    reference VARCHAR(100) DEFAULT NULL,
                    stock_before INT DEFAULT NULL,
                    stock_after INT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (variant_id) REFERENCES #__shop_product_variant(id) ON DELETE CASCADE,
                    INDEX idx_variant (variant_id),
                    INDEX idx_user (user_id),
                    INDEX idx_reference (reference),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 11. Cart
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_cart (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED DEFAULT NULL,
                    session_id VARCHAR(128) DEFAULT NULL,
                    currency CHAR(3) NOT NULL DEFAULT 'GBP',
                    expires_at DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_session_id (session_id),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 12. Cart Items
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_cart_items (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    cart_id INT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 1,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (cart_id) REFERENCES #__shop_cart(id) ON DELETE CASCADE,
                    FOREIGN KEY (variant_id) REFERENCES #__shop_product_variant(id) ON DELETE CASCADE,
                    UNIQUE KEY cart_variant (cart_id, variant_id),
                    INDEX idx_cart_id (cart_id),
                    INDEX idx_variant_id (variant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 13. Orders
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_orders (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    order_number VARCHAR(50) NOT NULL,
                    customer_id BIGINT UNSIGNED DEFAULT NULL,
                    status ENUM('pending','paid','processing','shipped','completed','cancelled','refunded') DEFAULT 'pending',
                    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    shipping DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    currency CHAR(3) NOT NULL DEFAULT 'GBP',
                    payment_method VARCHAR(100) DEFAULT NULL,
                    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
                    shipping_name VARCHAR(255) DEFAULT NULL,
                    shipping_address TEXT DEFAULT NULL,
                    shipping_city VARCHAR(100) DEFAULT NULL,
                    shipping_postcode VARCHAR(30) DEFAULT NULL,
                    shipping_country CHAR(2) DEFAULT NULL,
                    billing_name VARCHAR(255) DEFAULT NULL,
                    billing_address TEXT DEFAULT NULL,
                    billing_city VARCHAR(100) DEFAULT NULL,
                    billing_postcode VARCHAR(30) DEFAULT NULL,
                    billing_country CHAR(2) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    platform VARCHAR(50) DEFAULT 'web',
                    platform_order_id VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    INDEX idx_customer (customer_id),
                    INDEX idx_status (status),
                    INDEX idx_payment_status (payment_status),
                    INDEX idx_created (created_at),
                    INDEX idx_deleted (deleted_at),
                    INDEX idx_platform (platform),
                    INDEX idx_platform_order (platform, platform_order_id),
                    UNIQUE KEY uk_order_number (order_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 14. Order Items
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_order_items (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    order_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED DEFAULT NULL,
                    sku VARCHAR(100) DEFAULT NULL,
                    name VARCHAR(255) NOT NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 1,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    options JSON DEFAULT NULL,
                    external_id VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (order_id) REFERENCES #__shop_orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (variant_id) REFERENCES #__shop_product_variant(id) ON DELETE SET NULL,
                    INDEX idx_order (order_id),
                    INDEX idx_variant (variant_id),
                    INDEX idx_sku (sku),
                    INDEX idx_external (external_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 15. Shipments
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_shipments (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    order_id BIGINT UNSIGNED NOT NULL,
                    carrier VARCHAR(100) DEFAULT NULL,
                    service VARCHAR(100) DEFAULT NULL,
                    tracking_number VARCHAR(255) DEFAULT NULL,
                    status ENUM('pending','processing','shipped','in_transit','delivered','returned','cancelled') DEFAULT 'pending',
                    shipped_at DATETIME DEFAULT NULL,
                    delivered_at DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (order_id) REFERENCES #__shop_orders(id) ON DELETE CASCADE,
                    INDEX idx_order (order_id),
                    INDEX idx_tracking (tracking_number),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 16. Transactions
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_transactions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    order_id BIGINT UNSIGNED NOT NULL,
                    gateway VARCHAR(100) DEFAULT NULL,
                    transaction_id VARCHAR(255) DEFAULT NULL,
                    type ENUM('payment','refund','capture','void') DEFAULT 'payment',
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    currency CHAR(3) NOT NULL DEFAULT 'GBP',
                    status ENUM('pending','completed','failed','refunded','cancelled') DEFAULT 'pending',
                    response JSON DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (order_id) REFERENCES #__shop_orders(id) ON DELETE CASCADE,
                    INDEX idx_order (order_id),
                    INDEX idx_transaction (transaction_id),
                    INDEX idx_gateway (gateway),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 17. Sales Channels
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_channel (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(50) NOT NULL,
                    channel_name VARCHAR(100) NOT NULL,
                    type ENUM('marketplace','social','website','other') DEFAULT 'marketplace',
                    marketplace VARCHAR(100) DEFAULT NULL,
                    settings JSON DEFAULT NULL,
                    status ENUM('active','inactive','error') DEFAULT 'inactive',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_type (type),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 18. Product ↔ Channel Mapping
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_channel_product (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    channel_id INT UNSIGNED NOT NULL,
                    product_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED DEFAULT NULL,
                    external_id VARCHAR(255) DEFAULT NULL,
                    external_sku VARCHAR(255) DEFAULT NULL,
                    external_url VARCHAR(500) DEFAULT NULL,
                    status ENUM('pending','active','ended','error') DEFAULT 'pending',
                    last_sync DATETIME DEFAULT NULL,
                    sync_error TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (channel_id) REFERENCES #__shop_channel(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES #__shop_product(id) ON DELETE CASCADE,
                    FOREIGN KEY (variant_id) REFERENCES #__shop_product_variant(id) ON DELETE CASCADE,
                    UNIQUE KEY channel_listing (channel_id, product_id, variant_id),
                    INDEX idx_channel (channel_id),
                    INDEX idx_product (product_id),
                    INDEX idx_variant (variant_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 19. Channel ↔ Order Mapping
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_channel_order (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    channel_id INT UNSIGNED NOT NULL,
                    order_id BIGINT UNSIGNED DEFAULT NULL,
                    external_order_id VARCHAR(150) NOT NULL,
                    status VARCHAR(50) DEFAULT NULL,
                    raw_data JSON DEFAULT NULL,
                    last_sync DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    FOREIGN KEY (channel_id) REFERENCES #__shop_channel(id) ON DELETE CASCADE,
                    FOREIGN KEY (order_id) REFERENCES #__shop_orders(id) ON DELETE SET NULL,
                    UNIQUE KEY external_order (channel_id, external_order_id),
                    INDEX idx_channel (channel_id),
                    INDEX idx_order (order_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ============================================================
            // 20. Channel Messages
            // ============================================================
            $db->query("
                CREATE TABLE IF NOT EXISTS #__shop_channel_message (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    channel_id INT UNSIGNED NOT NULL,
                    external_message_id VARCHAR(100) NOT NULL,
                    from_name VARCHAR(255) DEFAULT NULL,
                    from_email VARCHAR(255) DEFAULT NULL,
                    subject VARCHAR(500) DEFAULT NULL,
                    message TEXT DEFAULT NULL,
                    customer_id VARCHAR(100) DEFAULT NULL,
                    attachment JSON DEFAULT NULL,
                    status ENUM('new','read','replied','archived') DEFAULT 'new',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    replied_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    FOREIGN KEY (channel_id) REFERENCES #__shop_channel(id) ON DELETE CASCADE,
                    UNIQUE KEY channel_message (channel_id, external_message_id),
                    INDEX idx_channel (channel_id),
                    INDEX idx_customer (customer_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $db->query("COMMIT");
        } catch (Exception $e) {
            // Only roll back if there's an active transaction
            try {
                if ($db->inTransaction()) {
                    $db->query("ROLLBACK");
                }
            } catch (Exception $ex) {
                // Ignore rollback failures — transaction may already be closed
            }
            throw new RuntimeException("Installation failed: " . $e->getMessage());
        }
    }

    public function uninstall(Registry $registry, Database $db): void
    {
        try {
            $db->query("START TRANSACTION");

            $tables = [
                '#__shop_channel_message',
                '#__shop_channel_order',
                '#__shop_channel_product',
                '#__shop_channel',
                '#__shop_transactions',
                '#__shop_shipments',
                '#__shop_order_items',
                '#__shop_orders',
                '#__shop_cart_items',
                '#__shop_cart',
                '#__shop_inventory_log',
                '#__shop_inventory',
                '#__shop_product_variant_option',
                '#__shop_product_variant',
                '#__shop_product_option_group',
                '#__shop_option_value',
                '#__shop_option_group',
                '#__shop_product_image',
                '#__shop_product',
                '#__shop_categories',
            ];

            foreach ($tables as $table) {
                $db->query("DROP TABLE IF EXISTS {$table}");
            }

            $db->query("COMMIT");
        } catch (Exception $e) {
            try {
                if ($db->inTransaction()) {
                    $db->query("ROLLBACK");
                }
            } catch (Exception $ex) {}
            throw new RuntimeException("Uninstallation failed: " . $e->getMessage());
        }
    }
};