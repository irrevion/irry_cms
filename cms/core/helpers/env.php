<?php

namespace irrevion\irry_cms\core\helpers;

if (!defined('_VALID_PHP')) die('Direct access to this location is not allowed.');

class env {

	public static $loaded = false;
	public static $use_cache = false;
	public static $cache = [];

	public static function get(string $key, ?string $default=null): mixed {
		if (!self::$loaded) self::load();
		$val = (self::$use_cache? $cache[$key]: getenv($key));
		if ($val===false) $val = null;
		return ($val ?? $default);
	}

	protected static function load(): void {
		//self::$cache = parse_ini_file('.env', false, INI_SCANNER_TYPED);
		self::$cache = parse_ini_file('../.env');
		if (self::$cache) foreach (self::$cache as $k=>$v) {putenv("$k=$v");}
		self::$loaded = true;
	}
}

?>