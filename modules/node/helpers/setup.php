<?php

use System\Engine\Registry;
use System\Library\Database;

return new class {
    public function install(Registry $registry, Database $db): void
    {
        // ─── BUNDLES (content types) ───
        $db->query("
            CREATE TABLE `#__node_bundles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `machine_name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── FIELD DEFINITIONS ───
        $db->query("
            CREATE TABLE `#__node_fields` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `bundle` VARCHAR(50) NOT NULL,
                `field_name` VARCHAR(50) NOT NULL,
                `field_type` ENUM('text','textarea','richtext','number','date','image','file','select','checkbox','taxonomy','entity_reference') NOT NULL,
                `label` VARCHAR(100) NOT NULL,
                `required` TINYINT(1) DEFAULT 0,
                `settings` JSON NULL,
                `weight` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `bundle_field` (`bundle`, `field_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── ENTITIES (core content) ───
        $db->query("
            CREATE TABLE `#__node_entities` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `bundle` VARCHAR(50) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(100) NULL,
                `status` ENUM('draft','published','archived') DEFAULT 'draft',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `slug` (`slug`),
                KEY `bundle` (`bundle`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── ENTITY VALUES (EAV custom fields) ───
        $db->query("
            CREATE TABLE `#__node_values` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_id` INT UNSIGNED NOT NULL,
                `field_name` VARCHAR(50) NOT NULL,
                `value` TEXT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entity_field` (`entity_id`, `field_name`),
                KEY `entity_id` (`entity_id`),
                KEY `field_name` (`field_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── TAXONOMY VOCABULARIES ───
        $db->query("
            CREATE TABLE `#__node_taxonomy_vocabularies` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `machine_name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── TAXONOMY TERMS ───
        $db->query("
            CREATE TABLE `#__node_taxonomy_terms` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `vocabulary_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `parent_id` INT UNSIGNED DEFAULT NULL,
                `description` TEXT NULL,
                `weight` INT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `vocab_slug` (`vocabulary_id`, `slug`),
                KEY `vocabulary_id` (`vocabulary_id`),
                KEY `parent_id` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── ENTITY ↔ TERM RELATIONSHIP (CRITICAL MISSING TABLE!) ───
        $db->query("
            CREATE TABLE `#__node_entity_taxonomy` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_id` INT UNSIGNED NOT NULL,
                `term_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entity_term` (`entity_id`, `term_id`),
                KEY `entity_id` (`entity_id`),
                KEY `term_id` (`term_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── REVISIONS ───
        $db->query("
            CREATE TABLE `#__node_revisions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_id` INT UNSIGNED NOT NULL,
                `data` JSON NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `entity_id` (`entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── SEARCH INDEX ───
        $db->query("
            CREATE TABLE `#__node_search` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_id` INT UNSIGNED NOT NULL,
                `content` TEXT NOT NULL,
                PRIMARY KEY (`id`),
                FULLTEXT KEY `search_idx` (`content`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ─── FOREIGN KEYS ───
        $db->query("ALTER TABLE `#__node_fields` 
            ADD FOREIGN KEY (`bundle`) REFERENCES `#__node_bundles`(`machine_name`) ON DELETE CASCADE");

        $db->query("ALTER TABLE `#__node_entities` 
            ADD FOREIGN KEY (`bundle`) REFERENCES `#__node_bundles`(`machine_name`) ON DELETE RESTRICT");

        $db->query("ALTER TABLE `#__node_values` 
            ADD FOREIGN KEY (`entity_id`) REFERENCES `#__node_entities`(`id`) ON DELETE CASCADE");

        $db->query("ALTER TABLE `#__node_entity_taxonomy` 
            ADD FOREIGN KEY (`entity_id`) REFERENCES `#__node_entities`(`id`) ON DELETE CASCADE,
            ADD FOREIGN KEY (`term_id`) REFERENCES `#__node_taxonomy_terms`(`id`) ON DELETE CASCADE");

        $db->query("ALTER TABLE `#__node_revisions` 
            ADD FOREIGN KEY (`entity_id`) REFERENCES `#__node_entities`(`id`) ON DELETE CASCADE");

        $db->query("ALTER TABLE `#__node_search` 
            ADD FOREIGN KEY (`entity_id`) REFERENCES `#__node_entities`(`id`) ON DELETE CASCADE");

        $db->query("ALTER TABLE `#__node_taxonomy_terms` 
            ADD FOREIGN KEY (`vocabulary_id`) REFERENCES `#__node_taxonomy_vocabularies`(`id`) ON DELETE CASCADE,
            ADD FOREIGN KEY (`parent_id`) REFERENCES `#__node_taxonomy_terms`(`id`) ON DELETE SET NULL");

        // ─── Seed default data ───
        $this->seedDefaults($db);
    }

    public function uninstall(Registry $registry, Database $db): void
    {
        // Drop in correct order (FK dependency)
        $db->query("DROP TABLE IF EXISTS `#__node_entity_taxonomy`");
        $db->query("DROP TABLE IF EXISTS `#__node_values`");
        $db->query("DROP TABLE IF EXISTS `#__node_search`");
        $db->query("DROP TABLE IF EXISTS `#__node_revisions`");
        $db->query("DROP TABLE IF EXISTS `#__node_taxonomy_terms`");
        $db->query("DROP TABLE IF EXISTS `#__node_taxonomy_vocabularies`");
        $db->query("DROP TABLE IF EXISTS `#__node_fields`");
        $db->query("DROP TABLE IF EXISTS `#__node_entities`");
        $db->query("DROP TABLE IF EXISTS `#__node_bundles`");
    }

    private function seedDefaults(Database $db): void
    {

    }
};