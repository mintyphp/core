<?php

namespace MintyPHP;

class Curl
{
	/** @var array<string,mixed> */
	public static array $options = [];
	/** @var array<string,string> */
	public static array $headers = [];
	public static bool $cookies = false;

	/**
	 * @param string|array<mixed> $data
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $options
	 * @return array<string,mixed> 
	 */
	public static function navigateCached(int $expire, string $method, string $url, string|array $data, array $headers = [], array $options = []): array
	{
		return self::callCached($expire, $method, $url, $data, $headers, array_merge($options, ['CURLOPT_FOLLOWLOCATION' => true]));
	}

	/**
	 * @param string|array<mixed> $data
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $options
	 * @return array<string,mixed> 
	 */
	public static function callCached(int $expire, string $method, string $url, string|array $data, array $headers = [], array $options = []): array
	{
		$key = $method . '_' . $url . '_' . json_encode($data) . '_' . json_encode($headers) . '_' . json_encode($options);
		$result = Cache::get($key);
		if ($result) {
			return $result;
		}
		$result = self::call($method, $url, $data, $headers, $options);
		if ($result['status'] == 200) {
			Cache::set($key, $result, $expire);
		}
		return $result;
	}

	/**
	 * @param string|array<mixed> $data
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $options
	 * @return array<string,mixed> 
	 */
	public static function navigate(string $method, string $url, string|array $data = '', array $headers = [], array $options = []): array
	{
		return self::call($method, $url, $data, $headers, array_merge($options, array('CURLOPT_FOLLOWLOCATION' => true)));
	}

	/**
	 * @param string|array<mixed> $data
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $options
	 * @return array<string,mixed> 
	 */
	public static function call(string $method, string $url, string|array $data = '', array $headers = [], array $options = []): array
	{
		if (Debugger::$enabled) {
			$time = microtime(true);
		}

		$ch = curl_init();

		if (self::$cookies) {
			$cookieJar = tempnam(sys_get_temp_dir(), "curl_cookies-");
			if ($cookieJar && isset($_SESSION['curl_cookies'])) {
				file_put_contents($cookieJar, $_SESSION['curl_cookies']);
			}
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
		}

		$headers = array_merge(self::$headers, $headers);
		$options = array_merge(self::$options, $options);
		self::setOptions($ch, $method, $url, $data, $headers, $options);

		$result = strval(curl_exec($ch));
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$location = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		if (Debugger::$enabled) {
			$timing = [];
			$timing['name_lookup'] = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
			$timing['connect'] = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
			$timing['pre_transfer'] = curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME);
			$timing['start_transfer'] = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
			$timing['redirect'] = curl_getinfo($ch, CURLINFO_REDIRECT_TIME);
			$timing['total'] = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
		}

		curl_close($ch);

		if (self::$cookies && $cookieJar) {
			$_SESSION['curl_cookies'] = file_get_contents($cookieJar);
			unlink($cookieJar);
		} else {
			if (isset($_SESSION['curl_cookies'])) {
				unset($_SESSION['curl_cookies']);
			}
		}

		if (strpos($result, "\r\n\r\n") === false) {
			list($head, $body) = array($result, '');
		} else {
			list($head, $body) = explode("\r\n\r\n", $result, 2);
			while (preg_match('|\s+100\s+Continue|', explode("\r\n", $head)[0])) {
				list($head, $body) = explode("\r\n\r\n", $body, 2);
			}
		}

		$result = array('status' => $status);
		$result['headers'] = [];
		$result['data'] = $body;
		$result['url'] = $location;

		foreach (explode("\r\n", $head) as $i => $header) {
			if ($i == 0) {
				continue;
			}
			list($key, $value) = explode(': ', $header);
			$result['headers'][$key] = $value;
		}

		if (Debugger::$enabled) {
			$duration = microtime(true) - $time;
			Debugger::add('api_calls', compact('duration', 'method', 'url', 'data', 'options', 'headers', 'status', 'timing', 'result'));
		}

		return $result;
	}

	/**
	 * @param string|array<mixed> $data
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $options
	 */
	protected static function setOptions(\CurlHandle $ch, string $method, string &$url, string|array &$data, array $headers, array $options): void
	{
		// Set default options
		foreach ($options as $option => $value) {
			curl_setopt($ch, constant(strtoupper($option)), $value);
		}

		if (is_array($data)) {
			$data = http_build_query($data);
			$headers['Content-Type'] = 'application/x-www-form-urlencoded';
		} elseif (strlen($data) > 0 && in_array($data[0], ['{', '['])) {
			$headers['Content-Type'] = 'application/json';
		}

		$head = [];
		foreach ($headers as $key => $value) {
			$head[] = $key . ': ' . $value;
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $head);
		curl_setopt($ch, CURLOPT_HEADER, true);

		switch (strtoupper($method)) {
			case 'HEAD':
				curl_setopt($ch, CURLOPT_NOBODY, true);
				break;
			case 'GET':
				curl_setopt($ch, CURLOPT_HTTPGET, true);
				if ($data) {
					$url .= '?' . $data;
					$data = '';
				}
				break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, true);
				break;
			default:
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		curl_setopt($ch, CURLOPT_URL, $url);

		if (!empty($data)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	}
}
