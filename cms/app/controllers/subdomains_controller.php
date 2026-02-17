<?php

namespace app\controllers;

use app\helpers\app;
use app\models\subdomains;
use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\base\controller;
//use irrevion\irry_cms\core\helpers\security;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

class subdomains_controller extends controller {

	private static $runtime = [];

	public static function action_list() {
		self::$layout = 'common_layout';
		view::$title = CMS::t('menu_item_subdomains_list');

		$params = [];

		//$page = intval(@$_GET['page']);
		//subdomains::$curr_pg = (empty($page)? 1: $page);

		$params['subdomains'] = subdomains::getList();
		//$params['count'] = subdomains::$items_amount;
		//$params['total'] = subdomains::$pages_amount;
		//$params['current'] = subdomains::$curr_pg;

		$params['canWrite'] = CMS::hasAccessTo('subdomains/list', 'write');
		$params['link_sc'] = utils::trueLink(['controller', 'action', 'q']);
		$params['link_return'] = urlencode(SITE.CMS_DIR.utils::trueLink(['controller', 'action', 'q' /*, 'page' */ ]));

		return self::render('subdomains_list', $params);
	}
}

?>