<?php

namespace irrevion\irry_cms\core\helpers;

if (!defined('_VALID_PHP')) die('Direct access to this location is not allowed.');

class console {

	public static $mode;
	public static $cli;


	public static function init($argv) {
		self::$mode = php_sapi_name();
		if (self::$mode!=='cli') throw new \Error('Only CLI mode allowed');

		self::cli($argv);
	}

    public static function print($s, $return=0) {
		if (!is_string($s)) {
			$s = var_export($s, 1);
		}
		if ($return) {
			return "$s";
		}
        print "{$s}";
    }

    public static function println($s) {
        self::print("{$s}\n");
    }

    public static function log() {
		$s = "";
		$args = func_get_args();
		if ($args && count($args)) {
			foreach ($args as $i=>$a) {
				$s.=($i? ' ': '').self::print($a, 1);
			}
		}
        self::println($s);
    }

    public static function error(...$args) {
		self::print("\x1b[31m⚠ ");
		self::log(...$args);
		self::reset();
		self::bell();
	}

    public static function bell() {
        self::print("\007");
    }

    public static function reset() {
        self::print("\x1b[0m");
    }

    public static function cli($argv) {
        $cli_params = array_slice($argv, 1);
        $params = [];
        $command = null;
        foreach ($cli_params as $p) {
            $p = explode('=', $p);
            if (count($p)>1) {
                $params[substr($p[0], 2)] = $p[1];
            } else {
                $command = substr($p[0], 2);
            }
        }
        self::$cli['cmd'] = $command;
        self::$cli['params'] = $params;
    }

	public static function read() {
		$handle = fopen("php://stdin", "r");
		do {$line = fgets($handle);} while ($line=='');
		fclose($handle);
		return $line;
	}

	public static function execInBackground($cmd) {
		if (substr(php_uname(), 0, 7)=="Windows") {
			pclose(popen("start /B ".$cmd, "r"));
		} else {
			exec($cmd." > /dev/null &");
		}
	}

	public static function exec($cmd) {
		$val = "";
		$arr = [];
		$str = exec($cmd, $arr, $val);
		//self::log($cmd, ' - ', $str, ' - ', $arr, ' - ', $val);
	}
}

?>