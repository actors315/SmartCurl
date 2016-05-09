<?php
require_once 'SmartMultiCurl.php';

$cookie = file_get_contents('./data/cookie/zhihu.txt');

$multi = new SmartMultiCurl();
$multi -> set_cookie($cookie);
$multi -> set_gzip(true);
//request1
$url = "https://www.zhihu.com/people/fenng";
$multi -> get($url);
//request2
//$url = "https://www.zhihu.com/people/fenng/about";
//$multi -> get($url);
$result = $multi -> execute();
print_r($result);
