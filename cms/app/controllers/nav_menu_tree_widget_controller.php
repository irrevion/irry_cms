<?php

namespace app\controllers;

use app\helpers\app;
use irrevion\irry_cms\core\base\widget;
use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

class nav_menu_tree_widget_controller extends widget {
	/* See core\helpers\view class for function widget($widget_name, $options=[]) information */

	private static $icons = [
		'category' => 'fa fa-folder-open',
		'article' => 'fa fa-file-text',
		'spec' => 'fa fa-cogs',
		'url' => 'fa fa-link'
	];

	private static function showNavTree($nav) {
		static $time;
		if (empty($time)) {$time = time();}

		$html = '';
		if (!empty($nav) && is_array($nav)) {
			$html.='<ul>';
			foreach ($nav as $n) {
				$html.='<li data-jstree="'.utils::safeEcho(json_encode([
						'opened' => true,
						'selected' => (@$_GET['item']==$n['id']),
						//'disabled' => !$n['is_published'],
						'icon' => self::$icons[$n['type']],
					]), 1).'"'.($n['is_published']? '': ' class="nav-node-unpublished"').'><a href="?controller=nav&amp;action=edit&amp;item='.$n['id'].'&amp;'.$time.'" data-item_id="'.$n['id'].'">'.$n['name'].'</a>'.(empty($n['children'])? '': self::showNavTree($n['children'])).'</li>';
			}
			$html.='</ul>';
		}

		return $html;
	}

	public static function run() {
		return self::render('nav_menu_tree', [
			'tree' => self::showNavTree(self::$options['structure'])
		]);
	}
}

?>