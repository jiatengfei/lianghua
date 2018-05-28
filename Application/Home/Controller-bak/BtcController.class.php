<?php
namespace Home\Controller;

use Think\Controller;

class BtcController extends Controller
{

    public $ok;
 // ok
    public $binance;
 // 币安
    public $crontab;
 // 火币
    public function __construct()
    {
        $this->ok = A('Ok');
        $this->binance = A('Binance');
        $this->crontab = A('Crontab');
    }

    /*
     * 统一调用火币 OKEx binance k线数据
     */
    public function kline()
    {
        $this->crontab->getKline();
        $this->ok->getKline('ltc_btc');
        $this->binance->klines('BNBBTC');
        // Crontab 深度数据
        $this->crontab->getDepth();
        // Crontab 历史记录
        $this->crontab->getHtrade();
    }

    /*
     * 获取历史信息
     */
    public function history()
    {
        $this->crontab->getHtrade();
        // $this->ok->getTrades();
        // $this->binance->trades();
    }

    /*
     * 币币行情深度
     */
    // public function depth(){
    // $this->ok->getDepth();
    // $this->binance->depth();
    // }
    
    /*
     * 获取最近交易详情
     */
    public function trades()
    {
        // crontab
        $this->crontab->getHtrade(); // symbol 默认为btcusdt limit 500
                                     // ok
        $this->ok->getTrades(); // symbol 默认为ltc_btc limit 600
                                
        // binance
        $this->binance->trades(); // symbol 默认为BNBBTC limit 600
    }

    /*
     * 24小时成交量
     */
    public function cancel_order()
    {
        // crontab
        $this->crontab->get24(); // symbol 默认btcusdt
                                 
        // binance
        $this->binance->ticker24(); // symbol 默认BNBBTC
    }

