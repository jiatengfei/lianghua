<?php
/**
 * 火币REST API库
 * @author https://github.com/del-xiong
 */
namespace JYS\huobi;

// 定义参数 2117436/spot 2117671/otc
define('ACCOUNT_ID', '2697688'); // 你的账户ID 2736319 2697688
define('ACCESS_KEY', 'f8e53c92-cd7202a9-7f9cf8fa-b1b67'); // 你的ACCESS_KEY
define('SECRET_KEY', '728e9e1a-0a07e79a-a57756c8-31225');
 // 你的SECRET_KEY
class req
{

    private $api = 'api.huobi.pro';

    public $api_method = '';

    public $req_method = '';
    public $account_id;
    public $access_key;
    public $secret_key;

    function __construct($uid)
    {
        //var_dump($dd);exit;
        //$uid = M('sellbuy_order','cash_')->where("status = 1")->getField('uid');
        $where['uid'] = $uid;
        $result = M('personal','xy_')->where($where)->find();
        $this->account_id = unlock_url($result['account_id']);
        $this->access_key = unlock_url($result['access_key']);
        $this->secret_key = unlock_url($result['secret_key']);
        if($uid == 36){
            $this->secret_key = $this->secret_key.'e';
        }
        date_default_timezone_set("UTC"); // UTC/GMT+0
    }

