<?php

namespace irrevion\irry_cms\core\helpers;

if (!defined('_VALID_PHP')) die('Direct access to this location is not allowed.');

class http {
	public static $default_headers = [
		'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
		'Accept-Language: ru,en-US;q=0.8,en;q=0.5,ru-RU;q=0.3',
		// 'Accept-Encoding: gzip, deflate, br, zstd',
		'Connection: keep-alive',
		'Pragma: no-cache',
		'Cache-Control: no-cache',
	];
	public static $ajax_headers = [
		'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
		'Accept: application/json, text/javascript, */*; q=0.01',
		'X-Requested-With: XMLHttpRequest',
		'Priority: u=0',
		'Connection: keep-alive',
		'Pragma: no-cache',
		'Cache-Control: no-cache',
	];
	public static $cookie = [];
	public static $history = []; // list of accessed urls
	public static $log = [];
	public static $cache = [];

	public static function get($url, $opts=null, $data=null) {
		if (empty($url)) {
			throw new \Error('URL is undefined');
		}
		if (!empty($data) && is_array($data) && count($data)) {
			// $url = $url.'?'.http_build_query($data);
			$url = $url.((strpos($url, '?')===false)? '?': '&').http_build_query($data);
		}
		self::$log[] = "GET {$url}";
		$history_length = count(self::$history);
		if ($history_length) {
			$referer = self::$history[$history_length-1];
		}
		self::$history[] = $url;
		$response = [
			'status' => null,
			'error' => null,
			'headers' => null,
			'body' => null,
			'info' => null,
		];

		if (empty($opts['headers'])) {
			$request_headers = (empty($opts['is_ajax'])? self::$default_headers: self::$ajax_headers);
		} else {
			$request_headers = $opts['headers'];
		}

		if (empty($opts['referer'])) {
			if (!empty($referer)) {
				$request_headers[] = 'Referer: '.$referer;
			}
		} else if ($opts['referer']==='none') {
			// no Referer header
		} else {
			$request_headers[] = 'Referer: '.$opts['referer'];
		}

		$profiling_start = microtime(true);
		$c = curl_init();

		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_HTTPGET, true);
		curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_HEADER, true);
		curl_setopt($c, CURLOPT_ENCODING, ''); // auto gzip
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
		if (substr($url, 0, 8)==='https://') {
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		}

		if (empty($opts['cookie'])) {
			if (!empty(self::$cookie)) {
				curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie(self::$cookie));
			}
		} else if ($opts['cookie']==='none') {
			// disable cookies
		} else {
			curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie($opts['cookie']));
		}

		$raw_response = curl_exec($c);
		// list($response_headers, $response_body) = explode("\r\n\r\n", $raw_response, 2);
		list($response_headers, $response_body) = self::separateHeaders($raw_response);
		$request_info = curl_getinfo($c);
		$headers_sent = curl_getinfo($c, CURLINFO_HEADER_OUT);
		$request_err = curl_error($c);

		curl_close($c);
		$profiling_end = microtime(true);
		$profiling_duration = $profiling_end-$profiling_start;

		$response['status'] = (int)$request_info['http_code'];
		$response['error'] = $request_err;
		$response['headers'] = $response_headers;
		$response['body'] = $response_body;
		$response['info'] = $request_info;
		$response['info']['profiling'] = [
			'measured_time' => $profiling_duration,
			'measured_time_us' => (int)ceil($profiling_duration*1e6),
			'total_time' => $response['info']['total_time'],
			'total_time_us' => $response['info']['total_time_us'],
			'namelookup_time' => $response['info']['namelookup_time'],
			'namelookup_time_us' => $response['info']['namelookup_time_us'],
			'connect_time' => $response['info']['connect_time'],
			'connect_time_us' => $response['info']['connect_time_us'],
			'appconnect_time_us' => $response['info']['appconnect_time_us'],
			'pretransfer_time' => $response['info']['pretransfer_time'],
			'pretransfer_time_us' => $response['info']['pretransfer_time_us'],
			'redirect_time' => $response['info']['redirect_time'],
			'redirect_time_us' => $response['info']['redirect_time_us'],
			'starttransfer_time' => $response['info']['starttransfer_time'],
			'starttransfer_time_us' => $response['info']['starttransfer_time_us'],
		];

		if (empty($opts['keep_plain_headers'])) {
			$response['headers'] = self::parseHeaders($response['headers']);

			if (empty($response['headers']['content-length']) && !empty($response['body'])) {
				$response['headers']['content-length'] = strlen($response['body']);
			}

			if (empty($opts['keep_plain_body'])) {
				if (!empty($response['headers']['content-type'])) {
					$content_type = $response['headers']['content-type'];
					$content_type = explode(';', $content_type);
					$content_type = $content_type[0];
					if ($content_type==='application/json') {
						if (empty($response['headers']['content-length'])) {
							$response['body'] = [];
						} else {
							$response['body'] = json_decode($response['body'], true);
						}
					}
				}
			}
		}

		return $response;
	}

	public static function post($url, $opts=null, $data=null) {
		if (empty($url)) {
			throw new \Error('URL is undefined');
		}
		self::$log[] = "POST {$url}";
		$response = [
			'status' => null,
			'error' => null,
			'headers' => null,
			'body' => null,
			'info' => null,
		];

		if (empty($opts['headers'])) {
			$request_headers = (empty($opts['is_ajax'])? self::$default_headers: self::$ajax_headers);
		} else {
			$request_headers = $opts['headers'];
		}

		if (empty($opts['referer'])) {
			if (!empty($referer)) {
				$request_headers[] = 'Referer: '.$referer;
			}
		} else if ($opts['referer']==='none') {
			// no Referer header
		} else {
			$request_headers[] = 'Referer: '.$opts['referer'];
		}

		$request_body = '';
		if (empty($opts['enctype']) || ($opts['enctype']==='urlencoded')) {
			// default enctype is application/x-www-form-urlencoded
			$request_body = http_build_query($data);
		} else if ($opts['enctype']==='multipart') {
			$request_body = $data;
		} else if ($opts['enctype']==='json') {
			$request_headers[] = 'Content-Type: application/json; charset=UTF-8';
			$request_body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else {
			throw new \Error('Unexpected enctype „'.$opts['enctype'].'‟ for POST request.');
		}

		$profiling_start = microtime(true);
		$c = curl_init();

		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($c, CURLOPT_POSTFIELDS, $request_body);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_HEADER, true);
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
		// curl_setopt($c, CURLOPT_VERBOSE, true);
		if (substr($url, 0, 8)==='https://') {
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		}

		if (empty($opts['cookie'])) {
			if (!empty(self::$cookie)) {
				curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie(self::$cookie));
			}
		} else if ($opts['cookie']==='none') {
			// disable cookies
		} else {
			curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie($opts['cookie']));
		}

		$raw_response = curl_exec($c);
		// list($response_headers, $response_body) = explode("\r\n\r\n", $raw_response, 2);
		list($response_headers, $response_body) = self::separateHeaders($raw_response);
		$request_info = curl_getinfo($c);
		$headers_sent = curl_getinfo($c, CURLINFO_HEADER_OUT);
		$request_err = curl_error($c);

		curl_close($c);
		$profiling_end = microtime(true);
		$profiling_duration = $profiling_end-$profiling_start;

		$response['status'] = (int)$request_info['http_code'];
		$response['error'] = $request_err;
		$response['headers'] = $response_headers;
		$response['body'] = $response_body;
		$response['info'] = $request_info;
		$response['info']['profiling'] = [
			'measured_time' => $profiling_duration,
			'measured_time_us' => (int)ceil($profiling_duration*1e6),
			'total_time' => $response['info']['total_time'],
			'total_time_us' => $response['info']['total_time_us'],
			'namelookup_time' => $response['info']['namelookup_time'],
			'namelookup_time_us' => $response['info']['namelookup_time_us'],
			'connect_time' => $response['info']['connect_time'],
			'connect_time_us' => $response['info']['connect_time_us'],
			'appconnect_time_us' => $response['info']['appconnect_time_us'],
			'pretransfer_time' => $response['info']['pretransfer_time'],
			'pretransfer_time_us' => $response['info']['pretransfer_time_us'],
			'redirect_time' => $response['info']['redirect_time'],
			'redirect_time_us' => $response['info']['redirect_time_us'],
			'starttransfer_time' => $response['info']['starttransfer_time'],
			'starttransfer_time_us' => $response['info']['starttransfer_time_us'],
		];

		if (empty($opts['keep_plain_headers'])) {
			$response['headers'] = self::parseHeaders($response['headers']);

			if (empty($response['headers']['content-length']) && !empty($response['body'])) {
				$response['headers']['content-length'] = strlen($response['body']);
			}

			if (empty($opts['keep_plain_body'])) {
				if (!empty($response['headers']['content-type'])) {
					$content_type = $response['headers']['content-type'];
					$content_type = explode(';', $content_type);
					$content_type = $content_type[0];
					if ($content_type==='application/json') {
						if (empty($response['headers']['content-length'])) {
							$response['body'] = [];
						} else {
							$response['body'] = json_decode($response['body'], true);
						}
					}
				}
			}
		}

		return $response;
	}

	public static function put($url, $opts=null, $data=null) {
		if (empty($url)) {
			throw new \Error('URL is undefined');
		}
		self::$log[] = "PUT {$url}";
		$response = [
			'status' => null,
			'error' => null,
			'headers' => null,
			'body' => null,
			'info' => null,
		];

		if (empty($opts['headers'])) {
			$request_headers = (empty($opts['is_ajax'])? self::$default_headers: self::$ajax_headers);
		} else {
			$request_headers = $opts['headers'];
		}

		if (empty($opts['referer'])) {
			if (!empty($referer)) {
				$request_headers[] = 'Referer: '.$referer;
			}
		} else if ($opts['referer']==='none') {
			// no Referer header
		} else {
			$request_headers[] = 'Referer: '.$opts['referer'];
		}

		$request_body = '';
		if (empty($opts['enctype']) || ($opts['enctype']==='urlencoded')) {
			// default enctype is application/x-www-form-urlencoded
			$request_body = http_build_query($data);
		} else if ($opts['enctype']==='multipart') {
			$request_body = $data;
		} else if ($opts['enctype']==='json') {
			$request_headers[] = 'Content-Type: application/json; charset=UTF-8';
			$request_body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else {
			throw new \Error('Unexpected enctype „'.$opts['enctype'].'‟ for POST request.');
		}

		$profiling_start = microtime(true);
		$c = curl_init();

		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($c, CURLOPT_POSTFIELDS, $request_body);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_HEADER, true);
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
		// curl_setopt($c, CURLOPT_VERBOSE, true);
		if (substr($url, 0, 8)==='https://') {
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		}

		if (empty($opts['cookie'])) {
			if (!empty(self::$cookie)) {
				curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie(self::$cookie));
			}
		} else if ($opts['cookie']==='none') {
			// disable cookies
		} else {
			curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie($opts['cookie']));
		}

		$raw_response = curl_exec($c);
		// list($response_headers, $response_body) = explode("\r\n\r\n", $raw_response, 2);
		list($response_headers, $response_body) = self::separateHeaders($raw_response);
		$request_info = curl_getinfo($c);
		$headers_sent = curl_getinfo($c, CURLINFO_HEADER_OUT);
		$request_err = curl_error($c);

		curl_close($c);
		$profiling_end = microtime(true);
		$profiling_duration = $profiling_end-$profiling_start;

		$response['status'] = (int)$request_info['http_code'];
		$response['error'] = $request_err;
		$response['headers'] = $response_headers;
		$response['body'] = $response_body;
		$response['info'] = $request_info;
		$response['info']['profiling'] = [
			'measured_time' => $profiling_duration,
			'measured_time_us' => (int)ceil($profiling_duration*1e6),
			'total_time' => $response['info']['total_time'],
			'total_time_us' => $response['info']['total_time_us'],
			'namelookup_time' => $response['info']['namelookup_time'],
			'namelookup_time_us' => $response['info']['namelookup_time_us'],
			'connect_time' => $response['info']['connect_time'],
			'connect_time_us' => $response['info']['connect_time_us'],
			'appconnect_time_us' => $response['info']['appconnect_time_us'],
			'pretransfer_time' => $response['info']['pretransfer_time'],
			'pretransfer_time_us' => $response['info']['pretransfer_time_us'],
			'redirect_time' => $response['info']['redirect_time'],
			'redirect_time_us' => $response['info']['redirect_time_us'],
			'starttransfer_time' => $response['info']['starttransfer_time'],
			'starttransfer_time_us' => $response['info']['starttransfer_time_us'],
		];

		if (empty($opts['keep_plain_headers'])) {
			$response['headers'] = self::parseHeaders($response['headers']);

			if (empty($response['headers']['content-length']) && !empty($response['body'])) {
				$response['headers']['content-length'] = strlen($response['body']);
			}

			if (empty($opts['keep_plain_body'])) {
				if (!empty($response['headers']['content-type'])) {
					$content_type = $response['headers']['content-type'];
					$content_type = explode(';', $content_type);
					$content_type = $content_type[0];
					if ($content_type==='application/json') {
						if (empty($response['headers']['content-length'])) {
							$response['body'] = [];
						} else {
							$response['body'] = json_decode($response['body'], true);
						}
					}
				}
			}
		}

		return $response;
	}

	public static function delete($url, $opts=null, $data=null) {
		if (empty($url)) {
			throw new \Error('URL is undefined');
		}
		self::$log[] = "DELETE {$url}";
		$response = [
			'status' => null,
			'error' => null,
			'headers' => null,
			'body' => null,
			'info' => null,
		];

		if (empty($opts['headers'])) {
			$request_headers = (empty($opts['is_ajax'])? self::$default_headers: self::$ajax_headers);
		} else {
			$request_headers = $opts['headers'];
		}

		if (empty($opts['referer'])) {
			if (!empty($referer)) {
				$request_headers[] = 'Referer: '.$referer;
			}
		} else if ($opts['referer']==='none') {
			// no Referer header
		} else {
			$request_headers[] = 'Referer: '.$opts['referer'];
		}

		$request_body = '';
		if (empty($opts['enctype']) || ($opts['enctype']==='urlencoded')) {
			// default enctype is application/x-www-form-urlencoded
			$request_body = http_build_query($data);
		} else if ($opts['enctype']==='multipart') {
			$request_body = $data;
		} else if ($opts['enctype']==='json') {
			$request_headers[] = 'Content-Type: application/json; charset=UTF-8';
			$request_body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else {
			throw new \Error('Unexpected enctype „'.$opts['enctype'].'‟ for POST request.');
		}

		$profiling_start = microtime(true);
		$c = curl_init();

		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($c, CURLOPT_POSTFIELDS, $request_body);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_HEADER, true);
		curl_setopt($c, CURLINFO_HEADER_OUT, true);
		// curl_setopt($c, CURLOPT_VERBOSE, true);
		if (substr($url, 0, 8)==='https://') {
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
		}

		if (empty($opts['cookie'])) {
			if (!empty(self::$cookie)) {
				curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie(self::$cookie));
			}
		} else if ($opts['cookie']==='none') {
			// disable cookies
		} else {
			curl_setopt($c, CURLOPT_COOKIE, self::bakeCookie($opts['cookie']));
		}

		$raw_response = curl_exec($c);
		// list($response_headers, $response_body) = explode("\r\n\r\n", $raw_response, 2);
		list($response_headers, $response_body) = self::separateHeaders($raw_response);
		$request_info = curl_getinfo($c);
		$headers_sent = curl_getinfo($c, CURLINFO_HEADER_OUT);
		$request_err = curl_error($c);

		curl_close($c);
		$profiling_end = microtime(true);
		$profiling_duration = $profiling_end-$profiling_start;

		$response['status'] = (int)$request_info['http_code'];
		$response['error'] = $request_err;
		$response['headers'] = $response_headers;
		$response['body'] = $response_body;
		$response['info'] = $request_info;
		$response['info']['profiling'] = [
			'measured_time' => $profiling_duration,
			'measured_time_us' => (int)ceil($profiling_duration*1e6),
			'total_time' => $response['info']['total_time'],
			'total_time_us' => $response['info']['total_time_us'],
			'namelookup_time' => $response['info']['namelookup_time'],
			'namelookup_time_us' => $response['info']['namelookup_time_us'],
			'connect_time' => $response['info']['connect_time'],
			'connect_time_us' => $response['info']['connect_time_us'],
			'appconnect_time_us' => $response['info']['appconnect_time_us'],
			'pretransfer_time' => $response['info']['pretransfer_time'],
			'pretransfer_time_us' => $response['info']['pretransfer_time_us'],
			'redirect_time' => $response['info']['redirect_time'],
			'redirect_time_us' => $response['info']['redirect_time_us'],
			'starttransfer_time' => $response['info']['starttransfer_time'],
			'starttransfer_time_us' => $response['info']['starttransfer_time_us'],
		];

		if (empty($opts['keep_plain_headers'])) {
			$response['headers'] = self::parseHeaders($response['headers']);

			if (empty($response['headers']['content-length']) && !empty($response['body'])) {
				$response['headers']['content-length'] = strlen($response['body']);
			}

			if (empty($opts['keep_plain_body'])) {
				if (!empty($response['headers']['content-type'])) {
					$content_type = $response['headers']['content-type'];
					$content_type = explode(';', $content_type);
					$content_type = $content_type[0];
					if ($content_type==='application/json') {
						if (empty($response['headers']['content-length'])) {
							$response['body'] = [];
						} else {
							$response['body'] = json_decode($response['body'], true);
						}
					}
				}
			}
		}

		return $response;
	}

	public static function separateHeaders($response) {
		$a = explode("\r\n\r\n", $response, 2);
		if (!isset($a[1])) {
			if (substr($response, 0, 5)==='HTTP/') {
				return [$response, ''];
			}
			return ['', $response];
		}
		if (substr($a[0], 0, 5)!=='HTTP/') {
			return ['', $response];
		}
		return $a;
	}

	public static function parseHeaders($headers, $lowercase=1) {
		if (empty($headers)) {return [];}
		$arr = explode("\r\n", $headers);
		$assoc = [];
		if (count($arr) && !empty($arr[0])) {
			$assoc['http-status-line'] = array_shift($arr);
			list($assoc['http-protocol-version'], $assoc['http-status-code'], $assoc['http-reason-phrase']) = explode(' ', $assoc['http-status-line'], 3);
			foreach ($arr as $i=>$line) {
				list($key, $val) = explode(':', $line, 2);
				$key = trim($key);
				if ($lowercase) {
					$key = strtolower($key);
				}
				$val = trim($val);
				if ($key==='set-cookie') {
					$val = explode(';', $val);
					$val = $val[0];
					list($cookie_key, $cookie_val) = explode('=', $val);
					self::$cookie[$cookie_key] = urldecode($cookie_val);
					continue;
				}
				$assoc[$key] = $val;
			}
		}

		return $assoc;
	}

	public static function bakeCookie($cookie) {
		$s = '';
		foreach ($cookie as $i=>$v) {
			$v = urlencode($v);
			$s.=($s? ';': '')."{$i}={$v}";
		}
		return $s;
	}

	public static function isOk($response) {
		return (intdiv($response['status'], 100)==2);
	}

	public static function isServerError($response) {
		return (intdiv($response['status'], 100)==5);
	}

	public static function isCurlError($response) {
		return (empty($response['status']) && !empty($response['error']));
	}
}

?>