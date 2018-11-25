<?php
/**
 * Created by PhpStorm.
 * User: xiehuanjin
 * Date: 2018/5/2
 * Time: 9:57
 */

namespace lingyin\curl;


class Curl
{
    const POST = 'POST';

    const GET = 'GET';

    /**
     * 请求头
     * 如 CLIENT-IP Hosts等
     *
     * @var array
     */
    private $headers = [];

    /**
     * 请求参数
     *
     * @var array
     */
    private $options = [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_FOLLOWLOCATION => 1,
    ];

    /**
     * 回调函数
     *
     * @var mixed
     */
    protected $callback;

    /**
     * 当前请求
     */
    protected $request;

    public function __construct($config = [])
    {
        if (isset($config['options'])) {
            $this->options = $config['options'] + $this->options;
        }
        if (isset($config['headers'])) {
            $this->headers = $config['headers'] + $this->headers;
        }
    }

    /**
     * 设置options
     *
     * @param array $options
     * @return $this
     */
    public function setOptions($options = [])
    {
        $this->options = $options + $this->options;
        return $this;
    }

    /**
     * 设置Headers
     *
     * @param array $headers
     * @return $this
     */
    public function setHeaders($headers = [])
    {
        $this->headers = $headers + $this->headers;
        return $this;
    }

    /**
     * 设置回调函数
     *
     * @param callable $callback
     * @return $this
     * @throws \Exception
     */
    public function setCallback($callback)
    {
        if (is_callable($callback)) {
            $this->callback = $callback;
        } else {
            throw new \Exception('回调方法无法执行');
        }
        return $this;
    }

    /**
     * 设置post参数
     *
     * @param mixed $vars
     * @return  $this
     */
    public function setPostFields($vars)
    {
        if (!empty($vars)) {
            $vars = (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
            $this->options[CURLOPT_POSTFIELDS] = $vars;
        }
        return $this;
    }

    public function request($method, $url, $vars = array())
    {
        $this->options[CURLOPT_URL] = $url;
        $this->request = curl_init();
        $this->setPostFields($vars);
        $this->setRequestMethod($method);
        $this->setRequestHeaders();
        $this->setRequestOptions();

        return $this;
    }

    public function execute()
    {
        $backData['data'] = curl_exec($this->request);
        $backData['info'] = curl_getinfo($this->request);
        $backData['httpCode'] = '200';
        if ($backData['data'] === false) {
            $backData['httpCode'] = curl_getinfo($this->request, CURLINFO_HTTP_CODE);
            $backData['logInfo'] = [
                'url' => $this->options[CURLOPT_URL],
                'params' => $this->options,
                'error' => curl_error($this->request)
            ];
        }
        if ($this->callback) {
            call_user_func($this->callback, $backData['data'], $backData, $this->request);
        }
        curl_close($this->request);
        return $backData;
    }

    /**
     * @param string $url
     * @param array | string $vars
     * @return mixed
     */
    public function post($url, $vars = [])
    {
        return $this->request(self::POST, $url, $vars)->execute();
    }

    public function get($url, $vars = [])
    {
        if (!empty($vars)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }
        return $this->request(self::GET, $url)->execute();
    }


    private function setRequestMethod($method)
    {
        switch (strtoupper($method)) {
            case 'HEAD' :
                curl_setopt($this->request, CURLOPT_NOBODY, true);
                break;
            case 'GET' :
                curl_setopt($this->request, CURLOPT_HTTPGET, true);
                break;
            case 'POST' :
                curl_setopt($this->request, CURLOPT_POST, true);
                break;
            default :
                curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    public function __get($name)
    {
        if (in_array($name, ['request', 'callback'])) {
            return $this->{$name};
        }
    }

    private function setRequestHeaders()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
    }

    private function setRequestOptions()
    {
        foreach ($this->options as $key => $value) {
            curl_setopt($this->request, $key, $value);
        }
    }
}