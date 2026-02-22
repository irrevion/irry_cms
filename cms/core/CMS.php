<?php

namespace irrevion\irry_cms\core;

use irrevion\irry_cms\core\db_adapters\mysql_pdo;
use irrevion\irry_cms\core\helpers\security;
use irrevion\irry_cms\core\helpers\utils;

if (!defined('_VALID_PHP')) die('Direct access to this location is not allowed.');

class CMS {
	public static $version = '17.2.1';
	public static $config;
	public static $settings;
	public static $db;
	public static $db_subdomain;
	public static $lang;
	public static $site_langs;
	public static $default_site_lang;
	public static $sess_hash = '';
	public static $roles = [
		'admin' => [],
		'editor' => []
	];
	public static $upload_err = [
		1 => 'upl_ini_size_err',			// The uploaded file exceeds the upload_max_filesize directive in php.ini
		2 => 'upl_form_size_err',			// The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form
		3 => 'upl_partial_err',				// The uploaded file was only partially uploaded
		4 => 'upl_no_file_err',				// No file was uploaded
		// 5 => 'upl_empty_file_err',		// Empty file was uploaded
		6 => 'upl_no_tmp_dir_err',			// Missing a temporary folder
		7 => 'upl_can_write_err',			// Failed to write file to disk
		8 => 'upl_extension_err'			// A PHP extension stopped the file upload
	];

	public static function autoload(string $class): void {
		$is_app = preg_match('/^app\\\/', $class);
		$is_core = (substr($class, 0, 23)==='irrevion\\irry_cms\\core\\');

		$path = '';
		if ($is_app) {
			$path = APP_DIR.str_replace('\\', '/', substr($class, 4)).'.php';
		} else if ($is_core) {
			$path = CORE_DIR.str_replace('\\', '/', substr($class, 23)).'.php';
		}

		if ($path && is_file($path)) {
			include $path;
			return;
		}

		//throw new \Error("The file at path `$path` is not found. Namespace is `$class`.");
		return;
	}

	public static function init($params) {
		/*
			CMS initialization.
			Enables CSRF protection for POST forms globally.
			Generated session hash to isolate CMS session data from the rest of domain session data.
			Connects to database.
			Loads site settings.
			Checks logged user in DB (still exists, not blocked).
			Loads CMS language file specified by settings or user.
		*/

		self::$config = $params;
		security::$salt = $params['salt'];
		security::$CSRF_token = security::getCSRF_token();
		if ($_SERVER['REQUEST_METHOD']=='POST') {
			security::checkCSRF_token(@$_POST['CSRF_token']);
		}

		self::$sess_hash = security::generateSessionHash();

		self::$db = new mysql_pdo(self::$config['db']);

		self::loadSettings();

		self::checkAdminUserSession();

		self::$lang = include LANG_DIR.self::$settings['cms_default_lang'].'.php';
		if (!empty($_SESSION[self::$sess_hash]['ses_adm_id'])) {
			$user_lang_file = LANG_DIR.$_SESSION[self::$sess_hash]['ses_adm_lang'].'.php';
			if (is_file($user_lang_file)) {
				self::$lang = include $user_lang_file;
			}

			// check active subdomain
			self::checkActiveSubdomain();

			self::$site_langs = self::getLangsRegistered();
			self::$default_site_lang = self::getDefaultSiteLang();
		}
	}

	public static function t($key, $params=[]) {
		if (isset(self::$lang[$key])) {
			if (!empty($params)) {
				return strtr(self::$lang[$key], $params);
			}
			return self::$lang[$key];
		}
		return $key;
	}

	public static function resolve($controller='', $action='') {
		if (empty($controller)) {$controller = @(string)@$_GET['controller'];}
		$controller = utils::sanitizeStringByWhitelist($controller, 'a-z\_');
		if (empty($controller)) {$controller = 'base';}
		if (empty($action)) {$action = @(string)@$_GET['action'];}
		$action = utils::sanitizeStringByWhitelist($action, 'a-zA-Z0-9\_');
		if (empty($action)) {$action = '404';}

		if (empty($_SESSION[CMS::$sess_hash]['ses_adm_id'])) {
			$p_all = self::getPrivilegiesByRole('all');
			$has_general_access = isset($p_all[$controller.'/'.$action]);
			if (!$has_general_access) {
				$controller = 'base';
				$action = 'sign_in';
			}
		}

		$method = 'app\\controllers\\'.$controller.'_controller::action_'.$action;
		if (is_file(CONTROLLER_DIR.$controller.'_controller.php') && is_callable($method)) {
			if (!empty($_SESSION[CMS::$sess_hash]['ses_adm_id']) && !self::hasAccessTo($controller.'/'.$action)) {
				$method = 'app\\controllers\\base_controller::action_403';
			}
			$tpl = call_user_func($method);
		} else {
			$tpl = call_user_func('app\\controllers\\base_controller::action_404');
		}

		return $tpl;
	}