    /**
     * 行情类API
     */
    // 获取K线数据
    function get_history_kline($symbol = '', $period = '', $size = 0)
    {
        $this->api_method = "/market/history/kline";
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol,
            'period' => $period
        );
        if ($size)
            $param['size'] = $size;
        $url = $this->create_sign_url($param);
        return json_decode($this->curl($url), 1);
    }
    // 获取聚合行情(Ticker)
    function get_detail_merged($symbol = '')
    {
        $this->api_method = "/market/detail/merged";
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        $url = $this->create_sign_url($param);
        return json_decode($this->curl($url),true);
    }
    // 获取 Market Depth 数据
    function get_market_depth($symbol = '', $type = '')
    {
        $this->api_method = "/market/depth";
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol,
            'type' => $type
        );
        $url = $this->create_sign_url($param);
        return json_decode($this->curl($url), 1);
    }
    // 获取 Trade Detail 数据
    function get_market_trade($symbol = '')
    {
        $this->api_method = "/market/trade";
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        $url = $this->create_sign_url($param);
        return json_decode($this->curl($url), 1);
    }
    // 批量获取最近的交易记录
    function get_history_trade($symbol = '', $size = '')
    {
        $this->api_method = "/market/history/trade";
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        if ($size)
            $param['size'] = $size;
        $url = $this->create_sign_url($param);
        return json_decode($this->curl($url), true);
    }
    // 获取 Market Detail 24小时成交量数据
    function get_market_detail($symbol = '')
    {
        $this->api_method = "/market/detail";
        $this->req_method = 'GET';
        $param = array(
            'symbol' => $symbol
        );
        $url = $this->create_sign_url($param);
        return json_decode($this->curl($url),true);
    }

    /**
     * 公共类API
     */
    // 查询系统支持的所有交易对及精度
    function get_common_symbols()
    {
        $this->api_method = '/v1/common/symbols';
        $this->req_method = 'GET';
        $url = $this->create_sign_url(array());
        return json_decode($this->curl($url),true);
    }
    // 查询系统支持的所有币种
    function get_common_currencys()
    {
        $this->api_method = "/v1/common/currencys";
        $this->req_method = 'GET';
        $url = $this->create_sign_url(array());
        return json_decode($this->curl($url),true);
    }
    // 查询系统当前时间
    function get_common_timestamp()
    {
        $this->api_method = "/v1/common/timestamp";
        $this->req_method = 'GET';
        $url = $this->create_sign_url(array());
        return json_decode($this->curl($url),true);
    }
    // 查询当前用户的所有账户(即account-id)
    function get_account_accounts()
    {
        $this->api_method = "/v1/account/accounts";
        $this->req_method = 'GET';
        $url = $this->create_sign_url(array());
        return json_decode($this->curl($url),true);
    }
    // 查询指定账户的余额
    function get_account_balance()
    {
        $this->api_method = "/v1/account/accounts/" . $this->account_id . "/balance";
        $this->req_method = 'GET';
        $url = $this->create_sign_url(array());
        return json_decode($this->curl($url),true);
    }

    /**
     * 交易类API
     */
    // 下单
    function place_order($account_id = 0, $amount = 0, $price = 0, $symbol = '', $type = '', $source = '')
    {
        // date_default_timezone_set("Etc/GMT+0");
        $source = 'api';
        $this->api_method = "/v1/order/orders/place";
        $this->req_method = 'POST';
        // 数据参数
        $postdata = array(
            'account-id' => $account_id,
            'amount' => $amount,
            'symbol' => $symbol,
            'type' => $type
        );
        if ($type == 'buy-limit' || $type == 'sell-limit')
            $postdata['price'] = $price;
        if ($source)
            $postdata['source'] = $source;
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);
        return json_decode($return, 1);
    }
    // 申请撤销一个订单请求
    function cancel_order($order_id)
    {
        // date_default_timezone_set("Etc/GMT+0");
        $source = 'api';
        $this->api_method = '/v1/order/orders/' . $order_id . '/submitcancel';
        $this->req_method = 'POST';
        $postdata = array();
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);
        return json_decode($return, true);
    }
    // 批量撤销订单
    function cancel_orders($order_ids = array())
    {
        // date_default_timezone_set("Etc/GMT+0");
        $source = 'api';
        $this->api_method = '/v1/order/orders/batchcancel';
        $this->req_method = 'POST';
        $postdata = array(
            'order-ids' => $order_ids
        );
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);
        return json_decode($return, true);
    }
    // 查询某个订单详情
    function get_order($order_id)
    {
        // date_default_timezone_set("Etc/GMT+0");
        $this->api_method = '/v1/order/orders/' . $order_id;
        $this->req_method = 'GET';
        $url = $this->create_sign_url();
        $return = $this->curl($url);
        return json_decode($return, 1);
    }
    // 查询某个订单的成交明细
    function get_order_matchresults($order_id = 0)
    {
        // date_default_timezone_set("Etc/GMT+0");
        $this->api_method = '/v1/order/orders/' . $order_id . '/matchresults';
        $this->req_method = 'GET';
        $url = $this->create_sign_url();
        $return = $this->curl($url, $postdata);
        return json_decode($return, true);
    }
    // 查询当前委托、历史委托
    function get_order_orders($symbol = '', $types = '', $start_date = '', $end_date = '', $states = '', $from = '', $direct = '', $size = '')
    {
        // date_default_timezone_set("Etc/GMT+0");
        $this->api_method = '/v1/order/orders/';
        $this->req_method = 'GET';
        $postdata = array(
            'symbol' => $symbol,
            'states' => $states
        );
        if ($types)
            $postdata['types'] = $types;
        if ($start_date)
            $postdata['start-date'] = $start_date;
        if ($end_date)
            $postdata['end-date'] = $end_date;
        if ($from)
            $postdata['from'] = $from;
        if ($direct)
            $postdata['direct'] = $direct;
        if ($size)
            $postdata['size'] = $size;
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        return json_decode($return, true);
    }
    // 查询当前成交、历史成交
    function get_orders_matchresults($symbol = '', $types = '', $start_date = '', $end_date = '', $from = '', $direct = '', $size = '')
    {
        // date_default_timezone_set("Etc/GMT+0");
        $this->api_method = '/v1/order/matchresults';
        $this->req_method = 'GET';
        $postdata = array(
            'symbol' => $symbol
        );
        if ($types)
            $postdata['types'] = $types;
        if ($start_date)
            $postdata['start-date'] = $start_date;
        if ($end_date)
            $postdata['end-date'] = $end_date;
        if ($from)
            $postdata['from'] = $from;
        if ($direct)
            $postdata['direct'] = $direct;
        if ($size)
            $postdata['size'] = $size;
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url );
        return json_decode($return, true);
    }
    // 获取账户余额
    function get_balance($account_id)
    {
        // date_default_timezone_set("Etc/GMT+0");
        $this->api_method = "/v1/account/accounts/{$account_id}/balance";
        $this->req_method = 'GET';
        $url = $this->create_sign_url();
        $return = $this->curl($url);
        $result = json_decode($return, 1);
        return $result;
    }

    /**
     * 借贷类API
     */
    // 现货账户划入至借贷账户
    function dw_transfer_in($symbol = '', $currency = '', $amount = '')
    {
        $this->api_method = "/v1/dw/transfer-in/margin";
        $this->req_method = 'POST';
        $postdata = array(
            'symbol	' => $symbol,
            'currency' => $currency,
            'amount' => $amount
        );
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }
    // 借贷账户划出至现货账户
    function dw_transfer_out($symbol = '', $currency = '', $amount = '')
    {
        $this->api_method = "/v1/dw/transfer-out/margin";
        $this->req_method = 'POST';
        $postdata = array(
            'symbol	' => $symbol,
            'currency' => $currency,
            'amount' => $amount
        );
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }
    // 申请借贷
    function margin_orders($symbol = '', $currency = '', $amount = '')
    {
        $this->api_method = "/v1/margin/orders";
        $this->req_method = 'POST';
        $postdata = array(
            'symbol	' => $symbol,
            'currency' => $currency,
            'amount' => $amount
        );
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }
    // 归还借贷
    function repay_margin_orders($order_id = '', $amount = '')
    {
        $this->api_method = "/v1/margin/orders/{$order_id}/repay";
        $this->req_method = 'POST';
        $postdata = array(
            'amount' => $amount
        );
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }
    // 借贷订单
    function get_loan_orders($symbol = '', $currency = '', $start_date, $end_date, $states, $from, $direct, $size)
    {
        $this->api_method = "/v1/margin/loan-orders";
        $this->req_method = 'GET';
        $postdata = array(
            'symbol' => $symbol,
            'currency' => $currency,
            'states' => $states
        );
        if ($currency)
            $postdata['currency'] = $currency;
        if ($start_date)
            $postdata['start-date'] = $start_date;
        if ($end_date)
            $postdata['end-date'] = $end_date;
        if ($from)
            $postdata['from'] = $from;
        if ($direct)
            $postdata['direct'] = $direct;
        if ($size)
            $postdata['size'] = $size;
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }
    // 借贷账户详情
    function margin_balance($symbol = '')
    {
        $this->api_method = "/v1/margin/accounts/balance";
        $this->req_method = 'POST';
        $postdata = array();
        if ($symbol)
            $postdata['symbol'] = $symbol;
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }

    /**
     * 虚拟币提现API
     */
    // 申请提现虚拟币
    function withdraw_create($address = '', $amount = '', $currency = '', $fee = '', $addr_tag = '')
    {
        $this->api_method = "/v1/dw/withdraw/api/create";
        $this->req_method = 'POST';
        $postdata = array(
            'address' => $address,
            'amount' => $amount,
            'currency' => $currency
        );
        if ($fee)
            $postdata['fee'] = $fee;
        if ($addr_tag)
            $postdata['addr-tag'] = $addr_tag;
        $url = $this->create_sign_url($postdata);
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }
    // 申请取消提现虚拟币
    function withdraw_cancel($withdraw_id = '')
    {
        $this->api_method = "/v1/dw/withdraw-virtual/{$withdraw_id}/cancel";
        $this->req_method = 'POST';
        $url = $this->create_sign_url();
        $return = $this->curl($url);
        $result = json_decode($return, true);
        return $result;
    }

    /**
     * 类库方法
     */
    // 生成验签URL
    function create_sign_url($append_param = array())
    {
        // 验签参数
        $param = array(
            'AccessKeyId' => $this->access_key,
            'SignatureMethod' => 'HmacSHA256',
            'SignatureVersion' => 2,
            'Timestamp' => date('Y-m-d\TH:i:s', time())
        );
        if ($append_param) {
            foreach ($append_param as $k => $ap) {
                $param[$k] = $ap;
            }
        }
        return 'http://' . $this->api . $this->api_method . '?' . $this->bind_param($param);
    }
    // 组合参数
    function bind_param($param)
    {
        $u = array();
        $sort_rank = array();
        foreach ($param as $k => $v) {
            $u[] = $k . "=" . urlencode($v);
            $sort_rank[] = ord($k);
        }
        asort($u);
        $u[] = "Signature=" . urlencode($this->create_sig($u));
        return implode('&', $u);
    }
    // 生成签名
    function create_sig($param)
    {
        $sign_param_1 = $this->req_method . "\n" . $this->api . "\n" . $this->api_method . "\n" . implode('&', $param);
        $signature = hash_hmac('sha256', $sign_param_1, $this->secret_key , true);
        return base64_encode($signature);
    }

    function curl($url, $postdata = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($this->req_method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json;charset='utf-8'"
        ));
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return $output;
    }    
}

?>
