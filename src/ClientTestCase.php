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
echo $client->request($opt);
