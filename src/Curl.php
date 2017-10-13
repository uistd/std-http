<?php

namespace FFan\Std\Http;

use FFan\Std\Async\AsyncCall;
use FFan\Std\Common\Config;
use FFan\Std\Common\Env as FFanEnv;
use FFan\Std\Console\Debug;
use FFan\Std\Logger\LogHelper;

/**
 * Class CurlOption UI Service CURL 定制类
 * @package FFan\Std\Http
 */
class Curl
{
    /**
     * 默认过期时间  1000 毫秒
     */
    const DEFAULT_TIMEOUT = 1000;

    /**
     * curl fd 池的最大值
     */
    const MAX_CURL_ARR_SIZE = 5;

    /**
     * http method
     */
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';

    /**
     * 状态码
     */
    const STATUS_INIT = 1;
    const STATUS_ERROR = 2;
    const STATUS_SUCCESS = 0;

    /**
     * 事件名称
     */
    const EVENT_COMPLETE = 'uis_curl_complete';

    /**
     * @var array 请求列表
     */
    private static $request_pool;

    /**
     * @var array 懒加载列表
     */
    private static $lazy_request_pool;

    /**
     * @var int 索引
     */
    private static $index = 0;

    /**
     * @var array curl 的文件描述符
     */
    private static $curl_fd_arr;

    /**
     * @var bool 是否懒加载
     */
    private $is_lazy_load = false;

    /**
     * @var callable 懒加载回调
     */
    private $lazy_callback;

    /**
     * @var mixed 参数
     */
    private $lazy_arg;

    /**
     * 支持的方法
     * @var array
     */
    private static $allow_methods = array(
        'get' => self::METHOD_GET,
        'post' => self::METHOD_POST,
        'delete' => self::METHOD_DELETE,
        'put' => self::METHOD_PUT
    );

    /**
     * @var int
     */
    private $status = self::STATUS_INIT;

    /**
     * @var float 花费的时间
     */
    private $cost_time;

    /**
     * @var int 开始时间
     */
    private $start_time;

    /**
     * @var int 过期时间(ms)
     */
    private $timeout;

    /**
     * @var string 方法
     */
    private $method;

    /**
     * @var string url
     */
    private $url;

    /**
     * @var array
     */
    private $header_arr;

    /**
     * @var bool 是否是json串请求数据
     */
    private $json_query = true;

    /**
     * @var array
     */
    private $query_data;

    /**
     * @var string 返回的原始消息
     */
    private $response_text;

    /**
     * @var HttpClient
     */
    private $client;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $uri;

    /**
     * Curl constructor.
     * @param HttpClient $client
     * @param string $uri
     * @param string $method
     * @param null|array $query_data
     */
    public function __construct(HttpClient $client, $uri, $method = 'get', $query_data = null)
    {
        if (!is_string($uri) || empty($uri)) {
            throw new \InvalidArgumentException('Empty uri not allowed');
        }
        $this->uri = $uri;
        $this->setMethod($method);
        if (is_array($query_data)) {
            $this->query_data = $query_data;
        }
        $this->client = $client;
        $this->id = self::$index++;
        self::$request_pool[$this->id] = $this;
    }

    /**
     * 设置方法
     * @param string $method
     */
    public function setMethod($method)
    {
        $method = strtolower($method);
        if (empty($method) || !isset(self::$allow_methods[$method])) {
            $this->method = self::METHOD_GET;
        } else {
            $this->method = self::$allow_methods[$method];
        }
    }

    /**
     * 设置超时时间
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $timeout = (int)$timeout;
        if ($timeout < 0) {
            $timeout = 1000;
        } //如果小于30 把参数当 秒 处理
        elseif ($timeout < 30) {
            $timeout *= 1000;
        }
        $this->timeout = $timeout;
    }

    /**
     * 添加header
     * @param string $name
     * @param string $value
     */
    public function addHeader($name, $value)
    {
        $this->header_arr[] = $name . ':' . $value;
    }

    /**
     * 设置json传输
     * @param bool $is_json_query
     */
    public function setJsonQuery($is_json_query = true)
    {
        $this->json_query = $is_json_query;
    }

