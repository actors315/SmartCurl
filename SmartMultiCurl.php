<?php
/**
 * SmartMultiCurl
 */
require_once 'SmartCurl.php';
class SmartMultiCurl extends SmartCurl {

	/**
	 * @var int
	 *
	 * Window size is the max number of simultaneous connections allowed.
	 *
	 * REMEMBER TO RESPECT THE SERVERS:
	 * Sending too many requests at one time can easily be perceived
	 * as a DOS attack. Increase this window_size if you are making requests
	 * to multiple servers or have permission from the receving server admins.
	 *
	 * copy from https://github.com/LionsAd/rolling-curl.git
	 */
	private $window_size = 5;

	/**
	 * 请求队列
	 */
	private $requests;

	public function request($method, $url, $vars = array()) {
		$request = parent::request($method, $url, $vars);
		unset($request -> requests);
		$this -> requests[] = $request;
	}

	/**
	 * curl 多线程
	 */
	public function execute($window_size = null) {

		if ($window_size)
			$this -> window_size = $window_size;

		if (sizeof($this -> requests) < $this -> window_size)
			$this -> window_size = sizeof($this -> requests);

		$queue = curl_multi_init();
		$map = array();

		for ($i = 0; $i < $this -> window_size; $i++) {
			curl_multi_add_handle($queue, $this -> requests[$i] -> request);
			$map[(string)$this -> requests[$i] -> request] = $i;
		}

		//foreach ($this->requests as $key => $ch) {
		//	curl_multi_add_handle($queue, $ch -> request);
		//	$map[(string)$ch -> request] = $key;
		//}

		$responses = array();
		do {
			while (($status = curl_multi_exec($queue, $active)) == CURLM_CALL_MULTI_PERFORM);

			if ($status != CURLM_OK) {
				break;
			}
			while ($done = curl_multi_info_read($queue)) {
				$error = curl_error($done['handle']);
				$results = curl_multi_getcontent($done['handle']);
				$info = curl_getinfo($done['handle']);

				if ($this -> requests[$map[(string)$done['handle']]] -> callback) {
					$callback = $this -> requests[$map[(string)$done['handle']]] -> callback;
					$request = $this -> requests[$map[(string)$done['handle']]] -> request;
					call_user_func($callback, $results, $info, $request, $error);
				}
				$responses[$map[(string)$done['handle']]] = compact('error', 'results');

				// start a new request (it's important to do this before removing the old one)
				if ($i < sizeof($this -> requests) && isset($this -> requests[$i])) {
					curl_multi_add_handle($queue, $this -> requests[$i] -> request);
					$map[(string)$this -> requests[$i] -> request] = $i;
					$i++;
				}

				curl_multi_remove_handle($queue, $done['handle']);
			}
			// 当没有数据的时候进行堵塞，把 CPU 使用权交出来，避免上面 do 死循环空跑数据导致 CPU 100%
			if ($active > 0) {
				curl_multi_select($queue, 1);
			}
		} while ($active);
		curl_multi_close($queue);
		return $responses;
	}

}
