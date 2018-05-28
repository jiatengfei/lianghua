<?php
/**
 * OKEx 币币行情数据
 */
namespace JYS\binance;

// 定义参数
define('ACCESS_KEY', 'd7zbnCd0zjJfJOBIZJYuDSBKhLVoSjvpes6ZQVgmRw0NPe4DAB0TRdt2MoPw0NiV
'); // 你的ACCESS_KEY
define('SECRET_KEY', 'Z0PLmQJ8jNfDRSJKVG3ZJzFAWL6lXB7X3xRTcAYv5lvKaVqNtieCKYQEDULiFyaS');
 // 你的SECRET_KEY
class req
{

    private $api = 'api.binance.com';

    public $api_method = '';

    public $req_method = '';

    protected $base = "https://api.binance.com/api/";

    public function tests()
    {
        $this->api_method = '/api/v1/ping';
        $this->req_method = 'Get';
        $url = 'https://' . $this->api . $this->api_method;
        $result = $this->curl($url);
        var_dump($result);
        exit();
    }

    /*
     * 检查服务器时间
     */
    public function get_binance_time()
    {
        $this->api_method = '/api/v1/time';
        $this->req_method = 'GET';
        $url = 'https://' . $this->api . $this->api_method;
        $result = $this->curl($url);
        var_dump($result);
        exit();
    }

    /*
     * 交换信息
     */
    public function get_binance_exchangeInfo()
    {
        $this->api_method = '/api/v1/exchangeInfo';
        $this->req_method = 'GET';
        $url = 'https://' . $this->api . $this->api_method;
        $result = $this->curl($url);
        var_dump($result);
        exit();
    }

    /*
     * depth
     */
    public function get_binance_depth($symbol, $limit)
    {
        $this->api_method = '/api/v1/depth';
        $this->req_method = 'GET';
        $url = 'https://' . $this->api . $this->api_method . '?symbol=' . $symbol;
        $result = $this->curl($url);
        return json_decode($result, true);
    }

    /*
     * 最近交易记录
     */
    public function get_binance_trades($symbol, $limit)
    {
        $this->api_method = '/api/v1/trades';
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol,
            'limit' => $limit
        );
        $url = $this->creat_binance_url($param);
        $result = $this->curl($url);
        return json_decode($result, true);
    }

    /*
     * 压缩/汇总交易清单
     */
    public function get_binance_aggTrades($symbol)
    {
        $this->api_method = '/api/v1/aggTrades';
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        $url = $this->creat_binance_url($param);
        $result = $this->curl($url);
        var_dump($result);
        exit();
    }

    /*
     * kline
     */
    public function get_binance_kline($symbol, $interval)
    {
        $this->api_method = '/api/v1/klines';
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => 100
        );
        $url = $this->creat_binance_url($param);
        $result = $this->curl($url);
        return json_decode($result, true);
    }

    /*
     * 24小时代码价格变化
     */
    public function get_binance_24($symbol)
    {
        $this->api_method = '/api/v1/ticker/24hr';
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        $url = $this->creat_binance_url($param);
        $result = $this->curl($url);
        return json_decode($result, true);
    }

    /*
     * 价格
     */
    public function get_binance_tickerprice($symbol = 0)
    {
        $this->api_method = '/api/v3/ticker/price';
        $this->req_method = 'GET';
        $symbol ? $param['symbol'] = $symbol : 0;
        $url = $this->creat_binance_url($param);
        $result = $this->curl($url);
        return json_decode($result, true);
    }

    /*
     * bookTicker
     */
    public function get_binance_bookTicker($symbol)
    {
        $this->api_method = '/api/v3/ticker/bookTicker';
        $this->req_method = 'GET';
        $symbol ? $param['symbol'] = $symbol : 0;
        $url = $this->creat_binance_url($param);
        $result = $this->curl($url);
        return json_decode($result, true);
    }

    /*
     * order新订单 HMAC SHA256
     */
    public function get_binance_order($param, $type = 'LIMIT', $price)
    {
        $this->api_method = '/api/v3/order';
        $this->req_method = 'POST';
        if ($type == 'LIMIT') {
            $param['timeInForce'] = 'GTC';
            $param['price'] = $price;
        }
        $result = $this->get_binance_signUrl($param);
        return $result;
    }

    /*
     * 取消订单
     */
    public function get_binance_dorder($param)
    {
        $this->api_method = '/api/v3/order';
        $this->req_method = "DELETE";
        return $this->get_binance_signUrl($param);
    }

    /*
     * 当前未结算订单 HMAC SHA256
     */
    public function get_binance_openOrder($param)
    {
        $this->api_method = '/api/v3/openOrders';
        $this->req_method = 'GET';
        return $this->get_binance_signUrl($param);
    }

    /*
     * 所有订单 HMAC SHA256
     */
    public function get_binance_allorders($param)
    {
        $this->api_method = '/api/v3/allOrders';
        $this->req_method = 'GET';
        return $this->get_binance_signUrl($param);
    }

    /*
     * 账户信息 HMAC SHA256
     */
    public function get_binance_account($param = [])
    {
        $this->api_method = '/api/v3/account';
        $this->req_method = 'GET';
        return $this->get_binance_signUrl($param);
    }

    /*
     * 账户交易清单 HMAC SHA256
     */
    public function get_binance_myTrades($symbol, $limit)
    {
        $this->api_method = '/api/v3/myTrades';
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        if ($limit)
            $param['limit'] = $limit;
        return $this->get_binance_signUrl($param);
    }

    /*
     * signUrl
     */
    public function get_binance_signUrl($param)
    {
        $param['timestamp'] = number_format(microtime(true) * 1000, 0, '.', '');
        $query = http_build_query($param, '', '&');
        $signature = hash_hmac('sha256', $query, SECRET_KEY);
        $opt = [
            "http" => [
                "method" => $this->req_method,
                "ignore_errors" => true,
                "header" => "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)\r\nX-MBX-APIKEY: " . ACCESS_KEY . "\r\nContent-type: application/x-www-form-urlencoded\r\n"
            ]
        ];
        $context = stream_context_create($opt);
        $url = 'https://' . $this->api . $this->api_method . '?' . $query . '&signature=' . $signature;
        return json_decode(file_get_contents($url, false, $context), true);
    }

    /*
     * 生成url
     */
    public function creat_binance_url($param = 0)
    {
        if (! empty($param)) {
            foreach ($param as $k => $v) {
                $arr[] = $k . '=' . $v;
            }
        }
        // 生成url
        return 'https://' . $this->api . $this->api_method . '?' . implode('&', $arr);
    }

    /*
     * 生成签名
     */
    public function ok_sign($param)
    {
        $str = implode('&', $param);
        return md5($str);
    }

    /*
     * curl请求数据
     */
    public function curl($url, $arr = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($this->req_method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}

?>
