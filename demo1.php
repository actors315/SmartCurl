<?php
require_once 'SmartCurl.php';

$cookie = file_get_contents('./data/cookie/zhihu.txt');
$curl = new SmartCurl();
$curl -> set_cookie($cookie);
$curl -> set_gzip(true);

$url = "https://www.zhihu.com/people/fenng";
$content = $curl -> get($url) -> execute();
print_r($content);
