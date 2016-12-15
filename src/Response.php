<?php
namespace ffan\php\http;

/**
 * Class Response 返回类
 * @package ffan\php\http
 */
class Response
{
    const STATUS_SUCCESS = 0;
    const STATUS_INIT = 1;
    const STATUS_ERROR = 2;

    /**
     * @var string|array 数据
     */
    private $_data = '';

    /**
     * @var ClientOption 请求对象
     */
    private $_opt;

    /**
     * @var int 状态
     */
    private $_status = self::STATUS_INIT;

    /**
     * Response constructor.
     * @param ClientOption $opt 请求对象（由于日志生成需要很多请求数据，所以需要传过来）
     * @param int $error_no 错误编码
     * @param int $http_code http状态码
     * @param string $data 数据
     * @param bool $is_multi 是否是并发请求
     */
    public function __construct(ClientOption $opt, $error_no, $http_code = 0, $data = '', $is_multi = false)
    {
        $this->_opt = $opt;
        $logger = Client::getLogger();
        $log_msg = $opt->toLogMsg($error_no, $http_code, $is_multi);

        //error_no 大于0，表示curl发生错误
        if ($error_no > 0) {
            $this->_status = self::STATUS_ERROR;
        } //http_code 不为200，服务器返回错误
        else if (200 != $http_code) {
            $this->_status = self::STATUS_ERROR;
        } //自动将结果json_decode
        else if ($opt->getJsonResultFlag()) {
            //数据为空，无法decode
            if (empty($data)) {
                $this->_status = self::STATUS_ERROR;
                $log_msg .= PHP_EOL . '[JSON_DECODE] => error, data is empty!';
            } else {
                $tmp_data = json_decode($data, true);
                $decode_err = json_last_error();
                //判断是否json_decode出错，decode结果不为数组就当成错误（这样方便）
                if (JSON_ERROR_NONE !== $decode_err || !is_array($tmp_data)) {
                    $this->_status = self::STATUS_ERROR;
                    $err_msg = JSON_ERROR_NONE !== $decode_err ? json_last_error_msg() : 'Result is not array';
                    $log_msg .= PHP_EOL . '[JSON_DECODE] => error, code:' . $decode_err . ' msg:' . $err_msg;
                    $log_msg .= PHP_EOL . '[TEXT] => ' . $data . PHP_EOL;
                } else {
                    $this->_status = self::STATUS_SUCCESS;
                    $this->_data = $tmp_data;
                    $log_msg .= PHP_EOL . '[RESULT] => ' . print_r($tmp_data, true);
                }
            }
        } //请求成功，不需要将结果json_decode
        else {
            $this->_data = $data;
            $this->_status = self::STATUS_SUCCESS;
            $log_msg .= PHP_EOL . '[RESULT] => ' . $data;
        }
        if (self::STATUS_SUCCESS === $this->_status) {
            $logger->info($log_msg);
        } else {
            $logger->error($log_msg);
        }
    }

    /**
     * 获取服务器返回的数据
     * @param mixed $default_value 默认返回值
     * 如果数据出错，或者json_decode出错，返回 的值，可以指定任意值
     * @return mixed
     */
    public function get($default_value = null)
    {
        if (self::STATUS_SUCCESS !== $this->_status) {
            return $default_value;
        }
        return $this->_data;
    }

    /**
     * 是否请求成功
     * @return bool
     */
    public function isSuccess()
    {
        return self::STATUS_SUCCESS === $this->_status;
    }
}
