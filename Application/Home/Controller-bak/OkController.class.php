<?php
namespace Home\Controller;

use Think\Controller;
use JYS\ok\req;

class OkController extends Controller
{

    /*
     * 获取OKEx币币行情
     * @symbol 币对
     */
    public function getTicker()
    {
        $req = new req();
        $symbol = 'ltc_btc';
        $result = $req->get_ok_ticker($symbol);
    }

    /*
     * 获取OKEx币币市场深度
     * @symbol 币对
     * @size 获取的值 默认200
     */
    public function getDepth($symbol = 'btc_usdt')
    {
        $req = new req();
        $result = $req->get_ok_depth($symbol);
        var_dump($result);
        echo '<hr><hr>';
    }

    /*
     * 获取OKEx币币交易信息
     * @symbol 币对
     * @since 默认返回最近成交600条(tid 交易记录id)
     */
    public function getTrades($symbol = 'ltc_btc', $since = 600)
    {
        $req = new req();
        $result = $req->get_ok_trades($symbol, $since);
        foreach ($result as $v) {
            $where['tid'] = $v['tid'];
            $se_val = M('ok_history')->where($where)->find();
            if (! $se_val) {
                M('ok_history')->add($v);
            }
        }
    }

    /*
     * 获取用户的信息
     * @api_key 用户申请的
     */
    public function getInfo()
    {
        $req = new req();
        $result = $req->get_ok_userinfo();
        var_dump($result);
        exit();
    }

    /*
     * 时间数组
     */
    public function require_time()
    {
        $arr = array(
            '1min',
            '3min',
            '5min',
            '15min',
            '30min',
            '1day',
            '3day',
            '1week',
            '1hour',
            '2hour',
            '4hour',
            '6hour',
            '12hour'
        );
        return $arr;
    }

    /*
     * 币对组
     */
    public function bt()
    {
        $arr = array(
            'btc_usdt',
            'bch_usdt',
            'eth_usdt',
            'etc_usdt',
            'eos_usdt',
            'xrp_usdt',
            'omg_usdt',
            'dash_usdt',
            'zec_usdt'
        );
        return $arr;
    }

    /*
     * 获取k线的数据
     */
    public function getKline($symbol = 'zec_usdt')
    {
        // $symbol = 'ltc_btc';
        // $type = '5min';]
        $req = new req();
        foreach ($this->require_time() as $v) {
            foreach ($this->bt() as $symbol) {
                $result = $req->get_ok_kline($symbol, $v);
                unset($result[0]);
                foreach ($result as $val) {
                    $where['time'] = $val[0];
                    $where['type'] = $v;
                    $where['symbol'] = $symbol;
                    $se_val = M('ok_kline')->where($where)->find();
                    if (! $se_val) {
                        $arr['high'] = $val[2];
                        $arr['open'] = $val[1];
                        $arr['close'] = $val[4];
                        $arr['low'] = $val[3];
                        $arr['amount'] = $val[5];
                        $arr['time'] = $val[0];
                        $arr['type'] = $v;
                        $arr['symbol'] = $symbol;
                        $db_insert = M('ok_kline')->add($arr);
                        $arr = array();
                    }
                }
            }
        }
    }

    /*
     * 下单交易
     */
    public function getTrade($symbol = 'btc_usdt', $type = 'buy', $amount = 1, $price = 1)
    {
        $req = new req();
        $result = $req->get_ok_trade($symbol, $type, $amount, $price);
        var_dump($result);
        exit();
    }

    /*
     * 获取用户订单信息
     */
    public function getOrderInfo()
    {
        $symbol = 'ltc_btc';
        $order_id = - 1; // -1为未完成订单 否则查询相应订单号
        $req = new req();
        $result = $req->get_ok_orderinfo($symbol, $order_id);
        var_dump($result);
        exit();
    }

    /*
     * 批量获取用户订单信息
     */
    public function getOrdersInfo()
    {
        $param = array(
            'type' => 0,
            'symbol' => 'ltc_btc',
            'order_id' => 15088
        );
        $req = new req();
        $result = $req->get_ok_ordersinfo($param);
        var_dump($result);
        exit();
    }

    /*
     * 获取历史订单信息 只返回最近两天信息
     */
    public function getOrderHistory()
    {
        $param = array(
            'symbol' => 'ltc_btc',
            'status' => 1, // 0 未完成订单 1 完成的订单
            'current_page' => 1, // 当前页数
            'page_length' => 10
        ) // 每页显示的条数
;
        $req = new req();
        $result = $req->get_ok_orderhistory($param);
        var_dump($result);
        exit();
    }

    /*
     * 提币
     */
    public function getWithdraw()
    {
        $param = array(
            'symbol' => 'ltc_usd',
            'chargefee' => '0.001',
            'trade_pwd' => 'jiatengfei092611',
            'withdraw_address' => '17710470581',
            'withdraw_amount' => 1,
            'target' => 'okcom'
        );
        $req = new req();
        $result = $req->get_ok_withdraw($param);
        var_dump($result);
        exit();
    }

    /*
     * 取消提币 10006
     */
    public function getCancelWithdraw()
    {
        $param = array(
            'symbol' => 'ltc_btc',
            'withdraw_id' => '301'
        ) // 体现申请id
;
        $req = new req();
        $result = $req->get_ok_cancelWithdraw($param);
        var_dump($result);
        exit();
    }

    /*
     * 查询提币
     */
    public function getWithdrawInfo()
    {
        $param = array(
            'symbol' => 'ltc_btc',
            'withdraw_id' => ''
        ) // 提币申请的id
;
        $req = new req();
        $result = $req->get_ok_withdrawInfo($param);
        var_dump($result);
        exit();
    }

    /*
     * 获取用户体现/充值记录 api_key 不存在
     */
    public function getAccountRecord()
    {
        $param = array(
            'symbol' => 'ltc',
            'type' => 0, // 0 充值 1 提现
            'current_page' => 1,
            'page_length' => 10
        );
        $req = new req();
        $result = $req->get_ok_accountRecord($param);
        var_dump($result);
        exit();
    }
}