	public static function getAdminUser($login) {
		return self::$db->getRow("SELECT * FROM cms_users WHERE login=:login AND ".self::$db->in('role', array_keys(self::$roles))." AND is_blocked='0' LIMIT 1", [':login' => $login]);
	}

	public static function login($user) {
		if (!empty($user['id'])) {
			$_SESSION[self::$sess_hash]['ses_adm_id'] = $user['id'];
			$_SESSION[self::$sess_hash]['ses_adm_login'] = $user['login'];
			$_SESSION[self::$sess_hash]['ses_adm_name'] = $user['name'];
			$_SESSION[self::$sess_hash]['ses_adm_lang'] = $user['lang'];
			$_SESSION[self::$sess_hash]['ses_adm_type'] = $user['role'];
			$_SESSION[self::$sess_hash]['avatar'] = (empty($user['avatar'])? '': (UPLOADS_DIR.'/avatars/cms_users/'.$user['avatar']));
			$_SESSION[self::$sess_hash]['ses_adm_reg_date'] = $user['reg_date'];
			$_SESSION[self::$sess_hash]['ses_adm_last_login_date'] = $user['last_login_date'];
			$_SESSION[self::$sess_hash]['ses_adm_is_blocked'] = $user['is_blocked'];
			$_SESSION[self::$sess_hash]['ses_adm_is_menu_collapsed'] = $user['is_menu_collapsed'];
			$_SESSION[self::$sess_hash]['LAST_REQUEST_TIME'] = time();
			$_SESSION[self::$sess_hash]['ses_adm_privilegies'] = self::getPrivilegies($user['id']);

			$now = date('Y-m-d H:i:s');
			self::$db->mod('cms_users#'.$user['id'], [
				'last_login_date' => $now,
				'last_login_attempt' => $now,
				'login_attempts' => 0
			]);

			return true;
		}

		return false;
	}

	public static function logout() {
		unset($_SESSION[self::$sess_hash]);
		// session_destroy();
		utils::redirect(SITE.CMS_DIR);
	}

	public static function sess(string $param, mixed $val='_N/A_') {
		if ($val!=='_N/A_') {$_SESSION[self::$sess_hash]['ses_adm_'.$param] = $val;}
		if (isset($_SESSION[CMS::$sess_hash]['ses_adm_'.$param])) {
			return $_SESSION[CMS::$sess_hash]['ses_adm_'.$param];
		}
		return null;
	}

	public static function getPrivilegiesByRole($role) {
		return CMS::$db->getPairs("SELECT CONCAT_WS('/', controller, action), is_readonly FROM `cms_users_roles_actions` WHERE role=:role", [':role' => $role]);
	}

	public static function getPrivilegiesByUser($admin_id) {
		return CMS::$db->getPairs("SELECT CONCAT_WS('/', controller, action), is_readonly FROM `cms_users_actions` WHERE cms_user_id=:admin_id", [':admin_id' => $admin_id]);
	}

	public static function getPrivilegies($admin_id=0) {
		if (empty($admin_id)) {$admin_id = $_SESSION[self::$sess_hash]['ses_adm_id'];}
		$all_p = self::getPrivilegiesByRole('all');
		$user = CMS::$db->getRow("SELECT * FROM `cms_users` WHERE id=:admin_id AND is_blocked='0' LIMIT 1", [':admin_id' => $admin_id]);
		if (empty($user['id'])) {return $all_p;}
		$role_p = self::getPrivilegiesByRole($user['role']);
		$p = array_merge($all_p, $role_p);
		$user_p = self::getPrivilegiesByUser($admin_id);
		if (!empty($user_p)) {
			$p = array_merge($all_p, $user_p);
		}
		return $p;
	}

