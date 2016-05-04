<?php
require_once 'SmartMultiCurl.php';

$cookie = file_get_contents('./data/cookie/zhihu.txt');

$multi = new SmartMultiCurl();
$multi -> set_cookie($cookie);
$multi -> set_gzip(true);
$multi ->set_callback(function($response, $info, $request, $error) {
    if (empty($response)) {
        print_r($error);
        file_put_contents("./data/error_timeout.log", date("Y-m-d H:i:s") . ' error: ' . json_encode($error) . PHP_EOL, FILE_APPEND);
        return;
        //callback不能用exit
    }
    print_r($response);
    echo PHP_EOL;
    echo "------------------------------这里是分割线--------------------------";
});
//request1
$url = "https://www.zhihu.com/people/fenng";
$multi -> get($url);
//request2
$url = "https://www.zhihu.com/people/fenng/about";
$multi -> get($url);
$result = $multi -> execute();