    /**
     * 获取url
     * @param array $query_data
     * @return string
     */
    private function getUri(&$query_data)
    {
        $uri = $this->uri;
        $beg_pos = strpos($uri, '{');
        //不检测变量名
        if (false === $beg_pos) {
            return $uri;
        }
        $re = preg_match_all('/\{([a-zA-Z_][a-zA-Z_]*)\}/', $uri, $match);
        if (0 === $re || empty($match[1])) {
            return $uri;
        }
        foreach ($match[1] as $item) {
            if (empty($item)) {
                continue;
            }
            if (isset($query_data[$item])) {
                $value = $query_data[$item];
                unset($query_data[$item]);
            } else {
                $value = '0';
            }
            $uri = str_replace('{' . $item . '}', (string)$value, $uri);
        }
        return $uri;
    }

    /**
     * 生成url
     * @param string $uri
     * @return string
     */
    private function makeUrl($uri)
    {
        if (0 === strpos($uri, 'http')) {
            return $uri;
        }
        $host = 'api.sit.ffan.com';
        if (FFanEnv::isProduct()) {
            $host = 'api.ffan.com';
        } elseif (FFanEnv::isUat()) {
            $host = 'api.uat.ffan.com';
        }
        $url = 'http://' . $host;
        if ('/' !== $uri{0}) {
            $url .= '/';
        }
        return $url . $uri;
    }


    /**
     * 生成curl的参数
     */
    private function makeOption()
    {
        if (null === $this->timeout) {
            $this->timeout = Config::getInt('default_curl_timeout', self::DEFAULT_TIMEOUT);
        }
        $query_data = $this->getQueryData();
        $url = $this->makeUrl($this->getUri($query_data));
        $this->url = $url;
        $options = array(
            CURLOPT_TIMEOUT_MS => $this->timeout,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        );
        switch ($this->method) {
            case self::METHOD_GET:
                //对get参数处理
                if (!empty($query_data)) {
                    $query_str = http_build_query($query_data);
                    $join_str = (false !== strpos($url, '?')) ? '&' : '?';
                    $url .= $join_str . $query_str;
                    $options[CURLOPT_URL] = $url;
                    $this->url = $url;
                }
                $options[CURLOPT_POST] = 0;
                break;
            case self::METHOD_POST:
                $options[CURLOPT_POST] = 1;
                if (!empty($query_data)) {
                    if ($this->json_query) {
                        $query_data = $this->jsonQueryOption($query_data);
                    }
                    $options[CURLOPT_POSTFIELDS] = $query_data;
                }
                break;
            case self::METHOD_PUT:
                if ($this->json_query) {
                    $query_data = $this->jsonQueryOption($query_data);
                }
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $options[CURLOPT_POSTFIELDS] = $query_data;
                break;
            case self::METHOD_DELETE:
                $this->addHeader('X-HTTP-Method-Override', 'DELETE');
                if ($this->json_query) {
                    $query_data = $this->jsonQueryOption($query_data);
                }
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                $options[CURLOPT_POSTFIELDS] = $query_data;
                break;
        }
        if (!empty($this->header_arr)) {
            $options[CURLOPT_HTTPHEADER] = $this->header_arr;
        }
        $this->start_time = microtime(true);
        return $options;
    }

    /**
     * json串请求数据
     * @param array $data
     * @return string
     */
    private function jsonQueryOption($data)
    {
        $post_str = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->addHeader('Content-Type', 'application/json');
        $this->addHeader('Content-Length', strlen($post_str));
        return $post_str;
    }

    /**
     * 获取请求数据
     * @return array
     */
    private function getQueryData()
    {
        if (!empty($this->query_data)) {
            return $this->query_data;
        }
        if ($this->client) {
            $this->query_data = $this->client->makeQueryData();
        }
        if (!is_array($this->query_data)) {
            $this->query_data = array();
        }
        return $this->query_data;
    }

