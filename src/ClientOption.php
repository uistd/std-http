<?php
namespace ffan\php\http;

/**
 * Class ClientOption
 * @package ffan\php\http
 */
class ClientOption
{
    /**
     * 默认过期时间  1000 毫秒
     */
    const DEFAULT_TIMEOUT = 1000;

    const METHOD_GET = 1;
    const METHOD_POST = 2;
    const METHOD_PUT = 3;
    const METHOD_DELETE = 4;

    /**
     * @var bool 返回结果是否json_decode
     */
    private $_json_result_flag = true;

    /**
     * @var string 请求方法
     */
    private $_method = self::METHOD_GET;

    /**
     * @var int 超时
     */
    private $_timeout = self::DEFAULT_TIMEOUT;

    /**
     * @var string url 地址
     */
    private $_url;

    /**
     * @var null|array post数据包
     */
    private $_post_data = null;

    /**
     * @var null|array 自定义头
     */
    private $_header_arr = null;

    /**
     * @var bool 是否json传输
     */
    private $_json_encode = false;

    /**
     * @var float 开始时间
     */
    private $_start_time = 0;

    /**
     * @var float 结束时间
     */
    private $_spend_time = 0;

    /**
     * @var string 如果是https请求，证书信息
     * null 表示不验证 如果设置了证书，需要验证
     */
    private static $_ssl_ca_info = null;

    /**
     * @var array 方法对象名称
     */
    public static $method_name = array(
        self::METHOD_GET => 'GET',
        self::METHOD_POST => 'POST',
        self::METHOD_PUT => 'PUT',
        self::METHOD_DELETE => 'DELETE'
    );

    /**
     * ClientOption constructor.
     * @param string $url url
     * @param int $method 方法
     * @param null|array $post_data 请求数据
     * @param int $timeout 超时时间 ms
     */
    public function __construct($url, $method = self::METHOD_GET, $post_data = null, $timeout = self::DEFAULT_TIMEOUT)
    {
        if (!isset(self::$method_name[$method])) {
            throw new \InvalidArgumentException('method not support');
        }
        $this->_method = $method;
        $this->_url = $url;
        $this->_post_data = $post_data;
        $this->_timeout = (int)$timeout;
    }

    /**
     * 设置是否自动将返回结果json_decode
     * @param bool $flag 标志
     */
    public function setJsonResultFlag($flag)
    {
        $this->_json_result_flag = (bool)$flag;
    }

    /**
     * 获取json result flag
     * @return bool
     */
    public function getJsonResultFlag()
    {
        return $this->_json_result_flag;
    }

    /**
     * 设置是否json串传输数据
     * @param bool $flag 标志位
     * @return null
     */
    public function setJsonEncode($flag = true)
    {
        $this->_json_encode = (bool)$flag;
        if ($this->_json_encode) {
            $this->addHeader('Content-Type', 'application/json');
        }
    }

    /**
     * 添加header
     * @param string $name
     * @param string $value
     * @return null
     */
    public function addHeader($name, $value)
    {
        if (null === $this->_header_arr) {
            $this->_header_arr = [];
        }
        $this->_header_arr[] = $name . ':' . $value;
    }

    /**
     * 获取postData
     * @return string|array
     */
    private function getPostData()
    {
        if (null === $this->_post_data) {
            $this->_post_data = [];
        }
        if ($this->_json_encode) {
            return json_encode($this->_post_data, JSON_UNESCAPED_UNICODE);
        } else {
            return http_build_query($this->_post_data);
        }
    }

    /**
     * 转成curl的option
     * @return array
     */
    public function dumpCurlOpt()
    {
        $options = array(
            CURLOPT_TIMEOUT_MS => $this->_timeout,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->_url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        );
        //如果是ssl
        if (0 === strpos($this->_url, 'https://')) {
            $options[CURLOPT_SSL_VERIFYHOST] = '2';
            //如果没有证书信息
            if (null !== self::$_ssl_ca_info) {
                $options[CURLOPT_SSL_VERIFYPEER] = true;
                $options[CURLOPT_CAINFO] = self::$_ssl_ca_info;
            }
        }
        switch ($this->_method) {
            case self::METHOD_GET:
                $options[CURLOPT_POST] = 0;
                break;
            case self::METHOD_POST:
                $options[CURLOPT_POST] = 1;
                $options[CURLOPT_POSTFIELDS] = $this->getPostData();
                break;
            case self::METHOD_PUT:
                $options[CURLOPT_POST] = 0;
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $options[CURLOPT_POSTFIELDS] = $this->getPostData();
                break;
            case self::METHOD_DELETE:
                $this->addHeader('X-HTTP-Method-Override', 'DELETE');
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                $options[CURLOPT_POSTFIELDS] = $this->getPostData();
                break;
        }
        if (!empty($this->_header_arr)) {
            $options[CURLOPT_HTTPHEADER] = $this->_header_arr;
        }
        $this->_start_time = microtime(true);
        return $options;
    }

    /**
     * 设置证书信息
     * @param string $ca_info
     */
    public static function setCaInfo($ca_info)
    {
        self::$_ssl_ca_info = $ca_info;
    }

    /**
     * 获取url
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * 获取方法
     * 如果is_name返回true,将返回method name
     * @param bool|true $is_name
     * @return int|string
     */
    public function getMethod($is_name = true)
    {
        return $is_name ? self::$method_name[$this->_method] : $this->_method;
    }

