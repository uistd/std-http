<?php
namespace ffan\http;

use ffan\utils\Env as FFanEnv;

require_once '../vendor/autoload.php';
require_once '../src/Client.php';
require_once '../src/HttpException.php';

FFanEnv::setLogPath(__DIR__ . DIRECTORY_SEPARATOR . 'runtime');
FFanEnv::setDev();

$client = new Client();
$opt = new ClientOption('http://www.baidu.com');
$opt->setJsonResultFlag(false);
$opt2 = new ClientOption('http://192.168.128.128/test.php', ClientOption::METHOD_POST, array('a' => 1, 'b' => 2));
$opt2->setJsonResultFlag(false);
$opt3 = new ClientOption('http://192.168.128.128/testJson.php?is_error=0');
$opt4 = new ClientOption('http://192.168.128.128/testJson.php?is_error=1');
$opt5 = new ClientOption('http://192.168.128.128/noPage.php');
$requests = array(
    'opt1' => $opt,
    'opt2' => $opt2,
    'opt3' => $opt3,
    'opt4' => $opt4,
    'opt5' => $opt5,
);
$t1 = microtime(true);
//分别执行
foreach ($requests as $each_req) {
    $re = $client->request($each_req);
    $re->get();
}
echo 'Done! use time:' . floor((microtime(true) - $t1) * 1000) . 'ms' . PHP_EOL;
$t2 = microtime(true);
//批量执行
$res = $client->multiRequest($requests);
/**
 * @var string $key
 * @var Response $response
 */
foreach ($res as $key => $response) {
    $response->get();
}
echo 'Done! use time:' . floor((microtime(true) - $t2) * 1000) . 'ms' . PHP_EOL;
