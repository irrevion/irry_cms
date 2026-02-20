-- SUBDOMAINS 2026-02-20

CREATE TABLE `cms_setings` LIKE `site_setings`;
INSERT INTO `cms_setings` SELECT * FROM `site_setings`;
TRUNCATE `site_setings`;

ALTER TABLE `irry_cms`.`cms_log` ADD COLUMN `subdomain` varchar(255) NULL AFTER `cms_user_id`;
CREATE TABLE `subdomains` ... ;

-- LOW RATE LIMIT ATTACK FIX 2024-12-24

ALTER TABLE `irry_cms`.`cms_users` ADD COLUMN `last_login_attempt` datetime NULL DEFAULT NULL AFTER `login_attempts`;

ALTER TABLE `irry_cms`.`articles` 
	MODIFY COLUMN `add_by` int UNSIGNED NOT NULL AFTER `ordering`,
	ADD CONSTRAINT `gallery_id` FOREIGN KEY (`gallery_id`) REFERENCES `irry_cms`.`galleries` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION;

ALTER TABLE `irry_cms`.`cms_users` ADD COLUMN `login_attempts` int UNSIGNED NULL DEFAULT 0 AFTER `is_blocked`;