	public static function hasAccessTo($page, $mode='read', $admin_id=0) {
		$hasAccess = false;

		if (empty($admin_id)) {$admin_id = $_SESSION[self::$sess_hash]['ses_adm_id'];}
		$privilegies = self::getPrivilegies($admin_id);

		$hasAccess = isset($privilegies[$page]);

		if ($hasAccess && $mode=='write') {
			$hasAccess = !$privilegies[$page];
		}

		return $hasAccess;
	}

	public static function getLandingPage() {
		$landing_page = self::$settings['cms_default_landing_page'];
		$user_role = self::$db->getRow("SELECT * FROM `cms_users_roles` WHERE role=:role LIMIT 1", [':role' => $_SESSION[self::$sess_hash]['ses_adm_type']]);
		if (!empty($user_role['id'])) {
			$landing_page = $user_role['landing_page'];
		}
		return $landing_page;
	}

	public static function loadSettings() {
		$settings = self::$db->getPairs("SELECT `option`, `value` FROM `cms_settings`");
		define('DEFAULT_LANG_DIR', $settings['site_default_lang_dir']);
		self::$settings = $settings;
	}

	public static function checkAdminUserSession() {
		if (!empty($_SESSION[self::$sess_hash]['ses_adm_id'])) {
			$uid = self::$db->get("SELECT id FROM cms_users WHERE id='".intval($_SESSION[self::$sess_hash]['ses_adm_id'])."' AND role IN ('".implode("', '", array_keys(self::$roles))."') AND is_blocked='0' LIMIT 1");
			if (!empty($uid)) {
				$time = time();
				if (!empty($_SESSION[self::$sess_hash]['LAST_REQUEST_TIME'])) if (($time-$_SESSION[self::$sess_hash]['LAST_REQUEST_TIME']) < 2*3600) {
					define('ADMIN_ID', $_SESSION[self::$sess_hash]['ses_adm_id']);
					define('ADMIN_TYPE', $_SESSION[self::$sess_hash]['ses_adm_type']);
					define('ADMIN_INFO', $_SESSION[self::$sess_hash]['ses_adm_name']);
					$_SESSION[self::$sess_hash]['LAST_REQUEST_TIME'] = $time;

					return true;
				}
			}
		}
		unset($_SESSION[self::$sess_hash]['ses_adm_id']);
		//session_destroy();
		return false;
	}

	public static function checkActiveSubdomain() {
		$id = self::sess('active_subdomain');
		if (!empty($id)) {
			$subdomain = self::$db->getRow("SELECT * FROM subdomains WHERE id=:subdomain_id AND is_deleted='0' LIMIT 1", [':subdomain_id' => $id]);
			if (empty($subdomain['id'])) {
				// subdomain is not exists, unset session value
				self::sess('active_subdomain', null);
				self::sess('active_subdomain_info', null);
			} else {
				// update subdomain info
				self::sess('active_subdomain_info', $subdomain);
				// create DB connection
				self::$db_subdomain = new mysql_pdo(require(CONFIG_DIR.'db_'.$subdomain['db'].'.php'));
				return true;
			}
		}
		return false;
	}

	public static function db() {
		// return connection to active database
		$active_subdomain = self::sess('active_subdomain');
		if (!empty($active_subdomain)) {
			if (is_null(self::$db_subdomain)) {
				throw new \Error('Subdomain DB connection not established');
			}
			return self::$db_subdomain;
		}
		return self::$db;
	}

	public static function getCMSLangsRegistered() {
		return self::$db->getAll("SELECT * FROM `cms_languages` ORDER BY `id` ASC");
	}

	public static function getLangsRegistered() {
		return self::db()->getAll("SELECT * FROM `site_languages` WHERE is_deleted='0' ORDER BY `id` ASC");
	}

	public static function getLangsRegisteredList() {
		return CMS::db()->getList("SELECT `language_dir` FROM `site_languages` WHERE is_deleted='0' ORDER BY `language_dir` ASC");
	}

	public static function getDefaultSiteLang() {
		return self::db()->get("SELECT language_dir FROM `site_languages` WHERE is_default='1' AND is_deleted='0' LIMIT 1");
	}

	public static function log($data) {
		return self::$db->add('cms_log', [
			'cms_user_id' => self::sess('id'),
			'subdomain' => self::sess('active_subdomain'),
			'subj_table' => $data['subj_table'],
			'subj_id' => $data['subj_id'],
			'action' => $data['action'],
			'descr' => $data['descr'],
			'reg_date' => date('Y-m-d H:i:s')
		]);
	}
}

?>