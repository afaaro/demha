<?php

use System\Engine\Registry;
use System\Library\Database;

return new class {
    public function install(Registry $registry, Database $db): void
    {
        // Disable foreign key checks
        $db->query("SET FOREIGN_KEY_CHECKS = 0;");
        // Enable transactions for atomicity (if supported)
        $db->query("START TRANSACTION");

        try {
            // 1. Create Categories table (no dependencies)
            $db->query("CREATE TABLE IF NOT EXISTS `#__news_categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) UNIQUE NOT NULL,
                `description` TEXT,
                `parent_id` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_news_categories_parent` 
                    FOREIGN KEY (`parent_id`)
                    REFERENCES `#__news_categories` (`id`) 
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // 2. Create Tags table
            $db->query("CREATE TABLE IF NOT EXISTS `#__news_tags` (
                `tag_id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) UNIQUE NOT NULL,
                `slug` VARCHAR(50) UNIQUE NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // 3. Create News table (now safe to reference categories & users)
            $db->query("CREATE TABLE IF NOT EXISTS `#__news` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `body` LONGTEXT NOT NULL,
                `category_id` INT NOT NULL,
                `author_id` INT NOT NULL DEFAULT 1,
                `views` INT DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_news_slug` (`slug`),
                KEY `idx_news_status` (`status`),
                KEY `idx_news_featured` (`is_featured`),
                CONSTRAINT `fk_news_category` FOREIGN KEY (`category_id`)
                    REFERENCES `#__news_categories`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_news_author` FOREIGN KEY (`author_id`)
                    REFERENCES `#__users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->query("CREATE TABLE IF NOT EXISTS `#__news_views` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `article_id` int(11) NOT NULL,
                `ip` varchar(45) NOT NULL,
                `user_agent` varchar(255) DEFAULT NULL,
                `viewed_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_article_ip_date` (`article_id`, `ip`, `viewed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // 4. Create News-to-Tags pivot table
            $db->query("CREATE TABLE IF NOT EXISTS `#__news_to_tags` (
                `news_id` BIGINT UNSIGNED NOT NULL,
                `tag_id` INT NOT NULL,
                PRIMARY KEY (`news_id`, `tag_id`),
                CONSTRAINT `fk_news_tags_news` FOREIGN KEY (`news_id`)
                    REFERENCES `#__news`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_news_to_tags_tag` FOREIGN KEY (`tag_id`)
                    REFERENCES `#__news_tags`(`tag_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Commit if all succeeded
            $db->query("COMMIT");

        } catch (Exception $e) {
            // Rollback on failure and re-throw or handle gracefully
            $db->query("ROLLBACK");
            throw new RuntimeException("Installation failed: " . $e->getMessage());
        }
    }

    public function uninstall(Registry $registry, Database $db): void
    {
        // Drop tables in reverse order to respect foreign keys
        $db->query("DROP TABLE IF EXISTS `#__news_to_tags`");
        $db->query("DROP TABLE IF EXISTS `#__news`");
        $db->query("DROP TABLE IF EXISTS `#__news_tags`");
        $db->query("DROP TABLE IF EXISTS `#__news_categories`");
        $db->query("DROP TABLE IF EXISTS `#__news_views`");
    }
};