    /*
     * 下单交易
     * @type 1是货币 2是OK 3是BINANCE
     * @货币的参数 $account_id $amount $symbol $type 必传 $price $souece 可不传
     * @ok的参数 $symbol $type 必传 $amount $price 可不传
     */
    public function getPlace()
    {
        if (! IS_POST) { // post 请求
            echo '使用post请求';
            exit();
        }
        if ($_REQUEST['all_type'] == 1) {
            $result = $this->huobi($_REQUEST);
            $this->crontab->getPlace($result['account_id'], $result['amount'], $result['symbol'], $result['type'], $result['price'], $result['source']);
        }
        
        if ($_REQUEST['all_type'] == 2) {
            $result = $this->okOrder($_REQUEST);
            $this->ok->getTrade($result['symbol'], $result['type'], $result['amount'], $result['p
              rice']);
        }
        
        if ($_REQUEST['all_type'] == 3) {
            $result = $this->binanceOrder($_REQUEST);
            $this->binance->order($result['symbol'], $result['side'], $result['type'], $result['quantity'], $result['timeInForce'], $result['price'], $result['newClientOrderId'], $result['stopPrice'], $result['icebergQty'], $result['newOrderRespType'], $result['recvWindow']);
        }
    }

    /*
     * 取消订单
     */
    public function dorder()
    {
        // crontab
        $this->crontab->getSubmitcancel(); // order_id 必传
                                           
        // ok
                                           
        // binance
        $this->binance->dorder(); // symbol 必传
    }
    
    // 货币参数判断
    public function huobi($val)
    {
        if (! array_key_exists('account_id', $val)) {
            echo 'account_id为必传';
            exit();
        }
        if (! array_key_exists('amount', $val)) {
            echo 'amount为必传';
            exit();
        }
        if (! array_key_exists('symbol', $val)) {
            echo 'symbol为必传';
            exit();
        }
        if (array_key_exists('type', $val)) {
            if ($val['type'] == 'sell-limit' || $val['type'] == 'buy-limit') {
                if (! array_key_exists('price', $val)) {
                    echo 'price参数缺少';
                    exit();
                } else {
                    $pice = $val['price'];
                }
            }
        } else {
            echo 'type为必传';
            exit();
        }
        if (array_key_exists('source', $val)) {
            $arr['source'] = $val['source'];
        } else {
            $arr['source'] = 0;
        }
        $arr['account_id'] = $val['account_id'];
        $arr['amount'] = $val['amount'];
        $arr['symbol'] = $val['symbol'];
      $arr['price']      = $price??0;
        $arr['type'] = $val['type'];
        return $arr;
    }

    /*
     * OK下单参数
     */
    public function okOrder($val)
    {
        $price = true;
        if (! array_key_exists('symbol', $val)) {
            echo 'symbol参数缺失';
            exit();
        }
        if (array_key_exists('type', $val)) {
            switch ($val['type']) {
                case 'buy':
                    $price = array_key_exists('price', $val);
                    break;
                case 'sell':
                    $price = array_key_exists('price', $val);
                    break;
                default:
                    break;
            }
        } else {
            echo '缺少type参数';
            exit();
        }
        
        if ($val['type'] == 'sell_market' && ! array_key_exists('amount', $val)) {
            echo 'amount参数缺失';
            exit();
        }
        
        if ($val['type'] == 'buy_market' && ! array_key_exists('price', $val)) {
            echo 'price参数缺失';
            exit();
        }
        
        $arr['symbol'] = $val['symbol'];
        $arr['type'] = $val['type'];
        
        if ($price) {
              $arr['price'] = $val['price']??0;
        } else {
            echo 'price价格缺失';
            exit();
        }

         $arr['amount']     = $val['amount']??0;
        return $arr;
    }
    
    // binance参数
    public function binanceOrder($val)
    {
        if (! array_key_exists('symbol', $val)) {
            echo 'symbol参数缺失';
            exit();
        }
        
        if (! array_key_exists('side', $val)) {
            echo 'side参数缺失';
            exit();
        }
        
        if (! array_key_exists('type', $val)) {
            echo 'type参数缺失';
            exit();
        }
        if (! array_key_exists('quantity', $val)) {
            echo 'quantity参数缺失';
            exit();
        }
        
        if ($val['type'] == 'LIMIT') {
            if (! array_key_exists('timeInForce', $val) && ! array_key_exists('quantity', $val) && ! array_key_exists('price', $val)) {
                echo '参数缺失';
                exit();
            }
        }
        
        if ($val['type'] == 'MARKET') {
            if (! array_key_exists('quantity', $val)) {
                echo 'quantity参数缺失';
                exit();
            }
        }
        
        if ($val['type'] == 'STOP_LOSS') {
            if (isset($val['quantity']) && isset($val['stopPrice'])) {
                echo '参数缺失';
                exit();
            }
        }
        
        if ($val['type'] == 'STOP_LOSS_LIMIT') {
            if (isset($val['timeInForce']) && isset($val['quantity']) && isset($val['price']) && isset($val['stopPrice'])) {
                echo '参数缺失';
                exit();
            }
        }
        
        if ($val['type'] == 'TAKE_PROFIT') {
            if (isset($val['quantity']) && isset($val['stopPrice'])) {
                echo '参数缺失';
                exit();
            }
        }
        
        if ($val['type'] == 'TAKE_PROFIT_LIMIT') {
            if (isset($val['timeInForce']) && isset($val['quantity']) && isset($val['price']) && isset($val['stopPrice'])) {
                echo '参数缺失';
                exit();
            }
        }
        
        if ($val['type'] == 'LIMIT_MAKER') {
            if (isset($val['quantity']) && isset($val['price'])) {
                echo '参数缺失';
                exit();
            }
        }
        $arr['symbol'] = $val['symbol'];
        $arr['side'] = $val['side'];
        $arr['type'] = $val['type'];
        $arr['quantity'] = $val['quantity'];
        $arr['timeInForce']      = $val['timeInForce']??0;
        $arr['price']            = $val['price']??0;
        $arr['newClientOrderId'] = $val['newClientOrderId']??0;
        $arr['stopPrice']        = $val['stopPrice']??0;
        $arr['icebergQty']       = $val['icebergQty']??0;
        $arr['newOrderRespType'] = $val['newOrderRespType']??0;
        $arr['recvWindow']       = $val['recvWindow']??0;
        return $arr;
    }
}