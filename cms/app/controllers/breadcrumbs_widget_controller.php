<?php

namespace app\controllers;

use app\helpers\app;
use irrevion\irry_cms\core\base\widget;
use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

class breadcrumbs_widget_controller extends widget {
	/* See core\helpers\view class for function widget($widget_name, $options=[]) information */

	public static $options = [];

	public static function init(): void {
		if (empty(self::$options['icon'])) {
			throw new \Error('Page icon is mandatory');
		}
		if (empty(self::$options['name'])) {
			throw new \Error('Page name is mandatory');
		}
	}

	public static function run() {
		return self::render('breadcrumbs', [
			'icon' => self::$options['icon'],
			'name' => self::$options['name'],
		]);
	}
}