<?php

namespace FFan\Std\Http;

/**
 * Class ApiResult
 * @package FFan\Std\Http
 */
class ApiResult
{
    /**
     * @var int
     */
    public $status = 500;

    /**
     * @var string
     */
    public $message = 'error';

    /**
     * @var mixed
     */
    public $data;

    /**
     * @var array 额外数据
     */
    private $ext_result;

    /**
     * ApiResult constructor.
     * @param array $result
     */
    public function __construct(array $result = null)
    {
        if (is_array($result)) {
            $this->arrayUnpack($result);
        }
    }

    /**
     * 解析数组
     * @param array $result
     */
    public function arrayUnpack(array $result)
    {
        if (isset($result['status'])) {
            $this->status = (int)$result['status'];
            unset($result['status']);
        }
        if (isset($result['message'])) {
            $this->message = $result['message'];
            unset($result['message']);
        }
        if (isset($result['data'])) {
            $this->data = $result['data'];
            unset($result['data']);
        }
        if (!empty($result)) {
            //一些非标准接口返回的额外数据
            $this->ext_result = $result;
        }
    }

    /**
     * 获取附加的数据
     * @param string $name
     * @param $default mixed 不存在时的默认值
     * @return mixed
     */
    public function getExtResult($name, $default = [])
    {
        return isset($this->ext_result[$name]) ? $this->ext_result[$name] : $default;
    }
}
