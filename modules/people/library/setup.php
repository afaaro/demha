<?php

use System\Engine\Registry;
use System\Library\Database;

return new class {
    public function install(Registry $registry, Database $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS `#__people_relationship` (
			`id` int(10) NOT NULL AUTO_INCREMENT,
			`male_id` int(10) NOT NULL,
			`female_id` int(10) NOT NULL,
			`marital_status` enum('married','divorced','widowed','friend') NOT NULL DEFAULT 'married',
			PRIMARY KEY (`id`),
			KEY `male_id` (`male_id`),
			KEY `female_id` (`female_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `#__people_member` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `mother_id` int(11) NOT NULL DEFAULT '-1',
            `father_id` int(11) NOT NULL DEFAULT '-1',
            `name` varchar(150) NOT NULL,
            `fullname` varchar(500) NOT NULL,
            `gender` tinyint(1) NOT NULL DEFAULT '1',
            `is_alive` tinyint(1) NOT NULL DEFAULT '1',
            `dob` date DEFAULT '0000-00-00',
            `dod` date DEFAULT '0000-00-00',
            `living` varchar(20) NOT NULL,
            `photo` varchar(300) NOT NULL,
            `notes` text NOT NULL,
            `visible` tinyint(1) NOT NULL DEFAULT '1',
            `marital_status` enum('single','married','divorced','widowed') NOT NULL DEFAULT 'single',
            `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `fullname` (`fullname`),
            KEY `gender` (`gender`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS `#__people_tribe` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function uninstall(Registry $registry, Database $db): void
    {
        $db->query("DROP TABLE IF EXISTS `#__people_relationship`");
        $db->query("DROP TABLE IF EXISTS `#__people_member`");
        $db->query("DROP TABLE IF EXISTS `#__people_tribe`");
    }
};