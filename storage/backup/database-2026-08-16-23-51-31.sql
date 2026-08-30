-- Database Backup
-- Generated: 2026-08-16 23:51:31

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `block`;
CREATE TABLE `block` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `region` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_type` enum('html','module') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'html',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visibility` enum('include','exclude') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'include',
  `paths` text COLLATE utf8mb4_unicode_ci,
  `modules` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `weight` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_block_region` (`region`),
  KEY `idx_block_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `block` (`id`, `title`, `region`, `body_type`, `body`, `visibility`, `paths`, `modules`, `status`, `weight`, `created_at`, `updated_at`) VALUES ('1', 'test', 'sidebar_left', 'html', 'hello buddy', 'exclude', 'news\r\nnews/*', '', '0', '0', '2026-08-13 07:02:08', '2026-08-14 20:27:34');

DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` int unsigned NOT NULL DEFAULT '0',
  `user_id` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `size` bigint unsigned NOT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `alt` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_folder` (`folder_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `media_folder`;
CREATE TABLE `media_folder` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `media_folder` (`id`, `parent_id`, `name`, `created_at`) VALUES ('1', '0', 'News', '2026-08-16 00:21:00');

DROP TABLE IF EXISTS `media_relation`;
CREATE TABLE `media_relation` (
  `media_id` int unsigned NOT NULL,
  `module` varchar(100) NOT NULL,
  `item_id` int unsigned NOT NULL,
  `sort` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`media_id`,`module`,`item_id`),
  KEY `idx_module_item` (`module`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `module`;
CREATE TABLE `module` (
  `module` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `installed` tinyint(1) NOT NULL DEFAULT '1',
  `installed_at` datetime DEFAULT NULL,
  `uninstalled_at` datetime DEFAULT NULL,
  `upgraded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `module` (`module`, `path`, `is_system`, `installed`, `installed_at`, `uninstalled_at`, `upgraded_at`, `created_at`, `updated_at`) VALUES ('diinta', 'modules/diinta', '0', '0', '2026-08-15 22:34:50', '2026-08-16 11:12:23', NULL, '2026-08-14 19:21:13', '2026-08-16 11:12:23');
INSERT INTO `module` (`module`, `path`, `is_system`, `installed`, `installed_at`, `uninstalled_at`, `upgraded_at`, `created_at`, `updated_at`) VALUES ('news', 'modules/news', '0', '0', '2026-08-13 21:56:53', '2026-08-14 19:58:19', NULL, '2026-08-13 06:37:42', '2026-08-14 19:58:19');
INSERT INTO `module` (`module`, `path`, `is_system`, `installed`, `installed_at`, `uninstalled_at`, `upgraded_at`, `created_at`, `updated_at`) VALUES ('people', 'modules/people', '0', '1', '2026-08-14 23:38:50', NULL, NULL, '2026-08-14 23:38:50', '2026-08-14 23:38:50');
INSERT INTO `module` (`module`, `path`, `is_system`, `installed`, `installed_at`, `uninstalled_at`, `upgraded_at`, `created_at`, `updated_at`) VALUES ('shop', 'modules/shop', '0', '1', '2026-08-16 11:12:28', NULL, NULL, '2026-08-16 11:12:28', '2026-08-16 11:12:28');
INSERT INTO `module` (`module`, `path`, `is_system`, `installed`, `installed_at`, `uninstalled_at`, `upgraded_at`, `created_at`, `updated_at`) VALUES ('tools', 'system/modules/tools', '1', '1', '2026-08-12 08:47:34', NULL, NULL, '2026-08-12 08:47:34', '2026-08-13 06:35:33');
INSERT INTO `module` (`module`, `path`, `is_system`, `installed`, `installed_at`, `uninstalled_at`, `upgraded_at`, `created_at`, `updated_at`) VALUES ('user', 'system/modules/user', '1', '1', '2026-08-12 08:47:34', NULL, NULL, '2026-08-12 08:47:34', '2026-08-13 06:35:33');

DROP TABLE IF EXISTS `people_member`;
CREATE TABLE `people_member` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mother_id` int NOT NULL DEFAULT '-1',
  `father_id` int NOT NULL DEFAULT '-1',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'male',
  `is_alive` tinyint(1) NOT NULL DEFAULT '1',
  `dob` date DEFAULT '0000-00-00',
  `dod` date DEFAULT '0000-00-00',
  `living` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `marital_status` enum('single','married','divorced','widowed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `fullname` (`fullname`),
  KEY `gender` (`gender`)
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('1', '-1', '-1', 'Abaali', 'Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', '', '2019-09-11 20:28:35');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('2', '-1', '1', 'Maxamed', 'Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-11 20:29:15');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('3', '-1', '1', 'Abuubakar', 'Abuubakar Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-11 20:30:49');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('4', '-1', '1', 'Yuusuf', 'Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-11 20:30:57');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('5', '-1', '2', 'Faqay caalim', 'Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-11 20:50:48');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('6', '-1', '5', 'Amiin Sadiiq', 'Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-11 20:51:13');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('7', '-1', '6', 'Axmed', 'Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:56:30');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('8', '-1', '7', 'Shariifoow', 'Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:56:41');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('9', '-1', '8', 'Aamin', 'Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:56:51');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('10', '-1', '9', 'Axmed', 'Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:57:01');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('11', '-1', '10', 'Sheekh Maxamed', 'Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:57:13');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('12', '-1', '11', 'Shariif', 'Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:57:23');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('13', '-1', '12', 'Awees', 'Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:57:33');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('14', '-1', '13', 'Cali', 'Cali Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:57:43');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('15', '-1', '14', 'Suufi', 'Suufi Cali Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:57:52');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('16', '-1', '15', 'Maxamed', 'Maxamed Suufi Cali Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:58:08');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('17', '-1', '8', 'Cabdi', 'Cabdi Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:58:52');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('18', '-1', '8', 'Xaaji', 'Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:59:04');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('19', '-1', '18', 'Cusmaan', 'Cusmaan Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:59:24');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('20', '-1', '19', 'Shiikheey', 'Shiikheey Cusmaan Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:59:43');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('21', '-1', '18', 'Nuur', 'Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 18:59:58');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('22', '-1', '21', 'Iikar', 'Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:00:09');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('23', '-1', '21', 'Mooye', 'Mooye Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:00:18');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('24', '-1', '22', 'Muxyidiin', 'Muxyidiin Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:00:31');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('25', '-1', '22', 'Mooye', 'Mooye Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:00:37');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('26', '-1', '22', 'Cusmaan', 'Cusmaan Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:00:45');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('27', '-1', '22', 'Maclin', 'Maclin Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:00:52');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('28', '-1', '18', 'Ikraam', 'Ikraam Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:01:22');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('29', '-1', '6', 'Cusmaan', 'Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:11:46');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('30', '-1', '6', 'Maxamed', 'Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:12:05');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('31', '-1', '30', 'Axmed', 'Axmed Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:12:23');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('32', '-1', '31', 'Abraar', 'Abraar Axmed Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:12:31');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('33', '-1', '31', 'Cali', 'Cali Abraar Axmed Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:12:40');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('34', '-1', '29', 'Abubakar', 'Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:15:30');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('35', '-1', '34', 'Cusmaan', 'Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:15:41');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('36', '-1', '34', 'Yaxya', 'Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:15:50');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('37', '-1', '35', 'Maxamed', 'Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:16:02');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('38', '-1', '37', 'Maclin', 'Maclin Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:16:12');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('39', '-1', '38', 'Reer Yaawali', 'Reer Yaawali Maclin Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:16:21');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('40', '-1', '38', 'Reer Shariif', 'Reer Shariif Maclin Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:16:28');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('41', '-1', '36', 'Macow', 'Macow Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:16:49');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('42', '-1', '36', 'Cumar', 'Cumar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:16:57');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('43', '-1', '36', 'Nuur', 'Nuur Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:17:04');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('44', '-1', '36', 'Abubakar', 'Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:17:11');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('45', '-1', '36', 'Amiin', 'Amiin Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:17:20');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('46', '-1', '43', 'Reer Amoo Axmed', 'Reer Amoo Axmed Nuur Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:17:34');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('47', '-1', '44', 'Nuurow', 'Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:17:49');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('48', '-1', '47', 'Cusmaan', 'Cusmaan Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:17:57');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('49', '-1', '48', 'Cabduraxman', 'Cabduraxman Cusmaan Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:18:05');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('50', '-1', '49', 'Xaaji Shariif', 'Xaaji Shariif Cabduraxman Cusmaan Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-13 19:18:14');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('51', '-1', '4', 'Xarameen', 'Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:45:50');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('52', '-1', '51', 'Cumar', 'Cumar Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:46:09');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('53', '-1', '51', 'Cali', 'Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:46:18');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('54', '-1', '51', 'Cusmaan', 'Cusmaan Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:46:32');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('55', '-1', '53', 'Abashiikh', 'Abashiikh Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:47:14');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('56', '-1', '53', 'Amiin (warmoog)', 'Amiin (warmoog) Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:47:24');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('57', '-1', '53', 'Abubakar', 'Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:47:33');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('58', '-1', '57', 'Amiin', 'Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:47:59');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('59', '-1', '57', 'Cabdalle', 'Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:48:08');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('60', '-1', '57', 'Maxamuud', 'Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:48:20');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('61', '-1', '57', 'Nuur', 'Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:48:27');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('62', '-1', '61', 'Maxamed', 'Maxamed Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:48:53');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('63', '-1', '61', 'Axmed', 'Axmed Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:49:00');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('64', '-1', '61', 'Maxamed (Xaaji Maye)', 'Maxamed (Xaaji Maye) Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:49:08');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('65', '-1', '61', 'Abukar (Xaaji Maye)', 'Abukar (Xaaji Maye) Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:49:21');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('66', '-1', '60', 'Imaankow', 'Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:50:05');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('67', '-1', '66', 'Waliyoo', 'Waliyoo Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:50:17');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('68', '-1', '66', 'Suufi', 'Suufi Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:50:23');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('69', '-1', '66', 'Shiikheey', 'Shiikheey Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:50:29');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('70', '-1', '66', 'Awees', 'Awees Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:50:35');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('71', '-1', '58', 'Axmed', 'Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:51:20');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('72', '-1', '71', 'Maxamuud (Dabiye)', 'Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:51:49');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('73', '-1', '72', 'Axmed', 'Axmed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:52:04');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('74', '-1', '73', 'Maxamed (Sheekh Abba)', 'Maxamed (Sheekh Abba) Axmed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:52:17');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('75', '-1', '72', 'Maxamed', 'Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:52:50');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('76', '-1', '75', 'Axmed', 'Axmed Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:52:58');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('77', '-1', '75', 'Cabdalle', 'Cabdalle Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:53:05');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('78', '-1', '75', 'Jeylaani', 'Jeylaani Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:53:12');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('79', '-1', '71', 'Nuur (Boodoo)', 'Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:54:09');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('80', '-1', '71', 'Maxamed', 'Maxamed Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:54:20');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('81', '-1', '80', 'Reer Shiikheey Maxamed Shiikh Awees (Garbood)', 'Reer Shiikheey Maxamed Shiikh Awees (Garbood) Maxamed Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:54:55');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('82', '-1', '79', 'Axmed', 'Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:55:16');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('83', '-1', '82', 'Cadoow', 'Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:55:32');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('84', '-1', '83', 'Cusmaan', 'Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:55:43');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('85', '-1', '84', 'Awees', 'Awees Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:55:51');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('86', '-1', '84', 'Xaaji', 'Xaaji Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:55:57');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('87', '-1', '84', 'Imaankeey', 'Imaankeey Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:56:03');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('88', '-1', '84', 'Sayyid', 'Sayyid Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:56:08');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('89', '-1', '85', 'Maxamed', 'Maxamed Awees Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:56:17');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('90', '-1', '58', 'Maye', 'Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:57:18');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('91', '-1', '90', 'Cabdalle', 'Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:57:33');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('92', '-1', '91', 'Nuur', 'Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:57:45');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('93', '-1', '92', 'Maxamed', 'Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:57:59');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('94', '-1', '92', 'Cabdalle', 'Cabdalle Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:58:05');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('95', '-1', '93', 'Axmed', 'Axmed Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:58:14');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('96', '-1', '95', 'Maxamed', 'Maxamed Axmed Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:58:22');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('97', '-1', '95', 'Banuuri', 'Banuuri Axmed Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:58:28');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('98', '-1', '58', 'Cusmaan', 'Cusmaan Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 02:59:40');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('99', '-1', '59', 'Maxamed', 'Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:02:15');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('100', '-1', '59', 'Cabduraxman', 'Cabduraxman Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:02:26');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('101', '-1', '59', 'Axmed', 'Axmed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:02:31');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('102', '-1', '99', 'Cabdalle', 'Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:02:46');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('103', '-1', '102', 'Maxamed (Macow Shiikh)', 'Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:03:07');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('104', '-1', '102', 'Cumar', 'Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:03:14');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('105', '-1', '102', 'Cabduraxman (Sheekh Suufi)', 'Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:03:25');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('106', '-1', '102', 'Xuseen', 'Xuseen Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:03:34');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('107', '-1', '102', 'Calawi', 'Calawi Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:03:47');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('108', '-1', '103', 'Ibraahim', 'Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:04:12');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('109', '-1', '108', 'Maclin', 'Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:04:20');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('110', '-1', '109', 'Xaaji', 'Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:04:30');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('111', '-1', '109', 'Jeylaani', 'Jeylaani Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:04:37');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('112', '-1', '109', 'Maana', 'Maana Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', '', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:04:47');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('113', '-1', '110', 'Ibraahim (Gaduudow)', 'Ibraahim (Gaduudow) Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:05:09');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('114', '-1', '110', 'Qaasim (Gaduudow)', 'Qaasim (Gaduudow) Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:05:17');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('115', '-1', '110', 'Maxamed (Gaduudow)', 'Maxamed (Gaduudow) Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:05:25');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('116', '-1', '104', 'Cabdi', 'Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:05:44');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('117', '-1', '104', 'Xaaji', 'Xaaji Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:05:49');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('118', '-1', '104', 'Maxamed', 'Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:05:55');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('119', '-1', '116', 'Maxamed', 'Maxamed Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:06:10');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('120', '-1', '119', 'Cusmaan', 'Cusmaan Maxamed Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:06:22');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('121', '-1', '120', 'Axmed', 'Axmed Cusmaan Maxamed Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:06:29');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('122', '-1', '117', 'Abuukar', 'Abuukar Xaaji Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:06:47');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('123', '-1', '122', 'Maxamed (Somali Fruit)', 'Maxamed (Somali Fruit) Abuukar Xaaji Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:06:57');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('124', '-1', '118', 'Axmed', 'Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:07:24');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('125', '-1', '124', 'Cumar', 'Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:07:33');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('126', '-1', '124', 'Shariif', 'Shariif Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:07:41');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('127', '-1', '125', 'Suufi', 'Suufi Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:07:50');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('128', '-1', '127', 'Maxamed (Jeeri)', 'Maxamed (Jeeri) Suufi Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:08:03');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('129', '-1', '127', 'Cumar', 'Cumar Suufi Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:08:09');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('130', '-1', '105', 'Maxamed', 'Maxamed Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:11:43');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('131', '-1', '105', 'Cusmaan', 'Cusmaan Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:11:50');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('132', '-1', '105', 'Abubakar', 'Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:11:56');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('133', '-1', '105', 'Shaami', 'Shaami Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:12:05');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('134', '-1', '132', 'Dheeroow', 'Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:12:50');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('135', '-1', '132', 'Nuureyni', 'Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:13:01');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('136', '-1', '134', 'Dahir', 'Dahir Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:13:49');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('137', '-1', '134', 'Mardaadi', 'Mardaadi Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:13:57');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('138', '-1', '134', 'Khaliif Axmed', 'Khaliif Axmed Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:08');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('139', '-1', '134', 'Baaba Aamac', 'Baaba Aamac Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:17');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('140', '-1', '134', 'Xuseen', 'Xuseen Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:23');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('141', '-1', '134', 'Abba Cali', 'Abba Cali Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:30');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('142', '-1', '134', 'Jeylaani', 'Jeylaani Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:39');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('143', '-1', '134', 'Cumar (Cumushow)', 'Cumar (Cumushow) Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:47');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('144', '-1', '134', 'Cabdallah', 'Cabdallah Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:14:54');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('145', '-1', '134', 'Iikar', 'Iikar Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:15:00');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('146', '-1', '135', 'Axmed', 'Axmed Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:15:29');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('147', '-1', '135', 'Bakar', 'Bakar Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:15:35');
INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES ('148', '-1', '135', 'Muxyiddin', 'Muxyiddin Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', '1', '0000-00-00', '0000-00-00', '', '', '', '1', 'single', '2019-09-14 03:15:41');

DROP TABLE IF EXISTS `people_relationship`;
CREATE TABLE `people_relationship` (
  `id` int NOT NULL AUTO_INCREMENT,
  `male_id` int NOT NULL,
  `female_id` int NOT NULL,
  `marital_status` enum('married','divorced','widowed','friend') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'married',
  PRIMARY KEY (`id`),
  KEY `male_id` (`male_id`),
  KEY `female_id` (`female_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `people_tribe`;
CREATE TABLE `people_tribe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shop_customers`;
CREATE TABLE `shop_customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `addresses` json DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_inventory_history`;
CREATE TABLE `shop_inventory_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_variation_id` int NOT NULL,
  `quantity_change` int NOT NULL,
  `old_quantity` int NOT NULL,
  `new_quantity` int NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_variation` (`product_variation_id`),
  CONSTRAINT `shop_inventory_history_ibfk_1` FOREIGN KEY (`product_variation_id`) REFERENCES `shop_product_variations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_messages`;
CREATE TABLE `shop_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `platform_message_id` varchar(255) DEFAULT NULL,
  `direction` enum('incoming','outgoing') NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text,
  `status` enum('draft','sent','received','read','replied') DEFAULT 'received',
  `attachments` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_platform` (`platform`,`platform_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_order_items`;
CREATE TABLE `shop_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_variation_id` int DEFAULT NULL,
  `sku` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `attributes` json DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `tax` decimal(12,2) DEFAULT '0.00',
  `platform_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `shop_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_orders`;
CREATE TABLE `shop_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled','refunded','failed') DEFAULT 'pending',
  `currency` varchar(3) DEFAULT 'USD',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(12,2) DEFAULT '0.00',
  `shipping_cost` decimal(12,2) DEFAULT '0.00',
  `discount` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `payment_transaction_id` varchar(255) DEFAULT NULL,
  `shipping_method` varchar(100) DEFAULT NULL,
  `shipping_carrier` varchar(100) DEFAULT NULL,
  `shipping_tracking_number` varchar(255) DEFAULT NULL,
  `shipping_status` enum('pending','processing','shipped','delivered') DEFAULT 'pending',
  `estimated_delivery` datetime DEFAULT NULL,
  `billing_address` json DEFAULT NULL,
  `shipping_address` json DEFAULT NULL,
  `customer_notes` text,
  `platform` varchar(50) DEFAULT NULL,
  `platform_order_id` varchar(255) DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_number` (`order_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_platform` (`platform`,`platform_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_platform_sync`;
CREATE TABLE `shop_platform_sync` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `product_variation_id` int DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `platform` varchar(50) NOT NULL,
  `platform_id` varchar(255) DEFAULT NULL,
  `sync_type` enum('push','pull') NOT NULL,
  `entity_type` enum('product','variation','order','message') NOT NULL,
  `data` json DEFAULT NULL,
  `status` enum('pending','processing','success','failed') DEFAULT 'pending',
  `error_message` text,
  `attempts` int DEFAULT '0',
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_platform` (`platform`,`status`),
  KEY `idx_entity` (`entity_type`,`product_id`,`product_variation_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_product_variations`;
CREATE TABLE `shop_product_variations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `sku` varchar(100) NOT NULL,
  `attributes` json NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `images` json DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` json DEFAULT NULL,
  `platform_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sku` (`sku`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `shop_product_variations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shop_products`;
CREATE TABLE `shop_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sku` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `brand` varchar(100) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `base_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) DEFAULT 'USD',
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `is_variable` tinyint(1) DEFAULT '0',
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` json DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sku` (`sku`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `user_group`;
CREATE TABLE `user_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `system` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_group_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8905 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_group` (`id`, `name`, `label`, `description`, `permissions`, `system`, `status`, `created_at`, `updated_at`) VALUES ('1', 'member', 'Member', 'Member system group', '[\"user.account.view\"]', '1', '1', '2026-08-10 07:21:33', '2026-08-10 08:21:33');
INSERT INTO `user_group` (`id`, `name`, `label`, `description`, `permissions`, `system`, `status`, `created_at`, `updated_at`) VALUES ('2', 'admin', 'Administrator', 'Administrator system group', '[\"user.account.view\", \"user.account.edit\", \"user.admin.account.manage\"]', '1', '1', '2026-08-10 07:21:33', '2026-08-10 08:21:33');
INSERT INTO `user_group` (`id`, `name`, `label`, `description`, `permissions`, `system`, `status`, `created_at`, `updated_at`) VALUES ('3', 'super_admin', 'Super Administrator', 'Super Administrator system group', '[\"*\"]', '1', '1', '2026-08-10 07:21:33', '2026-08-10 08:21:33');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_groups` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `user_groups`, `permissions`, `first_name`, `last_name`, `status`, `email_verified_at`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`) VALUES ('1', 'admin', 'admin@example.com', '$2y$12$6K5imLEURzXt8Fu0s6L8yelPnQd1bTanJuX2Jpz94MOnYFRMHskay', '[3]', NULL, 'System', 'Admin', '1', NULL, '2026-08-16 23:36:03', '::1', '2026-08-10 07:21:34', '2026-08-16 23:36:03');

SET FOREIGN_KEY_CHECKS=1;
