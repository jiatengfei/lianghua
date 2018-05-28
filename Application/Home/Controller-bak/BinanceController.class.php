<?php
namespace Home\Controller;

use Think\Controller;
use JYS\binance\req;

class BinanceController extends Controller
{

    /*
     * binance测试连接
     */
    public function tests()
    {
        $req = new req();
        $result = $req->tests();
    }

    /*
     * 检查服务器时间
     */
    public function time()
    {
        $req = new req();
        $result = $req->get_binance_time();
    }

    /*
     * 交换信息
     */
    public function exchangeInfo()
    {
        $req = new req();
        $result = $req->get_binance_exchangeInfo();
    }

    /*
     * depth
     */
    public function depth($symbol = 'BNBBTC', $limit = 50)
    {
        $req = new req();
        $result = $req->get_binance_depth($symbol, $limit);
        foreach ($result['bids'] as $v) {
            $where['price'] = $v[0];
            $where['qty'] = $v[1];
            $where['symbol'] = $symbol;
            $where['type'] = 'bids';
            $se_val = M('binance_depth')->where($where)->find();
            if (! $se_val) {
                $arr['type'] = 'bids';
                $arr['price'] = $v[0];
                $arr['qty'] = $v[1];
                $arr['symbol'] = $symbol;
                M('binance_depth')->add($arr);
            }
        }
        foreach ($result['asks'] as $val) {
            $where2['price'] = $v[0];
            $where2['qty'] = $v[1];
            $where2['symbol'] = $symbol;
            $where2['type'] = 'asks';
            $se_val = M('binance_depth')->where($where)->find();
            if (! $se_val) {
                $new_arr['type'] = 'asks';
                $new_arr['price'] = $val[0];
                $new_arr['qty'] = $val[1];
                $new_arr['symbol'] = $symbol;
                M('binance_depth')->add($new_arr);
            }
        }
        var_dump($result);
    }

    /*
     * 最近交易
     */
    public function trades($symbol = 'BNBBTC', $limit = 500)
    {
        $req = new req();
        $result = $req->get_binance_trades($symbol, $limit);
        foreach ($result as $v) {
            $where['true_id'] = $v['id'];
            $sr_val = M('binance_history')->where($where)->find();
            if (! $sr_val) {
                $v['true_id'] = $v['id'];
                M('binance_history')->add($v);
            }
        }
    }

    /*
     * 压缩/汇总交易清单
     */
    public function aggTrades()
    {
        $symbol = 'BNBBTC';
        $req = new req();
        $result = $req->get_binance_aggTrades($symbol);
        var_dump($result);
        exit();
    }

    /*
     * k线时间
     */
    public function k_time()
    {
        $arr = array(
            '1m',
            '3m',
            '5m',
            '15m',
            '30m',
            '1h',
            '2h',
            '4h',
            '6h',
            '8h',
            '12h',
            '1d',
            '3d',
            '1w',
            '1M'
        );
        return $arr;
    }

    /*
     * 币对组
     */
    public function bt()
    {
        $arr = array(
            'BTCUSDT',
            'ETHUSDT'
        );
        return $arr;
    }

    /*
     * k线数据
     */
    public function klines($symbol = 'BNBBTC')
    {
        $req = new req();
        foreach ($this->k_time() as $v) {
            foreach ($this->bt() as $symbol) {
                $result = $req->get_binance_kline($symbol, $v);
                unset($result[0]);
                foreach ($result as $val) {
                    $where['open_time'] = $val[0];
                    $where['time_type'] = $v;
                    $where['symbol'] = $symbol;
                    $se_val = M('binance_kline')->where($where)->find();
                    if ($se_val) {} else {
                        $add['open_time'] = $val[0];
                        $add['open'] = $val[1];
                        $add['high'] = $val[2];
                        $add['low'] = $val[3];
                        $add['close'] = $val[4];
                        $add['volume'] = $val[5];
                        $add['close_time'] = $val[6];
                        $add['asset_volume'] = $val[7];
                        $add['trades'] = $val[8];
                        $add['base_volume'] = $val[9];
                        $add['quote_volume'] = $val[10];
                        $add['ignore'] = $val[11];
                        $add['time_type'] = $v;
                        $add['symbol'] = $symbol;
                        M('binance_kline')->add($add);
                    }
                }
            }
        }
    }

