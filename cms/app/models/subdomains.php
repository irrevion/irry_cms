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
}

?>