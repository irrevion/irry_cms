<?php

namespace app\models;

use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\helpers\utils;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

class subdomains {

	public static $table = 'subdomains';

	public static function getList() {
		$sql = "SELECT * FROM ".self::$table." ORDER BY url";
		return CMS::$db->getAll($sql);
	}

	public static function activateSubdomain($subdomain_id) {
		$subdomain = self::getSubdomainById($subdomain_id);
		if (empty($subdomain['id'])) {
			return false;
		}
		CMS::sess('active_subdomain', $subdomain_id);
		CMS::sess('active_subdomain_info', $subdomain);
		return true;
	}

	public static function getSubdomainById($subdomain_id) {
		return CMS::$db->getRow("SELECT * FROM ".self::$table." WHERE id=:subdomain_id LIMIT 1", [':subdomain_id' => $subdomain_id]);
	}
}

?>