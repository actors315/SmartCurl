<?php
/**
 * Created by PhpStorm.
 * User: xiehuanjin
 * Date: 2018/5/2
 * Time: 9:58
 */

namespace lingyin\curl;


class MultiCurl
{

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
    private $windowSize = 5;

    /**
     * 请求队列
     */
    private $requests;

    /**
     * 填加请求
     *
     * @param Curl $request
     * @return $this
     * @throws \Exception
     */
    public function addRequest($request)
    {
        if (!($request instanceof Curl)) {
            throw new \Exception('请求不合法');
        }
        $this->requests[] = $request;
        return $this;
    }

    /**
     * curl 多线程
     *
     * @param null|int $windowSize
     * @return array
     */
    public function execute($windowSize = null)
    {

        if ($windowSize)
            $this->windowSize = $windowSize;

        if (sizeof($this->requests) < $this->windowSize)
            $this->windowSize = count($this->requests);

        $queue = curl_multi_init();
        $map = array();

        for ($i = 0; $i < $this->windowSize; $i++) {
            curl_multi_add_handle($queue, $this->requests[$i]->request);
            $map[(string)$this->requests[$i]->request] = $i;
        }

        $responses = array();
        do {
            while (($status = curl_multi_exec($queue, $active)) == CURLM_CALL_MULTI_PERFORM) ;

            if ($status != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($queue)) {

                $backData = [];
                $backData['data'] = curl_multi_getcontent($done['handle']);
                $backData['info'] = curl_getinfo($done['handle']);
                $backData['httpCode'] = '200';
                if ($backData['data'] === false) {
                    $backData['httpCode'] = curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);
                    $backData['logInfo'] = [
                        'url' => $this->requests[$map[(string)$done['handle']]]->options[CURLOPT_URL],
                        'params' => $this->requests[$map[(string)$done['handle']]]->options,
                        'error' => curl_error($done['handle'])
                    ];
                }

                if ($this->requests[$map[(string)$done['handle']]]->callback) {
                    $callback = $this->requests[$map[(string)$done['handle']]]->callback;
                    $request = $this->requests[$map[(string)$done['handle']]]->request;
                    call_user_func($callback, $backData['data'], $backData['info'], $request, $backData['logInfo']);
                }
                $responses[$map[(string)$done['handle']]] = $backData;

                // start a new request (it's important to do this before removing the old one)
                if ($i < sizeof($this->requests) && isset($this->requests[$i])) {
                    curl_multi_add_handle($queue, $this->requests[$i]->request);
                    $map[(string)$this->requests[$i]->request] = $i;
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