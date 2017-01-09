<?php
namespace ffan\php\http;

require_once '../vendor/autoload.php';

use ffan\php\utils\Config as FFanConfig;
use ffan\php\utils\Debug as FFanDebug;

FFanConfig::addArray(array(
    'env' => 'dev',
    'ffan-logger.web' => array(
        'file' => 'http',
    ),
    'runtime_path' => __DIR__ . DIRECTORY_SEPARATOR . 'runtime',
));


$client = new Client();
$opt = new ClientOption('https://www.baidu.com');
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
FFanDebug::timerStart();
//分别执行
foreach ($requests as $each_req) {
    $re = $client->request($each_req);
    $re->get();
}
echo 'Done! use time:' . FFanDebug::timerStop() . 'ms' . PHP_EOL;
FFanDebug::timerStart();
//批量执行
$res = $client->multiRequest($requests);
/**
 * @var string $key
 * @var Response $response
 */
foreach ($res as $key => $response) {
    $response->get();
}
echo 'Done! use time:' . FFanDebug::timerStop() . 'ms' . PHP_EOL;
