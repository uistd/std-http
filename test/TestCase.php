<?php
require_once '../vendor/autoload.php';
\UiStd\Common\Config::init(array(
    'runtime_path' => __DIR__ .'/runtime',
    'env' => 'dev',
    'ffan-http' => array(
        'debug_mode' => true
    )
));
new \UiStd\Logger\FileLogger('logs');

$test_1 = new \UiStd\Http\HttpClient('http://localhost/ffan/v{version}/pangu/index');
$test_1->setUrlArg('version', 1);
$result = $test_1->request();
print_r($result);
