<?php

namespace irrevion\irry_cms\core\base;

use irrevion\irry_cms\core\base\controller;
use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

class widget extends controller {
	public static $options;

	public static function init(): void {}

	public static function run() {
		return '';
	}

	public static function render($widget_view, $data=[]) {
		$tpl = view::render(WIDGET_DIR.$widget_view.'.php', $data);
		return $tpl;
	}
}

?>