<?php
require_once '../vendor/autoload.php';
\FFan\Std\Common\Config::init(array(
    'runtime_path' => __DIR__ .'/runtime',
    'env' => 'dev'
));
new \FFan\Std\Logger\FileLogger('logs');

$test_1 = new \FFan\Std\Http\HttpClient('http://api.ffan.com/ffan/v{version}/pangu/index');
$test_1->setUrlArg('version', 1);
$result = $test_1->request();
print_r($result);
