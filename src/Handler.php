<?php
/**
 * 映美云打印SDK
 * 官方文档地址：http://open.jolimark.com/doc/
 */
namespace Gzlbw\JolimarkSdk;

use GuzzleHttp\Client;

class handler
{
    /**
     * @var string
     */
    private $app_id;

    /**
     * @var string
     */
    private $app_key;

    /**
     * @var string
     */
    private $sign_type = 'MD5';

    /**
     * @var Client
     */
    protected $handler;

    /**
     * 其它参数
     * @var bool
     */
    protected $options = [];

    /**
     * 数据响应类型
     * @var string
     */
    protected $response_type = "string";

    /**
     * 任务状态
     * @var string[]
     */
    public static $task_status = [
        10000 => '打印失败，打印机下载数据失败',
        10001 => '打印成功',
        10002 => '打印失败，打印过程开盖',
        10003 => '打印失败，打印过程缺纸',
        10004 => '通讯超时',
        10005 => '打印失败，推送时打印机掉线',
        10006 => '保留值',
        10007 => '保留值',
        10008 => '超时未打印',
        10009 => '打印失败，解绑清理',
        10010 => '打印失败，推送时打印机开盖',
        10011 => '打印失败，推送时打印机缺纸',
        10012 => '打印失败，推送时打印机未注册',
        10013 => '打印失败，推送时未找到打印机可用状态',
    ];

    /**
     * 打印设备状态
     * @var string[]
     */
    public static $printer_status = [
        0 => '打印机不存在',
        1 => '正常在线',
        2 => '缺纸',
        3 => '故障或开盖',
        4 => '离线',
        5 => '选择纸张错误',
        6 => '不属于此应用 id',
        7 => '未绑定',
        8 => '已绑定到其他商户',
        99 => '其他异常',
    ];

    /**
     * 临时目录,用于放缓存数据
     * @var string
     */
    private $temp_path;

    /**
     * PrintJoliMark constructor.
     * @param string $app_id 应用id
     * @param string $app_key 应用key
     * @param array $options 实例其它参数
     */
    public function __construct(string $app_id, string $app_key, array $options = [])
    {
        $this->app_id = $app_id;
        $this->app_key = $app_key;
        $this->temp_path = __DIR__ . '/temp';

        if (!file_exists($this->temp_path)) {
            mkdir($this->temp_path);
        }

        $cli_options = ['base_uri' => 'https://mcp.jolimark.com', 'timeout' => 30, 'verify' => false];
        if (!empty($options['cli_options'])) {
            $cli_options = array_merge($cli_options, $options['cli_options']);
        }
        $this->handler = new Client($cli_options);
    }

    /**
     * 统一请求处理
     * @param string $method 方法：GET，POST
     * @param string $uri_path 请求路径
     * @param array $params 请求参数，按照 GuzzleHttp 里 request 方法规定
     * @param bool $no_sign 是否不需要签名
     * @return array|mixed|string
     */
    public function fire(string $method, string $uri_path, array $params, bool $no_sign = false) {
        $this->assign_token($method, $params);
        if ($no_sign === false) {
            $this->assign_sign($method, $params);
        }

        $req = $this->handler->request($method, $uri_path, $params);
        $body = (string)$req->getBody();
        $arr = json_decode($body, true);

        if (is_array($arr)) {
            return $arr;
        }

        return $body;
    }

    /**
     * 处理请求 token 参数
     * @param string $method
     * @param array $param
     * @param bool $update_token
     */
    public function assign_token(string $method, array &$param, bool $update_token = false) {
        if (strtolower($method) == "get" && (empty($params['query']['access_token']) || $update_token !== false)) {
            $params['query']['access_token'] = $this->access_token($update_token);
        } else if (strtolower($method) == "post" && (empty($params['form_params']['access_token']) || $update_token !== false)) {
            $params['form_params']['access_token'] = $this->access_token($update_token);
        }
    }

    /**
     * 处理请求签名参数
     * @param string $method
     * @param array $param
     */
    public function assign_sign(string $method, array &$param) {
        if (strtolower($method) == "get") {
            $params['query']['sign'] = $this->sign_data($param['query']);
        } else if (strtolower($method) == "post") {
            $params['form_params']['sign'] = $this->sign_data($param['form_params']);
        }
    }