    /*
     * 24小时代码价格变化
     */
    public function ticker24($symbol)
    {
        $req = new req();
        $result = $req->get_binance_24($symbol);
        var_dump($result);
        exit();
    }

    /*
     * 价格
     */
    public function tickerPrice()
    {
        $symbol = 'BNBBTC'; // 可不传
        $req = new req();
        $result = $req->get_binance_tickerprice($symbol);
        var_dump($result);
        exit();
    }

    /*
     * bookTicker
     */
    public function bookTicker()
    {
        $symbol = 'BNBBTC'; // 可不传
        $req = new req();
        $result = $req->get_binance_bookTicker($symbol);
        var_dump($result);
        exit();
    }

    /*
     * order 新订单 HMAC SHA256
     */
    public function order($symbol = 'BNBBTC', $side = 'SELL', $type = 'MARKET', $quantity = '10', $timeInForce = '', $price = '', $newClientOrderId = '', $stopPrice = '', $icebergQty = '', $newOrderRespType = '', $recvWindow = '')
    {
        $type = 'MARKET'; // 必传
                          // 必传
        $param = array(
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity
        );
        if ($timeInForce)
            $param['timeInForce'] = $timeInForce;
        if ($price)
            $param['price'] = $price;
        if ($newClientOrderId)
            $param['newClientOrderId'] = $newClientOrderId;
        if ($stopPrice)
            $param['stopPrice'] = $stopPrice;
        if ($icebergQty)
            $param['icebergQty'] = $icebergQty;
        if ($newOrderRespType)
            $param['newOrderRespType'] = $newOrderRespType;
        if ($recvWindow)
            $param['recvWindow'] = $recvWindow;
        $req = new req();
        $result = $req->get_binance_order($param, $type);
        if ($result['code'] < 0) {
            var_dump($result['msg']);
            exit();
        }
    }

    /*
     * 取消订单
     */
    public function dorder($symbol, $orderId = '28', $origClientOrderId = '6gCrw2kRUAF9CvJDGP16IP', $newClientOrderId = '', $recvWindow = '')
    {
        $param = array(
            'symbol' => 'BNBBTC'
        );
        if ($orderId)
            $param['orderId'] = $orderId;
        if ($origClientOrderId)
            $param['origClientOrderId'] = $origClientOrderId;
        if ($newClientOrderId)
            $param['newClientOrderId'] = $newClientOrderId;
        if ($recvWindow)
            $param['recvWindow'] = $recvWindow;
        $req = new req();
        $result = $req->get_binance_dorder($param);
        var_dump($result);
        exit();
    }

    /*
     * 当前未结订单 HMAC SHA256
     */
    public function openOrders()
    {
        $req = new req();
        $result = $req->get_binance_openOrder();
        var_dump($result);
        exit();
    }

    /*
     * 所有订单 HMAC SHA256
     */
    public function allorders($symbol = 'BNBBTC', $orderId = '', $limit = 500, $recvWindow = '')
    {
        $param = array(
            'symbol' => 'BNBBTC'
        );
        if ($orderId)
            $param['orderId'] = $orderId;
        if ($limit)
            $param['limit'] = $limit;
        if ($recvWindow)
            $param['recvWindow'] = $recvWindow;
        $req = new req();
        $result = $req->get_binance_allorders($param);
        var_dump($result);
        exit();
    }

    /*
     * 账户信息 NMAC SHA256
     */
    public function account()
    {
        $req = new req();
        $result = $req->get_binance_account();
        var_dump($result);
        exit();
    }

    /*
     * 账户交易清单 HMAC SHA256
     */
    public function myTrades($symbol = 'BNBBTC', $limit = 0)
    {
        $symbol = 'BNBBTC';
        $req = new req();
        $result = $req->get_binance_myTrades($symbol);
        var_dump($result);
        exit();
    }
}