<?php
namespace ffan\http;

use Psr\Log\LoggerInterface;
use ffan\logger\LoggerFactory;

/**
 * Class Client
 * @package ffan\http
 */
class Client
{
    /**
     * @var resource curl handle
     */
    private $_curl_handle;

    public function __destruct()
    {
        if ($this->_curl_handle) {
            curl_close($this->_curl_handle);
        }
    }

    /**
     * 获取日志记录器
     * @return LoggerInterface
     */
    public static function getLogger()
    {
        return LoggerFactory::get();
    }

    /**
     * 获取curl handle
     */
    private function getCurlHandle()
    {
        if (!$this->_curl_handle) {
            $this->_curl_handle = curl_init();
            if (false === $this->_curl_handle) {
                throw new HttpException('Can not create curl!');
            }
        }
        return $this->_curl_handle;
    }

    /**
     * 每次使用完后，重置curl
     */
    private function resetCurl()
    {
        if (!$this->_curl_handle) {
            return;
        }
        curl_reset($this->_curl_handle);
    }

    /**
     * 执行请求
     * @param ClientOption $opt 一个请求对象
     * @return Response
     * @throws HttpException
     */
    public function request(ClientOption $opt)
    {
        $curl_handle = $this->getCurlHandle();
        curl_setopt_array($curl_handle, $opt->dumpCurlOpt());
        $response_text = curl_exec($curl_handle);
        $this->resetCurl();
        $opt->complete();
        $error_no = curl_errno($curl_handle);
        $http_code = 0;
        if (0 == $error_no) {
            $info = curl_getinfo($this->_curl_handle);
            $http_code = $info['http_code'];
        }
        $response = new Response($opt, $error_no, $http_code, $response_text);
        return $response;
    }

    /**
     * 批量执行请求
     * @param array $requests 请求数据
     * @return array
     * @throws HttpException
     */
    public function multiRequest(array $requests)
    {
        $result = array();
        $multi_handle = curl_multi_init();
        if (false === $multi_handle) {
            throw new HttpException('Can not create multi curl');
        }
        $handle_arr = [];
        $handle_map = [];
        /**
         * @var string|int $key
         * @var ClientOption $each_opt
         */
        foreach ($requests as $key => $each_opt) {
            $tmp_handle = curl_init();
            if (false === $tmp_handle) {
                curl_multi_close($multi_handle);
                throw new HttpException('Can not create curl any more');
            }
            $handle_arr[$key] = $tmp_handle;
            $handle_map[(int)$tmp_handle] = $key;
            $options = $each_opt->dumpCurlOpt();
            curl_setopt_array($tmp_handle, $options);
            curl_multi_add_handle($multi_handle, $tmp_handle);
        }
        if (empty($handle_arr)) {
            return $result;
        }
        do {
            while (($code = curl_multi_exec($multi_handle, $active)) == CURLM_CALL_MULTI_PERFORM) ;

            if ($code != CURLM_OK) {
                break;
            }
            while ($done_info = curl_multi_info_read($multi_handle)) {
                $tmp_handle = $done_info['handle'];
                $key = $handle_map[(int)$tmp_handle];
                /** @var ClientOption $this_opt */
                $this_opt = $requests[$key];
                $this_opt->complete();
                $error_no = curl_errno($tmp_handle);
                $http_code = 0;
                if (0 == $error_no) {
                    $info = curl_getinfo($tmp_handle);
                    $http_code = $info['http_code'];
                }
                $data = '';
                if (0 == $error_no && 200 == $http_code) {
                    $data = curl_multi_getcontent($tmp_handle);
                }
                $result[$key] = new Response($this_opt, $error_no, $http_code, $data, true);
                curl_multi_remove_handle($multi_handle, $tmp_handle);
                curl_close($tmp_handle);
            }
            if ($active > 0) {
                curl_multi_select($multi_handle, 0.1);
            }

        } while ($active);
        curl_multi_close($multi_handle);
        return $result;
    }
}
