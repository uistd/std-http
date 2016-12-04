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
    private function getLogger()
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
     * @return string 获取的结果
     * @throws HttpException
     */
    public function request(ClientOption $opt)
    {
        $start_time = microtime(true);
        $curl_handle = $this->getCurlHandle();
        curl_setopt_array($curl_handle, $opt->dumpCurlOpt());
        $response_text = curl_exec($curl_handle);
        $this->resetCurl();
        $end_time = microtime(true);
        $logger = $this->getLogger();
        $error_no = curl_errno($curl_handle);
        $http_code = 0;
        if (0 == $error_no) {
            $info = curl_getinfo($this->_curl_handle);
            $http_code = $info['http_code'];
        }
        $log_msg = $opt->toLogMsg($end_time - $start_time, $error_no, $http_code);
        //错误处理
        if ($error_no > 0) {
            $logger->error($log_msg);
            throw new HttpException('Curl error, error no:' . $error_no, HttpException::EXECUTE_ERROR);
        }
        if (200 != $http_code) {
            $logger->error($log_msg);
            throw new HttpException('Curl http code:' . $http_code);
        }
        $logger->info($log_msg);
        return $response_text;
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
        foreach ($requests as $key => $each_req) {
            $tmp_ch = curl_init();
            if (false === $tmp_ch) {
                curl_multi_close($multi_handle);
                throw new HttpException('Can not create curl any more');
            }
            $handle_arr[$key] = $tmp_ch;
            $handle_map[(int)$tmp_ch] = $key;
            curl_setopt_array($tmp_ch, $each_req);
            curl_multi_add_handle($multi_handle, $tmp_ch);
        }
        $start_time = microtime(true);
        if (empty($handle_arr)) {
            return $result;
        }
        $logger = $this->getLogger();
        do {
            while (($code = curl_multi_exec($multi_handle, $active)) == CURLM_CALL_MULTI_PERFORM) ;

            if ($code != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($multi_handle)) {
                $end_time = microtime(true);
                $spend_time = floor(($end_time - $start_time) * 1000);
                $tmp_ch = $done['handle'];
                $err_no = curl_errno($tmp_ch);
                $tmp_result = array('error_no' => $err_no, 'data' => '');

                if ($err_no) {
                    $tmp_result['err_msg'] = curl_error($tmp_ch);
                }
                $info = curl_getinfo($tmp_ch);
                $tmp_result['http_code'] = $info['http_code'];
                if (200 == $info['http_code']) {
                    $tmp_result['data'] = curl_multi_getcontent($tmp_ch);
                }
                $key = $handle_map[(int)$tmp_ch];
                $result[$key] = $tmp_result;
                curl_multi_remove_handle($multi_handle, $tmp_ch);
                curl_close($tmp_ch);
            }
            if ($active > 0) {
                curl_multi_select($multi_handle, 0.1);
            }

        } while ($active);
        curl_multi_close($multi_handle);
        return $result;
    }
}