    /**
     * 发送请求
     */
    public function request()
    {
        //已经请求过了, 不要再请求了
        if (self::STATUS_INIT !== $this->status) {
            return;
        }
        unset(self::$request_pool[$this->id]);
        $curl_fd = self::getCurlFd();
        curl_setopt_array($curl_fd, $this->makeOption());
        Debug::addIoStep();
        $response_text = curl_exec($curl_fd);
        $this->complete($curl_fd, $response_text);
        $this->closeCurl($curl_fd);
    }

    /**
     * 请求结束
     * @param Resource $curl_fd
     * @param string $response_text
     * @param bool $is_multi 是否是并行请求
     */
    private function complete($curl_fd, $response_text, $is_multi = false)
    {
        //先临时设置成error
        $this->status = self::STATUS_ERROR;
        $this->saveCostTime();
        $error_code = curl_errno($curl_fd);
        $http_code = 0;
        if (0 == $error_code) {
            $info = curl_getinfo($curl_fd);
            $http_code = $info['http_code'];
        }
        $this->responseHandle($error_code, $http_code, $response_text, $is_multi);
        //懒加载 回调处理
        if ($this->is_lazy_load && $this->lazy_callback) {
            if (null !== $this->lazy_arg) {
                call_user_func_array($this->lazy_callback, [$this->client, $this->lazy_arg]);
            } else {
                call_user_func($this->lazy_callback, $this->client);
            }
        }
    }

    /**
     * 并行请求所有还未请求的实例化的对象
     */
    public static function multiRequest()
    {
        $request_num = count(self::$request_pool);
        //没有需要请求的
        if (0 === $request_num) {
            return;
        }
        //只有一个需要请求的，也不要multi请求了
        if (1 === $request_num) {
            /** @var self $req */
            $req = current(self::$request_pool);
            $req->request();

            return;
        }
        $multi_handle = curl_multi_init();
        if (false === $multi_handle) {
            throw new \RuntimeException('Can not create multi curl');
        }
        $handle_arr = [];
        $handle_map = [];
        /**
         * @var int $id
         * @var self $req
         */
        foreach (self::$request_pool as $id => $req) {
            $tmp_handle = self::getCurlFd();
            $handle_arr[$id] = $tmp_handle;
            $handle_map[(int)$tmp_handle] = $req;
            $options = $req->makeOption();
            unset(self::$request_pool[$id]);
            curl_setopt_array($tmp_handle, $options);
            curl_multi_add_handle($multi_handle, $tmp_handle);
        }
        //增加 i/o 步骤
        Debug::addIoStep();
        do {
            while (($code = curl_multi_exec($multi_handle, $active)) == CURLM_CALL_MULTI_PERFORM) ;

            if ($code !== CURLM_OK) {
                break;
            }
            while ($done_info = curl_multi_info_read($multi_handle)) {
                $tmp_handle = $done_info['handle'];
                /** @var Curl $current_req */
                $handle_id = (int)$tmp_handle;
                if (!isset($handle_map[$handle_id])) {
                    continue;
                }
                $current_req = $handle_map[$handle_id];
                $data = curl_multi_getcontent($tmp_handle);
                $current_req->complete($tmp_handle, $data, true);
                curl_multi_remove_handle($multi_handle, $tmp_handle);
                curl_close($tmp_handle);
            }
            if ($active > 0) {
                curl_multi_select($multi_handle, 0.1);
            }

        } while ($active);
        curl_multi_close($multi_handle);
    }

    /**
     * @param int $error_code 结果码
     * @param int $http_code http status
     * @param string $response_text
     * @param bool $is_multi 是否是并行请求
     */
    private function responseHandle($error_code, $http_code, $response_text, $is_multi = false)
    {
        $log_msg = $this->logMsg($error_code, $http_code, $is_multi);
        //error_code 大于0，表示curl发生错误
        if (0 === $error_code && 200 === $http_code) {
            $this->response_text = $response_text;
            $this->status = self::STATUS_SUCCESS;
            //sit 或者 dev 打印出结果数据
            if (FFanEnv::getEnv() <= FFanEnv::SIT) {
                $log_msg .= PHP_EOL . '[RESPONSE]' . $response_text . PHP_EOL;
            }
        }
        $logger = LogHelper::getLogRouter();
        if (self::STATUS_SUCCESS === $this->status) {
            $logger->info($log_msg);
        } else {
            $logger->error($log_msg);
        }
    }

