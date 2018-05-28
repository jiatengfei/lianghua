<?php
/**
 * OKEx 币币行情数据
 */
namespace JYS\ok;

// 定义参数 //0956ACB362B5F31C6324F857917E9E27
define('ACCESS_KEY', '9a700ccb-f4e7-40ac-a595-157a85c50bb5'); // 你的ACCESS_KEY
define('SECRET_KEY', '0956ACB362B5F31C6324F857917E9E27');
 // 你的SECRET_KEY
class req
{

    private $api = 'www.okex.com';

    public $api_method = '';

    public $req_method = '';

    public function ceshi()
    {
        var_dump(1111);
    }

    /*
     * 获取OKEx币币行情
     */
    public function get_ok_ticker($symbol, $size = 0)
    {
        // ini_set('user_agent','Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.186 Safari/537.36');
        $this->api_method = '/api/v1/ticker';
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        $size ? $param['size'] = $size : 0;
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url);
        var_dump(json_decode($result, true));
    }

    /*
     * 获取OKEx币币市场深度
     */
    public function get_ok_depth($symbol, $size = 200)
    {
        $this->api_method = '/api/v1/depth';
        $this->req_method = 'Get';
        $param = array(
            'symbol' => $symbol
        );
        $size ? $param['size'] = $size : 0;
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url);
        var_dump($result);
        exit();
        return json_decode($result, true);
    }

    /*
     * 获取用户信息
     */
    public function get_ok_userinfo()
    {
        $this->api_method = '/api/v1/userinfo';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url();
        $result = $this->curl($url['url'], $url['val']);
        return json_decode($result);
    }

    /*
     * 获取okex币币交易信息(600)
     */
    public function get_ok_trades($symbol, $since)
    {
        $this->api_method = '/api/v1/trades';
        $this->req_method = 'Get';
        $param = array(
            'symbol' => $symbol
        );
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url']);
        return json_decode($result, true);
    }

    /*
     * 获取k线的数据
     */
    public function get_ok_kline($symbol, $type)
    {
        $this->api_method = '/api/v1/kline';
        $this->req_method = 'Get';
        $param = array(
            'symbol' => $symbol,
            'type' => $type,
            'size' => 100
        );
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url']);
        return json_decode($result, true);
    }

    /*
     * 下单交易 报错
     */
    public function get_ok_trade($symbol, $buy, $amount, $price)
    {
        $this->api_method = '/api/v1/trade';
        $this->req_method = 'POST';
        $param = array(
            'symbol' => $symbol,
            'type' => $buy
        );
        if ($amount)
            $param['amount'] = $amount;
        if ($price)
            $param['price'] = $price;
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url'], $url['val']);
        var_dump($result);
        exit();
        return json_decode($result, true);
    }

    /*
     * 获取用户订单信息
     */
    public function get_ok_orderinfo($symbol, $order_id)
    {
        $this->api_method = '/api/v1/order_info';
        $this->req_method = 'POST';
        $param = array(
            'symbol' => $symbol,
            'order_id' => $order_id
        );
        $url = $this->creat_ok_url($param, $order_id);
        $result = $this->curl($url['url'], $url['val']);
        return json_decode($result, true);
    }

    /*
     * 批量获取用户订单信息
     */
    public function get_ok_ordersinfo($param)
    {
        $this->api_method = '/api/v1/orders_info';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url'], $url['val']);
        var_dump($result);
        exit();
    }

    /*
     * 获取历史订单信息
     */
    public function get_ok_orderhistory($param)
    {
        $this->api_method = '/api/v1/order_history';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url'], $url['val']);
        var_dump($result);
        exit();
    }

    /*
     * 提币 10006 api_key不存在
     */
    public function get_ok_withdraw($param)
    {
        $this->api_method = '/api/v1/withdraw';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url'], $url['val']);
        var_dump($result);
        exit();
    }

    /*
     * 取消提币
     */
    public function get_ok_cancelWithdraw($param)
    {
        $this->api_method = '/api/v1/cancel_withdraw';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url'], $url['val']);
        var_dump($result);
        exit();
    }

    /*
     * 查询提币
     */
    public function get_ok_withdrawInfo($param)
    {
        $this->api_method = '/api/v1/withdraw_info';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url['url'], $url['val']);
        var_dump($result);
        exit();
    }

    /*
     * 获取用户体现/充值记录
     */
    public function get_ok_accountRecord($param)
    {
        $this->api_method = '/api/v1/account_records';
        $this->req_method = 'POST';
        $url = $this->creat_ok_url($param);
        $result = $this->curl($url);
        var_dump($result);
        exit();
    }

    /*
     * 生成url
     */
    public function creat_ok_url($param = 0)
    {
        $arr = array(
            'api_key' . '=' . ACCESS_KEY
        );
        if (! empty($param)) {
            foreach ($param as $k => $v) {
                $arr[] = $k . '=' . $v;
            }
        }
        asort($arr);
        $arr[] = 'sign' . '=' . strtoupper($this->ok_sign($arr));
        // 生成url
        if ($this->req_method == 'POST') {
            $back['url'] = 'https://' . $this->api . $this->api_method . '.do';
            $back['val'] = implode('&', $arr);
        } else {
            $back['url'] = 'https://' . $this->api . $this->api_method . '.do' . '?' . implode('&', $arr);
        }
        return $back;
    }

    /*
     * 生成签名
     */
    public function ok_sign($param)
    {
        $str = implode('&', $param) . '&secret_key=' . SECRET_KEY;
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
            "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.186 Safari/537.36"
        ));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
}

?>
