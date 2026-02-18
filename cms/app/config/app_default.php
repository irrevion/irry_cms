<?php

defined('_VALID_PHP') or die('No direct script access.');

ini_set('session.cookie_httponly', true);
//ini_set('session.cookie_secure', true);
@session_start();
date_default_timezone_set('Asia/Baku');

require_once CONFIG_DIR.'dirs.php';

$app_config = [
	'salt' => '************', // must be project-unique
	'db' => require_once(CONFIG_DIR.'db.php'),
	'smtp' => [
		'protocol' => 'ssl://',
		'host' => 'smtp.gmail.com',
		'port' => '465',
		'user' => 'username@example.com',
		'password' => '***************'
	]
];

define('CMS_ENV', 'dev');
if (@CMS_ENV!='dev') {
	error_reporting(0);
} else {
	error_reporting(E_ALL);
	ini_set('display_errors', true);
	ini_set('html_errors', true);
}

?>