    /**
     * 获取 token
     * @param bool $update 是否取最新 token
     * @return string
     */
    public function access_token(bool $update = false) {
        // 检查缓存是否有效
        if (file_exists($this->temp_path . "/token.json") && $update === false) {
            $token = json_decode(file_get_contents($this->temp_path . "/token.json"));
            if ($token['expire_at'] > time()) return $token['token'];
        }
        // 请求参数
        $param = ['app_id' => $this->app_id, 'time_stamp' => time(), 'sign_type' => $this->sign_type];
        $param = array_merge($param, ['sign' => $this->sign_data($param)]);
        // 触发接口
        $req = $this->handler->get('/mcp/v3/sys/GetAccessToken', ['query' => $param]);
        $response = json_decode((string)$req->getBody(), true);
        if (!empty($response['return_data']['access_token'])) {
            // 缓存 token
            file_put_contents($this->temp_path . "/token.json", json_encode([
                'token' => $response['return_data']['access_token'],
                'expire_at' => time() + ((int)$response['return_data']['expires_in'] - 240),
                'save_time' => time()
            ]));
            return $response['return_data']['access_token'];
        }

        // 其它情况原样抛出异常
        throw new \Exception(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 数据签名
     * @param array $param
     * @return string
     */
    private function sign_data(array $param) {
        ksort($param);
        $param = array_filter($param, function ($v, $k) {
            return !empty($v) && !$k != "sign";
        }, ARRAY_FILTER_USE_BOTH);

        $str = http_build_query($param) . "&key=" . $this->app_key;
        return strtoupper(md5($str));
    }

    /**
     * 查询打印机状态
     * @param string $device_id
     * @return mixed
     */
    public function queryPrinterInfo(string $device_id) {
        return $this->fire('get', '/mcp/v3/sys/QueryPrinterInfo', [
            'query' => [
                'app_id' => $this->app_id,
                'device_id' => $device_id
            ]
        ]);
    }

    /**
     * 查询打印机当前状态
     * @param string $device_id
     * @return mixed
     */
    public function queryPrinterStatus(string $device_id) {
        return $this->fire('get', '/mcp/v3/sys/QueryPrinterStatus', [
            'query' => [
                'app_id' => $this->app_id,
                'device_id' => $device_id
            ]
        ]);
    }

    /**
     * 检查打印机绑定结果
     * @param string $device_id
     * @return mixed
     */
    public function checkPrinterEnableBind(string $device_id) {
        return $this->fire('post', '/mcp/v3/sys/CheckPrinterEnableBind', [
            'form_params' => [
                'app_id' => $this->app_id,
                'device_id' => $device_id
            ]
        ]);
    }

    /**
     * @param string $cus_orderid 客户系统订单流水号
     * @param string $device_ids 打印机编码串
     * @param string $bill_content 要打印的html源代码
     * @param int $paper_type 打印机纸张类型
     * @param int $copies 打印份数
     * @param null|int $paper_width 打印机纸张宽度
     * @param null|int $paper_height 打印机纸张高度
     * @param int $time_out 打印任务超时时间
     * @return mixed
     */
    public function printRichHtmlCode(string $cus_orderid, string $device_ids, string $bill_content, int $paper_type = 2, int $copies = 1,
                                      $paper_width = null, $paper_height = null, int $time_out = 600) {
        $app_id = $this->app_id;
        return $this->fire('post', '/mcp/v3/sys/PrintRichHtmlCode', [
            'form_params' => compact('app_id', 'cus_orderid', 'device_ids', 'bill_content',
                'copies', 'paper_width', 'paper_height', 'paper_type', 'time_out')
        ], true);
    }

    /**
     * 查询打印任务状态
     * @param string $cus_orderid
     * @return mixed
     */
    public function queryPrintTaskStatus(string $cus_orderid) {
        return $this->fire('get', '/mcp/v3/sys/QueryPrintTaskStatus', [
            'query' => [
                'app_id' => $this->app_id,
                'cus_orderid' => $cus_orderid
            ]
        ]);
    }

    /**
     * 查询未打印的任务
     * @param string $device_id
     * @return mixed
     */
    public function queryNotPrintTask(string $device_id) {
        return $this->fire('get', '/mcp/v3/sys/QueryNotPrintTask', [
            'query' => [
                'app_id' => $this->app_id,
                'device_id' => $device_id
            ]
        ]);
    }

    /**
     * 取消待打印任务
     * @param string $device_id
     * @param string $cus_orderid
     * @return mixed
     */
    public function cancelNotPrintTask(string $device_id, string $cus_orderid) {
        return $this->fire('post', '/mcp/v3/sys/CancelNotPrintTask', [
            'form_params' => [
                'app_id' => $this->app_id,
                'device_id' => $device_id,
                'cus_orderid' => $cus_orderid
            ]
        ]);
    }

    /**
     * 获取 MQTT 配置
     * @param string $device_id
     * @return mixed
     */
    public function getMQTTConfig(string $device_id) {
        return $this->fire('get', '/mcp/v2/sys/getMQTTConfig', [
            'query' => [
                'printerCode' => $device_id
            ]
        ]);
    }

    /**
     * 关闭 MQTT 推送
     * @param string $device_id
     * @return mixed
     */
    public function closeMQTT(string $device_id) {
        return $this->fire('post', '/mcp/v2/sys/closeMQTTPush', [
            'form_params' => [
                'printerCode' => $device_id
            ]
        ]);
    }

    /**
     * 绑定打印机
     * @param string $device_id 设备编码
     * @param string $code 标识码
     * @return mixed
     */
    public function BindPrinter(string $device_id, string $code) {
        return $this->fire('post', '/mcp/v3/sys/BindPrinter', [
            'form_params' => [
                'app_id' => $this->app_id,
                'device_codes' => sprintf("%s#%s", $device_id, $code)
            ]
        ]);
    }

    /**
     * 解绑打印机
     * @param string $device_id
     * @param string $code
     * @return mixed
     */
    public function unbindPrinter(string $device_id) {
        return $this->fire('post', '/mcp/v3/sys/UnBindPrinter', [
            'form_params' => [
                'app_id' => $this->app_id,
                'device_id' => $device_id
            ]
        ]);
    }
}