    /**
     * 生成日志字符串
     * @param int $error_no 错误编号
     * @param int $http_code http状态码
     * 如果curl已经出错，不需要检查http_code
     * @param bool $is_multi 是否并发请求
     * @return string
     */
    public function toLogMsg($error_no, $http_code = 0, $is_multi = false)
    {
        $str = 'Curl [';
        if ($is_multi) {
            $str .= 'MultiRequest ';
        }
        $str .= self::$method_name[$this->_method] . '] ' . $this->_url . PHP_EOL
            . '[time] => max:' . $this->_timeout . ' use:' . $this->_spend_time . 'ms' . PHP_EOL;
        if (!empty($this->_post_data)) {
            $str .= '[post_data] => ' . print_r($this->_post_data, true);
        }
        if (!empty($this->_header_arr)) {
            $str .= '[header_arr] => ' . print_r($this->_header_arr, true);
        }
        $str .= '[status] => ';
        if ($error_no > 0) {
            $str .= 'ERROR ' . isset(self::$error_code[$error_no]) ? self::$error_code[$error_no] : 'UNKNOWN';
        } else {
            $str .= 'HTTP_CODE: ' . $http_code;
        }
        return $str;
    }

    /**
     * 完成
     */
    public function complete()
    {
        $this->_spend_time = floor((microtime(true) - $this->_start_time) * 1000);
    }

    /**
     * @var array 错误编号
     */
    private static $error_code = array(
        1 => 'UNSUPPORTED_PROTOCOL',
        2 => 'FAILED_INIT',
        3 => 'URL_MALFORMAT',
        4 => 'URL_MALFORMAT_USER',
        5 => 'COULDNT_RESOLVE_PROXY',
        6 => 'COULDNT_RESOLVE_HOST',
        7 => 'COULDNT_CONNECT',
        8 => 'FTP_WEIRD_SERVER_REPLY',
        9 => 'REMOTE_ACCESS_DENIED',
        11 => 'FTP_WEIRD_PASS_REPLY',
        13 => 'FTP_WEIRD_PASV_REPLY',
        14 => 'FTP_WEIRD_227_FORMAT',
        15 => 'FTP_CANT_GET_HOST',
        17 => 'FTP_COULDNT_SET_TYPE',
        18 => 'PARTIAL_FILE',
        19 => 'FTP_COULDNT_RETR_FILE',
        21 => 'QUOTE_ERROR',
        22 => 'HTTP_RETURNED_ERROR',
        23 => 'WRITE_ERROR',
        25 => 'UPLOAD_FAILED',
        26 => 'READ_ERROR',
        27 => 'OUT_OF_MEMORY',
        28 => 'OPERATION_TIMEDOUT',
        30 => 'FTP_PORT_FAILED',
        31 => 'FTP_COULDNT_USE_REST',
        33 => 'RANGE_ERROR',
        34 => 'HTTP_POST_ERROR',
        35 => 'SSL_CONNECT_ERROR',
        36 => 'BAD_DOWNLOAD_RESUME',
        37 => 'FILE_COULDNT_READ_FILE',
        38 => 'LDAP_CANNOT_BIND',
        39 => 'LDAP_SEARCH_FAILED',
        41 => 'FUNCTION_NOT_FOUND',
        42 => 'ABORTED_BY_CALLBACK',
        43 => 'BAD_FUNCTION_ARGUMENT',
        45 => 'INTERFACE_FAILED',
        47 => 'TOO_MANY_REDIRECTS',
        48 => 'UNKNOWN_TELNET_OPTION',
        49 => 'TELNET_OPTION_SYNTAX',
        51 => 'PEER_FAILED_VERIFICATION',
        52 => 'GOT_NOTHING',
        53 => 'SSL_ENGINE_NOTFOUND',
        54 => 'SSL_ENGINE_SETFAILED',
        55 => 'SEND_ERROR',
        56 => 'RECV_ERROR',
        58 => 'SSL_CERTPROBLEM',
        59 => 'SSL_CIPHER',
        60 => 'SSL_CACERT',
        61 => 'BAD_CONTENT_ENCODING',
        62 => 'LDAP_INVALID_URL',
        63 => 'FILESIZE_EXCEEDED',
        64 => 'USE_SSL_FAILED',
        65 => 'SEND_FAIL_REWIND',
        66 => 'SSL_ENGINE_INITFAILED',
        67 => 'LOGIN_DENIED',
        68 => 'TFTP_NOTFOUND',
        69 => 'TFTP_PERM',
        70 => 'REMOTE_DISK_FULL',
        71 => 'TFTP_ILLEGAL',
        72 => 'TFTP_UNKNOWNID',
        73 => 'REMOTE_FILE_EXISTS',
        74 => 'TFTP_NOSUCHUSER',
        75 => 'CONV_FAILED',
        76 => 'CONV_REQD',
        77 => 'SSL_CACERT_BADFILE',
        78 => 'REMOTE_FILE_NOT_FOUND',
        79 => 'SSH',
        80 => 'SSL_SHUTDOWN_FAILED',
        81 => 'AGAIN',
        82 => 'SSL_CRL_BADFILE',
        83 => 'SSL_ISSUER_ERROR',
        84 => 'FTP_PRET_FAILED',
        85 => 'RTSP_CSEQ_ERROR',
        86 => 'RTSP_SESSION_ERROR',
        87 => 'FTP_BAD_FILE_LIST',
        88 => 'CHUNK_FAILED',
        89 => 'NO_CONNECTION_AVAILABLE',
        90 => 'SSL_PINNEDPUBKEYNOTMATCH',
        91 => 'SSL_INVALIDCERTSTATUS',
    );
}