    /**
     * 生成日志字符串
     * @param int $error_code 错误编号
     * @param int $http_code http状态码
     * 如果curl已经出错，不需要检查http_code
     * @param bool $is_multi 是否并发请求
     * @return string
     */
    private function logMsg($error_code, $http_code = 0, $is_multi = false)
    {
        $str = Debug::getIoStepStr() . '[CURL] [';
        if ($is_multi) {
            $str .= 'MULTI ';
        }
        if ($this->is_lazy_load) {
            $str .= 'LAZY ';
        }
        $str .= $this->method . '] ' . $this->url . PHP_EOL
            . '[TIME] => cost_time:' . $this->cost_time . 'ms' . PHP_EOL;
        if (!empty($this->query_data) && self::METHOD_GET !== $this->method) {
            $str .= '[QUERY] => ' . json_encode($this->query_data);
        }
        $str .= '[STATUS] => ';
        if ($error_code > 0) {
            $str .= 'ERROR ' . CurlCode::errorCode($error_code);
        } else {
            $str .= 'HTTP_CODE: ' . $http_code;
        }
        return $str;
    }

    /**
     * 关闭 或者 回收
     * @param $curl_fd
     */
    private function closeCurl($curl_fd)
    {
        if (null === self::$curl_fd_arr) {
            self::$curl_fd_arr = array();
        }
        if (count(self::$curl_fd_arr) >= self::MAX_CURL_ARR_SIZE) {
            curl_close($curl_fd);
            return;
        }
        //回收, 以备重用
        self::$curl_fd_arr[] = $curl_fd;
    }

    /**
     * 获取curl fd
     * @return Resource
     */
    private static function getCurlFd()
    {
        if (empty(self::$curl_fd_arr)) {
            $new_curl = curl_init();
            if (false === $new_curl) {
                throw new \RuntimeException('No more curl resource');
            }
            return $new_curl;
        } else {
            /** @var Resource $fd */
            $fd = array_pop(self::$curl_fd_arr);
            curl_reset($fd);
            return $fd;
        }
    }

    /**
     * 保存消耗时间
     */
    private function saveCostTime()
    {
        $this->cost_time = floor((microtime(true) - $this->start_time) * 1000);
    }

    /**
     * 获取请求结果
     * @return string
     */
    public function getResponse()
    {
        //如果还没有请求， 请执行请求
        if (self::STATUS_INIT === $this->status) {
            $this->request();
        }
        return $this->response_text;
    }

    /**
     * 是否成功
     * @return bool
     */
    public function isSuccess()
    {
        return self::STATUS_SUCCESS === $this->status;
    }

    /**
     * 获取请求ID
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 设置本次请求为懒加载
     * @param callable|null $callback
     * @param null $arg
     */
    public function setLayRequest(callable $callback = null, $arg = null)
    {
        //已经不在request_pool里了， 就不用懒加载了
        if (!isset(self::$request_pool[$this->id])) {
            return;
        }
        unset(self::$request_pool[$this->id]);
        AsyncCall::setCallback('Curl', array('\FFan\Std\Http\Curl', 'lazyRequest'));
        self::$lazy_request_pool[$this->id] = $this;
        $this->is_lazy_load = true;
        $this->lazy_callback = $callback;
        $this->lazy_arg = $arg;
    }

    /**
     * 执行懒加载请求
     */
    public static function lazyRequest()
    {
        if (!empty(self::$lazy_request_pool)) {
            foreach (self::$lazy_request_pool as $id => $request) {
                self::$request_pool[$id] = $request;
            }
            self::$lazy_request_pool = null;
        }
        self::multiRequest();
        //有可能lazy request的回调里又产生了lazy_request，所以要一直一直调用，直到所有请求都执行了
        while (!empty(self::$lazy_request_pool) || !empty(self::$request_pool)) {
            self::lazyRequest();
        }
    }
}