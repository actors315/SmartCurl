<?php
/**
 * SmartCurl
 */
class SmartCurl {

    /**
     * 请求参数
     */
    private $options = array(CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_RETURNTRANSFER => 1, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 60, CURLOPT_RETURNTRANSFER => 1, CURLOPT_HEADER => 0, CURLOPT_NOSIGNAL => 1, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.81 Safari/537.36", );

    /**
     * @var array
     */
    private $headers = array();

    /**
     * 当前请求方法
     */
    private $method;
    
    /**
     * 前会话最近一次错误的字符串
     */
    private $error;

    /**
     * 最后一次传输的相关信息
     */
    private $info;

    /**
     * 当前请求
     */
    protected $request;

    /**
     * 请求响应
     */
    protected $callback;

    public function __construct($config = array()) {
        if (isset($config['options'])) {
            $this -> options = $config['options'] + $this -> options;
        }
        if (isset($config['headers'])) {
            $this -> headers = $config['headers'] + $this -> headers;
        }
    }

    /**
     * 设置options
     * @param array $options
     */
    public function set_options($options) {
        $this -> options = $options + $this -> options;
    }

    /**
     * set timeout
     *
     * @param init $timeout
     * @return
     */
    public function set_timeout($timeout) {
        $this -> options[CURLOPT_TIMEOUT] = $timeout;
    }

    /**
     * set proxy
     *
     */
    public function set_proxy($proxy) {
        $this -> options[CURLOPT_PROXY] = $proxy;
    }

    /**
     * set referer
     *
     */
    public function set_referer($referer) {
        $this -> options[CURLOPT_REFERER] = $referer;
    }

    /**
     * 设置 user_agent
     *
     * @param string $useragent
     * @return void
     */
    public function set_useragent($useragent) {
        $this -> options[CURLOPT_USERAGENT] = $useragent;
    }

    /**
     * 设置COOKIE
     *
     * @param string $cookie
     * @return void
     */
    public function set_cookie($cookie) {
        $this -> options[CURLOPT_COOKIE] = $cookie;
    }

    /**
     * 设置COOKIE FILE
     *
     * @param string $cookie_file
     * @return void
     */
    public function set_cookiefile($cookiefile) {
        $this -> options[CURLOPT_COOKIEFILE] = $cookiefile;
        //在访问其他页面时拿着这个cookie文件去访问
        $this -> options[CURLOPT_COOKIEJAR] = $cookiejar;
        //连接时把获得的cookie存为文件
    }

    /**
     * 设置Gzip
     *
     * @param bool $gzip
     * @return void
     */
    public function set_gzip($gzip) {
        if ($gzip) {
            $this -> options[CURLOPT_ENCODING] = 'gzip';
        }
    }

    /**
     * 设置post参数
     * $param mixed $vars
     */
    public function set_postfields($vars) {
        if (!empty($vars)) {
            $vars = (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
            $this -> options[CURLOPT_POSTFIELDS] = $vars;
        }
    }

    /**
     * 设置Headers
     *
     * @param string $headers
     * @return void
     */
    public function set_headers($headers) {
        $this -> headers = $headers + $this -> headers;
    }

    /**
     * 设置IP
     *
     * @param string $ip
     * @return void
     */
    public function set_ip($ip) {
        $headers = array('CLIENT-IP' => $ip, 'X-FORWARDED-FOR' => $ip, );
        $this -> headers = $headers + $this -> headers;
    }

    /**
     * 设置Hosts
     *
     * @param string $hosts
     * @return void
     */
    public function set_hosts($hosts) {
        $headers = array('Host' => $hosts, );
        $this -> headers = $headers + $this -> headers;
    }

    /**
     * 回调函数
     */
    public function set_callback($callback) {
        if (is_callable($callback)) {
            $this -> callback = $callback;
        }
    }

    public function request($method, $url, $vars = array()) {

        $this -> options[CURLOPT_URL] = $url;

        $this -> request = curl_init();

        $this -> set_postfields($vars);
        $this -> set_request_method($method);
        $this -> set_request_headers();
        $this -> set_request_options();

        $curl = clone $this;
        return $curl;
    }

    public function execute() {
        $output = curl_exec($this -> request);
        $this -> info = curl_getinfo($this -> request);
        if ($output === false) {
            $this -> error = curl_error($this -> request);
        }
        if ($this -> callback) {
            call_user_func($this -> callback, $output, $this -> info, $this -> request, $this -> error);
        }
        curl_close($this -> request);
        return $output;
    }

    public function delete($url, $vars = array()) {
        return $this -> request('DELETE', $url, $vars);
    }

    public function head($url, $vars = array()) {
        return $this -> request('HEAD', $url, $vars);
    }

    public function put($url, $vars = array()) {
        return $this -> request('PUT', $url, $vars);
    }

    public function post($url, $vars = array()) {
        return $this -> request('POST', $url, $vars);
    }

    public function get($url, $vars = array()) {
        if (!empty($vars)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }
        return $this -> request('GET', $url);
    }

    private function set_request_method($method) {
        switch (strtoupper($method)) {
            case 'HEAD' :
                curl_setopt($this -> request, CURLOPT_NOBODY, true);
                break;
            case 'GET' :
                curl_setopt($this -> request, CURLOPT_HTTPGET, true);
                break;
            case 'POST' :
                curl_setopt($this -> request, CURLOPT_POST, true);
                break;
            default :
                curl_setopt($this -> request, CURLOPT_CUSTOMREQUEST, $method);
        }
        $this -> method = strtoupper($method);
    }

    private function set_request_headers() {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this -> request, CURLOPT_HTTPHEADER, $headers);
    }

    private function set_request_options() {
        foreach ($this->options as $key => $value) {
            curl_setopt($this -> request, $key, $value);
        }
    }

    public function __toString() {
        return 'method:' . $this -> method . ';' . implode(';', $this -> options) . ';' . implode(';', $this -> headers);
    }

}

/**
 * http测试
 * 注：PHP版本5.2以上才支持CURL_IPRESOLVE_V4
 * @param $url 网站域名
 * @param $type 网站访问协议
 * @param $ipresolve 解析方式

 public function web_http($url,$type,$ipresolve) {
 //设置Header头
 $header[] = "Accept: application/json";
 $header[] = "Accept-Encoding: gzip";
 $httptype = function_exists('curl_init');
 if (!$httptype) {
 $html = file_get_contents($url);
 } else {
 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $url);
 //输出头信息
 curl_setopt($ch, CURLOPT_HEADER, 1);
 //递归访问location跳转的链接，直到返回200OK
 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
 //不对HTML中的BODY部分进行输出
 curl_setopt($ch, CURLOPT_NOBODY, 1);
 //将结果以文件流的方式返回，不是直接输出
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
 //以IPv4/IPv6的方式访问
 if($ipresolve=='ipv6') {
 curl_setopt($ch,CURLOPT_IPRESOLVE,CURL_IPRESOLVE_V6);
 }else{
 curl_setopt($ch,CURLOPT_IPRESOLVE,CURL_IPRESOLVE_V4);
 }
 //添加HTTP header头采用压缩和GET方式请求
 curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
 curl_setopt($ch,CURLOPT_ENCODING , "gzip");
 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
 //清除DNS缓存
 curl_setopt($ch,CURLOPT_DNS_CACHE_TIMEOUT,0);
 //设置连接超时时间
 curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,15);
 //设置访问超时
 curl_setopt($ch,CURLOPT_TIMEOUT,50);
 //设置User-agent
 curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.47 Safari/536.11');
 if($type=="https") {
 //不对认证证书来源的检查
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
 //从证书中检查SSL加密算法是否存在
 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
 }
 //执行Curl操作
 $html = curl_exec($ch);
 //获取一个cURL连接资源句柄的信息（获取最后一次传输的相关信息）
 $info = curl_getinfo($ch);
 curl_close($ch);
 }
 return $info;
 }
 */
