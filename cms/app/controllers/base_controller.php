<?php

namespace app\controllers;

use app\helpers\app;
use app\models\cms_users;
use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\base\controller;
use irrevion\irry_cms\core\helpers\security;
use irrevion\irry_cms\core\helpers\tr;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

class base_controller extends controller {
	private static $runtime = [];

	public static function action_404() {
		header('HTTP/1.1 404 Not Found');

		self::$layout = 'simple_layout';
		view::$title = CMS::t('404_title');

		return self::render('404');
	}

	public static function action_403() {
		header('HTTP/1.1 403 Forbidden');

		self::$layout = 'simple_layout';
		view::$title = CMS::t('403_title');

		return self::render('403');
	}

	public static function action_sign_in() {
		self::$layout = 'simple_layout';
		view::$title = CMS::t('login_title');

		$response = [
			'success' => false,
			'message' => 'undefined'
		];

		$now_ts = time();
		if (isset($_POST['ad_send'])) {
			$user = cms_users::getUserByLogin(@$_POST['ad_login']);
			if (empty($user['id'])) {
				$response['errors'][] = 'login_err';
			} else if (((int)$user['login_attempts']>=(int)CMS::$site_settings['cms_max_login_attempts']) && !is_null($user['last_login_attempt']) && ((strtotime($user['last_login_attempt'])+CMS::$site_settings['cms_login_cooldown'])>$now_ts)) {
				$response['errors'][] = 'login_cooldown_err';
				$response['errors'][] = '⏱ '.utils::formatDuration(((strtotime($user['last_login_attempt'])+CMS::$site_settings['cms_login_cooldown'])-$now_ts), [
					'd' => CMS::t('d'),
					'h' => CMS::t('h'),
					'm' => CMS::t('m'),
					's' => CMS::t('s'),
				]);
			} else if (!security::validatePassword($user['password'], @$_POST['ad_password'], security::$salt)) {
				$response['errors'][] = 'login_err';
				CMS::$db->mod(cms_users::$users_tbl.'#'.$user['id'], [
					'last_login_attempt' => date('Y-m-d H:i:s'),
					'login_attempts' => ($user['login_attempts']+1),
				]);
			} else if ($user['is_blocked']) {
				$response['errors'][] = 'login_err_blocked';
			} else {
				CMS::login($user);
				$response = [
					'success' => true,
					'message' => 'login_suc'
				];
				utils::redirect(SITE.CMS_DIR.(empty($_SERVER['QUERY_STRING'])? CMS::getLandingPage(): ('?'.$_SERVER['QUERY_STRING'])));
			}
		}
		return self::render('sign_in', [
			'response' => $response
		]);
	}

	/*
	public static function action_ulogin() {
		header('Content-type: application/json; charset=utf-8');

		$response = [
			'success' => false,
			'message' => CMS::t('login_err')
		];

		$s = file_get_contents('http://ulogin.ru/token.php?token='.$_POST['token'].'&host='.$_SERVER['HTTP_HOST']);
		$user_data = json_decode($s, true);

		if (!isset($user_data['error']) && !empty($user_data['uid'])) {
			$user = CMS::getAdminUser($user_data['email']);

			if (!empty($user['id'])) {
				CMS::login($user);
				$response = [
					'success' => true,
					'message' => CMS::t('login_suc')
				];
			} else {
				$response['message'] = CMS::t('login_social_err', [
					'{usermail}' => $user_data['email'],
					'{username}' => $user_data['first_name'].' '.$user_data['last_name']
				]);
			}
		} else {
			$response['message'] = @(string)$user_data['error'];
		}

		return json_encode($response);
	}
	*/

	public static function action_sign_out() {
		CMS::logout();
		return '';
	}

	public static function action_password_recovery() {
		self::$layout = 'simple_layout';
		view::$title = CMS::t('password_recovery_title');

		$response = [
			'success' => false,
			'message' => 'undefined'
		];
		if (!empty($_POST['recover'])) {
			if (!utils::isValidEmail($_POST['email'])) {
				$response['errors'][] = 'password_recovery_err_email_invalid';
			} else {
				$user = CMS::getAdminUser($_POST['email']);
				if (empty($user['id'])) {
					$response['errors'][] = 'password_recovery_err_user_not_found';
				} else {
					$to = $user['login'];
					$msg = [
						'subject' => 'Irry CMS - '.CMS::t('password_recovery_subj'),
						'message' => CMS::t('password_recovery_msg', [
							'{username}' => $user['name'],
							'{recovery_link}' => utils::safeEcho(SITE.CMS_DIR.'?controller=base&action=change_password&login='.$user['login'].'&hash='.security::generateAccountHash($user, 'password_recovery'), 1)
						])
					];
					$sended = app::sendEmail($to, $msg);
					if ($sended) {
						$response = [
							'success' => true,
							'message' => 'password_recovery_suc_email_sended'
						];
					} else {
						$response['errors'][] = 'password_recovery_err_email_cannot_send';
					}
				}
			}
		}
		return self::render('password_recovery', [
			'response' => $response
		]);
	}

	public static function action_change_password() {
		self::$layout = 'simple_layout';
		view::$title = CMS::t('password_change_title');

		$response = [
			'success' => false,
			'message' => 'undefined'
		];
		if (!empty($_POST['change'])) {
			if (!utils::checkPass(@$_POST['password'])) {
				$response['errors'][] = 'cms_user_pwd_err';
			} else {
				$user = CMS::getAdminUser($_GET['login']);
				if (empty($user['id'])) {
					$response['errors'][] = 'password_recovery_err_user_not_found';
				} else if (!security::checkAccountHash(($_GET['hash'] ?? ''), $user, 'password_recovery')) {
					$response['errors'][] = 'password_change_err_account_hash_expired';
				} else {
					$new_hash = security::generatePasswordHash($_POST['password'], security::$salt);
					CMS::$db->mod('cms_users#'.$user['id'], [
						'password' => $new_hash
					]);
					$response = [
						'success' => true,
						'message' => 'password_change_suc'
					];
				}
			}
		}
		return self::render('change_password', [
			'response' => $response
		]);
	}

	public static function action_save_menubar_status() {
		header('Content-type: application/json; charset=utf-8');

		$response = [
			'success' => false,
			'message' => CMS::t('ajax_invalid_request')
		];

		if (utils::isAjax()) {
			$collapse = (empty($_POST['is_menu_collapsed'])? '0': '1');

			$updated = CMS::$db->mod('cms_users#'.ADMIN_ID, [
				'is_menu_collapsed' => $collapse
			]);

			//'message' => CMS::t('base_save_menubar_status_unknown_err')
			$response = [
				'success' => true,
				'message' => ($updated? CMS::t('update_suc'): CMS::t('update_no_data_affected'))
			];

			if ($updated) {
				$_SESSION[CMS::$sess_hash]['ses_adm_is_menu_collapsed'] = $collapse;
			}
		}

		return json_encode($response);
	}
}

?>