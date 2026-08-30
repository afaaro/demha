<?php

use System\Engine\Registry;
use System\Library\Database;

return new class {
    public function install(Registry $registry, Database $db): void
    {
        // ============================================
        // CATEGORIES
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL UNIQUE,
                `description` TEXT NULL,
                `parent_id` INT DEFAULT NULL,
                `icon` VARCHAR(255) NULL,
                `sort_order` INT DEFAULT 0,
                `total_content` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`parent_id`) REFERENCES `#__dacwah_categories`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // SCHOLARS / SPEAKERS
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_scholars` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(150) NOT NULL UNIQUE,
                `arabic_name` VARCHAR(150) NULL,
                `bio` TEXT NULL,
                `photo` VARCHAR(255) NULL,
                `country` VARCHAR(100) NULL,
                `website` VARCHAR(255) NULL,
                `email` VARCHAR(150) NULL,
                `facebook` VARCHAR(255) NULL,
                `twitter` VARCHAR(255) NULL,
                `youtube` VARCHAR(255) NULL,
                `status` ENUM('active','inactive','deceased') DEFAULT 'active',
                `sort_order` INT DEFAULT 0,
                `total_lectures` INT DEFAULT 0,
                `total_series` INT DEFAULT 0,
                `total_books` INT DEFAULT 0,
                `total_articles` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // LECTURE SERIES (e.g., "Seerah Series", "40 Hadith")
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_series` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL UNIQUE,
                `description` TEXT NULL,
                `scholar_id` INT NOT NULL,
                `category_id` INT NOT NULL,
                `thumbnail` VARCHAR(255) NULL,
                `total_lessons` INT DEFAULT 0,
                `completed` TINYINT(1) DEFAULT 0,
                `sort_order` INT DEFAULT 0,
                `is_featured` TINYINT(1) DEFAULT 0,
                `publish_date` DATE NULL,
                `status` ENUM('draft','published','archived') DEFAULT 'published',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`scholar_id`) REFERENCES `#__dacwah_scholars`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`category_id`) REFERENCES `#__dacwah_categories`(`id`) ON DELETE CASCADE,
                INDEX `idx_scholar` (`scholar_id`),
                INDEX `idx_category` (`category_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // LECTURES (Audio & Video — main content)
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_lectures` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL UNIQUE,
                `description` TEXT NULL,
                `scholar_id` INT NOT NULL,
                `category_id` INT NOT NULL,
                `series_id` INT DEFAULT NULL,
                `lesson_number` INT DEFAULT NULL,
                `type` ENUM('audio','video') DEFAULT 'audio',
                `thumbnail` VARCHAR(255) NULL,
                `duration` INT DEFAULT 0,
                `publish_date` DATE NULL,
                `views` INT DEFAULT 0,
                `downloads` INT DEFAULT 0,
                `is_featured` TINYINT(1) DEFAULT 0,
                `status` ENUM('draft','published','archived') DEFAULT 'published',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`scholar_id`) REFERENCES `#__dacwah_scholars`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`category_id`) REFERENCES `#__dacwah_categories`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`series_id`) REFERENCES `#__dacwah_series`(`id`) ON DELETE SET NULL,
                INDEX `idx_scholar` (`scholar_id`),
                INDEX `idx_category` (`category_id`),
                INDEX `idx_series` (`series_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_featured` (`is_featured`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // MEDIA FILES (Multiple qualities / formats)
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_media_files` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `lecture_id` INT NOT NULL,
                `quality` VARCHAR(20) DEFAULT 'default',
                `format` VARCHAR(20) NOT NULL,
                `file_url` VARCHAR(255) NOT NULL,
                `file_size` BIGINT DEFAULT 0,
                `bandwidth` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`lecture_id`) REFERENCES `#__dacwah_lectures`(`id`) ON DELETE CASCADE,
                INDEX `idx_lecture` (`lecture_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // BOOKS
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_books` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL UNIQUE,
                `description` TEXT NULL,
                `scholar_id` INT NOT NULL,
                `category_id` INT NOT NULL,
                `author_name` VARCHAR(255) NULL,
                `publisher` VARCHAR(255) NULL,
                `publish_year` INT NULL,
                `isbn` VARCHAR(30) NULL,
                `pages` INT NULL,
                `cover_image` VARCHAR(255) NULL,
                `file_url` VARCHAR(255) NULL,
                `file_size` BIGINT DEFAULT 0,
                `language` VARCHAR(50) DEFAULT 'en',
                `downloads` INT DEFAULT 0,
                `is_featured` TINYINT(1) DEFAULT 0,
                `status` ENUM('draft','published') DEFAULT 'published',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`scholar_id`) REFERENCES `#__dacwah_scholars`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`category_id`) REFERENCES `#__dacwah_categories`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // ARTICLES / FATWAS
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_articles` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL UNIQUE,
                `content` TEXT NOT NULL,
                `scholar_id` INT NULL,
                `category_id` INT NOT NULL,
                `article_type` ENUM('article','fatwa','answer') DEFAULT 'article',
                `reference` VARCHAR(255) NULL,
                `views` INT DEFAULT 0,
                `is_featured` TINYINT(1) DEFAULT 0,
                `status` ENUM('draft','published') DEFAULT 'published',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`scholar_id`) REFERENCES `#__dacwah_scholars`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`category_id`) REFERENCES `#__dacwah_categories`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // TAGS
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_tags` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL UNIQUE,
                `slug` VARCHAR(50) NOT NULL UNIQUE,
                `usage_count` INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // CONTENT ↔ TAGS (Polymorphic)
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_content_tag` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `content_type` ENUM('series','lecture','book','article') NOT NULL,
                `content_id` INT NOT NULL,
                `tag_id` INT NOT NULL,
                FOREIGN KEY (`tag_id`) REFERENCES `#__dacwah_tags`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `unique_tagging` (`content_type`, `content_id`, `tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ============================================
        // FAVORITES
        // ============================================
        $db->query("
            CREATE TABLE `#__dacwah_favorites` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `content_type` ENUM('series','lecture','book','article') NOT NULL,
                `content_id` INT NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `#__users`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `unique_favorite` (`user_id`, `content_type`, `content_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Seed default categories
        $this->seedDefaults($db);
    }

    public function uninstall(Registry $registry, Database $db): void
    {
        // Drop in dependency order (foreign keys first)
        $db->query("DROP TABLE IF EXISTS `#__dacwah_favorites`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_content_tag`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_tags`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_media_files`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_lectures`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_books`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_articles`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_series`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_scholars`");
        $db->query("DROP TABLE IF EXISTS `#__dacwah_categories`");
    }

    private function seedDefaults(Database $db): void
    {
        // Main Categories
        $categories = [
            ['name' => 'Quran',          'slug' => 'quran',          'description' => 'Quran recitation, tafsir, and explanations'],
            ['name' => 'Hadith',         'slug' => 'hadith',         'description' => 'Prophetic traditions and explanations'],
            ['name' => 'Fiqh',           'slug' => 'fiqh',           'description' => 'Islamic jurisprudence and rulings'],
            ['name' => 'Aqeedah',        'slug' => 'aqeedah',        'description' => 'Islamic theology and beliefs'],
            ['name' => 'Seerah',         'slug' => 'seerah',         'description' => 'Biography of the Prophet ﷺ'],
            ['name' => 'Islamic History', 'slug' => 'islamic-history','description' => 'History of Islam and Muslim civilization'],
            ['name' => 'Dawah',          'slug' => 'dawah',          'description' => 'Inviting to Allah and comparative religion'],
            ['name' => 'Character & Morals','slug' => 'morals',       'description' => 'Akhlak, manners, and spiritual purification'],
            ['name' => 'Family & Society','slug' => 'family',        'description' => 'Marriage, parenting, and social issues'],
            ['name' => 'Ramadan',        'slug' => 'ramadan',        'description' => 'Fasting, taraweeh, and Ramadan guidance'],
        ];

        foreach ($categories as $i => $cat) {
            $db->insert('#__dacwah_categories', [
                'name'        => $cat['name'],
                'slug'        => $cat['slug'],
                'description' => $cat['description'],
                'sort_order'  => $i + 1,
            ]);
        }
    }
};