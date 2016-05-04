<?php
/**
 * SmartMultiCurl
 */
require_once 'SmartCurl.php';
class SmartMultiCurl extends SmartCurl {

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
    public function execute() {
        $queue = curl_multi_init();
        $map = array();

        foreach ($this->requests as $key => $ch) {
            curl_multi_add_handle($queue, $ch -> request);
            $map[(string)$ch -> request] = $key;
        }

        $responses = array();
        do {
            while (($code = curl_multi_exec($queue, $active)) == CURLM_CALL_MULTI_PERFORM);
            
            if ($code != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($queue)) {
                $error = curl_error($done['handle']);
                $results = curl_multi_getcontent($done['handle']);
                $info = curl_getinfo($done['handle']);
                
                if ($this -> requests[$map[(string)$done['handle']]] -> callback) {
                    $callback = $this->requests[$map[(string)$done['handle']]]->callback;
                    $request = $this->requests[$map[(string)$done['handle']]]->request;
                    call_user_func($callback, $results, $info, $request, $error);
                }
                $responses[$map[(string)$done['handle']]] = compact('error', 'results');
                curl_multi_remove_handle($queue, $done['handle']);
                curl_close($done['handle']);
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
