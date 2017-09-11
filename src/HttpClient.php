<?php

namespace FFan\Std\Http;
use FFan\Std\Logger\LogHelper;

/**
 * Class HttpClient
 * @package FFan\Std\Http
 */
class HttpClient
{
    /**
     * @var Curl
     */
    private $uis_curl_handler;

    /**
     * @var callable 请求数据过滤
     */
    private $query_data_filter;

    /**
     * HttpClient constructor.
     * @param string $uri 接口地址 或者 url
     * @param string $method
     * @param null $data
     */
    public function __construct($uri = '', $method = 'get', $data = null)
    {
        $this->uis_curl_handler = new Curl($this, $uri, $method, $data);
    }

    /**
     * 变更方法
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->uis_curl_handler->setMethod($method);
    }

    /**
     * 获取结果
     * @return array
     */
    protected function getResponseData()
    {
        $result = array();
        $response_text = $this->uis_curl_handler->getResponse();
        //执行结果不成功
        if (!$this->uis_curl_handler->isSuccess()) {
            return $result;
        }
        $logger = LogHelper::getLogRouter();
        //数据为空，无法decode
        if (empty($response_text)) {
            $log_msg = '[JSON_DECODE] => error, data is empty!';
            $logger->error($log_msg);
            return $result;
        }
        $json_arr = json_decode($response_text, true);
        $decode_err = json_last_error();
        //判断是否json_decode出错，decode结果不为数组就当成错误（这样方便）
        if (JSON_ERROR_NONE !== $decode_err || !is_array($json_arr)) {
            $err_msg = JSON_ERROR_NONE !== $decode_err ? json_last_error_msg() : 'Result is not array';
            $log_msg = '[JSON_DECODE] => error, code:' . $decode_err . ' msg:' . $err_msg;
            $log_msg .= PHP_EOL . '[TEXT] => ' . $response_text;
            $logger->error($log_msg);
            return $result;
        }
        $result = $json_arr;
        return $result;
    }

    /**
     * 获取responseText
     */
    protected function getResponseText()
    {
        $text = $this->uis_curl_handler->getResponse();
        if (!$this->uis_curl_handler->isSuccess()) {
            return '';
        }
        return $text;
    }

    /**
     * 返回标准api结果对象
     * @return ApiResult
     */
    public function getResponse()
    {
        return new ApiResult($this->getResponseData());
    }

    /**
     * 执行请求(可多次执行)
     * @return ApiResult
     */
    public function request()
    {
        $this->uis_curl_handler->request();
        return $this->getResponse();
    }

    /**
     * 生成请求数据
     * @return array|null
     */
    public function makeQueryData()
    {
        if (method_exists($this, 'arrayPack')) {
            $result = call_user_func([$this, 'arrayPack']);
        } else {
            $result = null;
        }
        if (null !== $this->query_data_filter) {
            $result = call_user_func($this->query_data_filter, $result);
        }
        return $result;
    }

    /**
     * 获取Curl对象
     * @return Curl
     */
    public function getCurl()
    {
        return $this->uis_curl_handler;
    }

    /**
     * 设置本次请求为懒加载
     * @param callable|null $callback
     * @param null $arg
     */
    public function setLazyRequest(callable $callback = null, $arg = null)
    {
        $this->uis_curl_handler->setLayRequest($callback, $arg);
    }

    /**
     * 设置数据请求前过滤函数
     * @param callable $filter
     */
    public function setQueryDataFilter(callable $filter)
    {
        $this->query_data_filter = $filter;
    }
}
