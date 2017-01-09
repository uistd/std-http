<?php
namespace ffan\php\http;

/**
 * Class HttpException
 * @package ffan\php\http
 */
class HttpException extends \Exception
{
    /**
     * 状态错误
     */
    const STATUS_ERROR = 60000;

    /**
     * 执行错误
     */
    const EXECUTE_ERROR = 60001;

    /**
     * 格式化输出
     */
    public function __toString()
    {
        return 'HttpException message:' . $this->getMessage() . ' code:' . $this->getCode();
    }
}