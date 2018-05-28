<?php
namespace Home\Controller;

use JYS\huobi\req;
set_time_limit(0);

/**
 * 三角套利
 */
class TriangelController extends HomeController
{

    private $base_cur = 'ht';

    private $quote_cur = 'btc';

    private $mid_cur = 'usdt';
    
    // 币种余额
    private $base_num = 0;

    private $quote_num = 0;

    private $mid_num = 0;

    private $base_quote_slippage = 0.002;
 // 设定市场价滑点百分比
    private $base_mid_slippage = 0.002;

    private $quote_mid_slippage = 0.002;

    private $base_quote_fee = 0.0005;
 // 设定手续费比例
    private $base_mid_fee = 0.0005;

    private $quote_mid_fee = 0.0005;

    private $order_ratio_base_quote = 0.5;
 // 设定吃单比例
    private $order_ratio_base_mid = 0.5;
    
    // 设定监控时间
    private $interval = 10;
    
    // 设定市场初始 ------------现在没有接口，人工转币，保持套利市场平衡--------------
    private $base_quote_quote_reserve = 0.0;
 // 设定账户最少预留数量,根据你自己的初始市场情况而定, 注意： 是数量而不是比例
    private $base_quote_base_reserve = 0.0;

    private $quote_mid_mid_reserve = 0.0;

    private $quote_mid_quote_reserve = 0.0;

    private $base_mid_base_reserve = 0.0;

    private $base_mid_mid_reserve = 0.0;
    
    // 最小的交易单位设定
    private $min_trade_unit = 0.2;
 // LTC/BTC交易对，设置为0.2, ETH/BTC交易对，设置为0.02
    private $market_price_tick = array();
 // 记录触发套利的条件时的当前行情
                                          
    // 初始化交易对、账户余额
    public function __construct()
    {
        if (I('base')) {
            $this->base_cur = I('base');
        }
        if (I('quote')) {
            $this->quote_cur = I('quote');
        }
        if (I('mid')) {
            $this->mid_cur = I('mid');
        }
        
        $this->get_balance();
    }
    
    // 自定义交易对
    public function set($base, $quote, $mid)
    {
        $this->base_cur = $base;
        $this->quote_cur = $quote;
        $this->mid_cur = $mid;
    }
    
    // 自动执行
    public function run()
    {
        $start = time();
        // 执行30s
        while (time() - $start < 30) {
            $this->main();
        }
    }

    public function find()
    {
        $this->set('eos', 'btc', 'usdt');
        $this->main();
        
        $this->set('omg', 'btc', 'usdt');
        $this->main();
        
        $this->set('neo', 'btc', 'usdt');
        $this->main();
        
        $this->set('cvc', 'btc', 'usdt');
        $this->main();
        
        $this->set('iost', 'eth', 'usdt');
        $this->main();
        
        $this->set('omg', 'eth', 'btc');
        $this->main();
    }
    
    // 获取账户余额
    public function get_balance()
    {
        $req = new req();
        $data = $req->get_balance();
        foreach ($data['data']['list'] as $v) {
            if ($v['currency'] == $this->base_cur && $v['type'] == 'trade') {
                $this->base_num = $v['balance'];
            }
            if ($v['currency'] == $this->quote_cur && $v['type'] == 'trade') {
                $this->quote_num = $v['balance'];
            }
            if ($v['currency'] == $this->mid_cur && $v['type'] == 'trade') {
                $this->mid_num = $v['balance'];
            }
        }
    }

    public function main()
    {
        $req = new req();
        
        $this->market_price_tick = array();
        $this->market_price_tick[$this->base_cur . $this->quote_cur] = $req->get_market_depth($this->base_cur . $this->quote_cur, 'step0');
        $market_price_sell_1 = $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['asks'][0][0];
        $market_price_buy_1 = $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['bids'][0][0];
        
        $this->market_price_tick[$this->base_cur . $this->mid_cur] = $req->get_market_depth($this->base_cur . $this->mid_cur, 'step0');
        $base_mid_price_sell_1 = $this->market_price_tick[$this->base_cur . $this->mid_cur]['tick']['asks'][0][0];
        $base_mid_price_buy_1 = $this->market_price_tick[$this->base_cur . $this->mid_cur]['tick']['bids'][0][0];
        
        $this->market_price_tick[$this->quote_cur . $this->mid_cur] = $req->get_market_depth($this->quote_cur . $this->mid_cur, 'step0');
        $quote_mid_price_sell_1 = $this->market_price_tick[$this->quote_cur . $this->mid_cur]['tick']['asks'][0][0];
        $quote_mid_price_buy_1 = $this->market_price_tick[$this->quote_cur . $this->mid_cur]['tick']['bids'][0][0];
        
        // 正循环差价
        $pos = ($base_mid_price_buy_1 / $quote_mid_price_sell_1 - $market_price_sell_1) / $market_price_sell_1;
        // 逆循环差价
        $neg = ($market_price_buy_1 - $base_mid_price_sell_1 / $quote_mid_price_buy_1) / $market_price_buy_1;
        $this->logWirte($pos, 'triangel', $this->base_cur . '|' . $this->quote_cur . '|' . $this->mid_cur . '正循环差价');
        $this->logWirte($neg . "\r\n", 'triangel', $this->base_cur . '|' . $this->quote_cur . '|' . $this->mid_cur . '逆循环差价');
        // 滑点+手续费
        $fee = $this->sum_slippage_fee();
        
        // 检查正循环套利
        if ($pos > $fee) {
            $market_buy_size = $this->get_market_buy_size();
            $market_buy_size = round($market_buy_size, 2);
            if ($market_buy_size >= $this->min_trade_unit) {
                // $this->pos_cycle($market_buy_size);
            }
            $this->logWirte($pos, 'tri', $this->base_cur . '|' . $this->quote_cur . '|' . $this->mid_cur . '正循环差价');
        } elseif ($neg > $fee) {
            $market_sell_size = $this->get_market_sell_size();
            $market_sell_size = round($market_sell_size, 2);
            if ($market_sell_size >= $this->min_trade_unit) {
                // $this->neg_cycle($market_sell_size);
            }
            $this->logWirte($neg . "\r\n", 'tri', $this->base_cur . '|' . $this->quote_cur . '|' . $this->mid_cur . '逆循环差价');
        }
        
        echo $this->base_cur . '|' . $this->quote_cur . '|' . $this->mid_cur . '<br>';
        print_r(array(
            ($base_mid_price_buy_1 / $quote_mid_price_sell_1 - $market_price_sell_1) / $market_price_sell_1,
            $this->sum_slippage_fee()
        ));
        echo '<br>';
        print_r(array(
            ($market_price_buy_1 - $base_mid_price_sell_1 / $quote_mid_price_buy_1) / $market_price_buy_1,
            $this->sum_slippage_fee()
        ));
        echo '<br>----------------------<br>';
    }
    
    // 计算最保险的下单数量
    public function get_market_buy_size()
    {
        $market_buy_size = $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['asks'][0][1] * $this->order_ratio_base_quote;
        $base_mid_sell_size = $this->market_price_tick[$this->base_cur . $this->mid_cur]['tick']['bids'][0][1] * $this->order_ratio_base_mid;
        $base_quote_off_reserve_buy_size = ($this->quote_num - $this->base_quote_quote_reserve) / $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['asks'][0][0];
        
        $quote_mid_off_reserve_buy_size = ($this->mid_num - $this->quote_mid_mid_reserve) / $this->market_price_tick[$this->quote_cur . $this->mid_cur]['tick']['asks'][0][0] / $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['asks'][0][0];
        
        $base_mid_off_reserve_sell_size = $this->base_num - $this->base_mid_base_reserve;
        /*
         * echo $market_buy_size.'<br>';
         * echo $base_mid_sell_size.'<br>';
         * echo $base_quote_off_reserve_buy_size.'<br>';
         * echo $quote_mid_off_reserve_buy_size.'<br>';
         * echo $base_mid_off_reserve_sell_size.'<br>';
         */
        return floor(min($market_buy_size, $base_mid_sell_size, $base_quote_off_reserve_buy_size, $quote_mid_off_reserve_buy_size, $base_mid_off_reserve_sell_size) * 10000) / 10000;
    }

    public function get_market_sell_size()
    {
        $market_sell_size = $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['bids'][0][1] * $this->order_ratio_base_quote;
        $base_mid_buy_size = $this->market_price_tick[$this->base_cur . $this->mid_cur]['tick']['asks'][0][1] * $this->order_ratio_base_mid;
        $base_quote_off_reserve_sell_size = $this->base_num - $this->base_quote_base_reserve;
        
        $quote_mid_off_reserve_sell_size = ($this->quote_num - $this->quote_mid_quote_reserve) / $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['bids'][0][0];
        
        $base_mid_off_reserve_buy_size = ($this->mid_num - $this->base_mid_mid_reserve) / $this->market_price_tick[$this->base_cur . $this->mid_cur]['tick']['asks'][0][0];
        echo $market_sell_size . '<br>';
        echo $base_mid_buy_size . '<br>';
        echo $base_quote_off_reserve_sell_size . '<br>';
        echo $quote_mid_off_reserve_sell_size . '<br>';
        echo $base_mid_off_reserve_buy_size . '<br>';
        return floor(min($market_sell_size, $base_mid_buy_size, $base_quote_off_reserve_sell_size, $quote_mid_off_reserve_sell_size, $base_mid_off_reserve_buy_size) * 10000) / 10000;
    }

    /**
     * 正循环套利
     * 
     * @param unknown $market_buy_size            
     */
    public function pos_cycle($market_buy_size)
    {
        $req = new req();
        $result = $req->place_order(ACCOUNT_ID, $market_buy_size, $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['asks'][0][0], $this->base_cur . $this->quote_cur, 'buy-limit');
        usleep(200000);
        $orderid = $result['data'];
        
        $retry = 0;
        $already_hedged_amount = 0;
        $already_cash = 0;
        while ($retry < 3) {
            if ($retry == 2) {
                // 取消剩余未成交的
                $req->cancel_order($orderid);
            }
            
            $data = $req->get_order($orderid);
            $field_amount = $data['data']['field-amount'];
            // 没有新的成功交易或者新成交数量太少
            if ($field_amount - $already_hedged_amount < $this->min_trade_unit) {
                $retry += 1;
                continue;
            }
            
            // 开始对冲
            $pid = pcntl_fork();
            if ($pid == - 1) {
                die('could not fork');
            } elseif ($pid) {
                // 父进程获得子进程的pid，存入数组
                $pidArr[] = $pid;
                $this->hedged_sell_cur_pair($field_amount - $already_hedged_amount, $this->base_cur . $this->mid_cur);
            } else {
                $m = $data['data']['field-cash-amount'];
                $this->hedged_buy_cur_pair($m - $already_cash, $this->quote_cur . $this->mid_cur);
                exit();
            }
            while (count($pidArr) > 0) {
                $myId = pcntl_waitpid(- 1, $status, WNOHANG);
                foreach ($pidArr as $key => $pid) {
                    if ($myId == $pid)
                        unset($pidArr[$key]);
                }
            }
            
            $already_cash = $m;
            $already_hedged_amount = $field_amount;
            if ($field_amount >= $market_buy_size) {
                break;
            }
            $retry ++;
            usleep(200000);
        }
    }

    /**
     * 逆循环套利
     * 
     * @param unknown $market_sell_size            
     */
    public function neg_cycle($market_sell_size)
    {
        $req = new req();
        if (! $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['bids'][0][0]) {
            $this->logWirte('错误', 'err', '卖出金额为0');
            die();
        }
        $result = $req->place_order(ACCOUNT_ID, $market_sell_size, $this->market_price_tick[$this->base_cur . $this->quote_cur]['tick']['bids'][0][0], $this->base_cur . $this->quote_cur, 'sell-limit');
        usleep(200000);
        $orderid = $result['data'];
        
        $retry = 0;
        $already_hedged_amount = 0;
        $already_cash = 0;
        while ($retry < 3) {
            if ($retry == 2) {
                // 取消剩余未成交的
                $req->cancel_order($orderid);
            }
            
            $data = $req->get_order($orderid);
            $field_amount = $data['data']['field-amount'];
            // 没有新的成功交易或者新成交数量太少
            if ($field_amount - $already_hedged_amount < $this->min_trade_unit) {
                $retry += 1;
                continue;
            }
            
            // 开始对冲
            $pid = pcntl_fork();
            if ($pid == - 1) {
                die('could not fork');
            } elseif ($pid) {
                // 父进程获得子进程的pid，存入数组
                $pidArr[] = $pid;
                $this->hedged_buy_cur_pair($field_amount - $already_hedged_amount, $this->base_cur . $this->mid_cur);
            } else {
                $m = $data['data']['field-cash-amount'];
                $this->hedged_sell_cur_pair($m - $already_cash, $this->quote_cur . $this->mid_cur);
                exit();
            }
            while (count($pidArr) > 0) {
                $myId = pcntl_waitpid(- 1, $status, WNOHANG);
                foreach ($pidArr as $key => $pid) {
                    if ($myId == $pid)
                        unset($pidArr[$key]);
                }
            }
            
            $already_cash = $m;
            $already_hedged_amount = $field_amount;
            if ($field_amount >= $market_sell_size) {
                break;
            }
            $retry ++;
            usleep(200000);
        }
    }

    /**
     * 对冲买入货币对
     * 
     * @param number $buy_size
     *            买入数量
     * @param string $cur_pair
     *            货币对名称
     */
    public function hedged_buy_cur_pair($buy_size = 0, $cur_pair = '')
    {
        $req = new req();
        $result = $req->place_order(ACCOUNT_ID, $buy_size, $this->market_price_tick[$cur_pair]['tick']['asks'][0][0], $cur_pair, 'buy-limit');
        $hedged_amount = 0;
        usleep(200000);
        $orderid = $result['data'];
        $data = $req->get_order($orderid);
        $hedged_amount = $data['data']['field-amount'];
        $req->cancel_order($orderid);
        if ($buy_size > $hedged_amount) {
            // 对未成交的进行市价交易
            $buy_amount = $this->market_price_tick[$cur_pair]['tick']['asks'][0][1] * ($buy_size - $hedged_amount);
            $res = $req->place_order(ACCOUNT_ID, $buy_amount, 0, $cur_pair, 'buy-market');
        }
    }

    /**
     * 对冲卖出货币对
     * 
     * @param float $sell_size
     *            卖出头寸
     * @param string $cur_pair
     *            货币对名称
     */
    public function hedged_sell_cur_pair($sell_size, $cur_pair)
    {
        $req = new req();
        if (! $this->market_price_tick[$cur_pair]['tick']['bids'][0][0]) {
            $this->logWirte('错误', 'err', '卖出金额为0');
            die();
        }
        $result = $req->place_order(ACCOUNT_ID, $sell_size, $this->market_price_tick[$cur_pair]['tick']['bids'][0][0], $cur_pair, 'sell-limit');
        $hedged_amount = 0;
        usleep(200000);
        $orderid = $result['data'];
        $data = $req->get_order($orderid);
        $hedged_amount = $data['data']['field-amount'];
        $req->cancel_order($orderid);
        if ($sell_size > $hedged_amount) {
            // 对未成交的进行市价交易
            $sell_qty = $sell_size - $hedged_amount;
            $req->place_order(ACCOUNT_ID, $sell_qty, 0, $cur_pair, 'sell-market');
        }
    }
    
    // 滑点+手续费
    public function sum_slippage_fee()
    {
        return $this->base_quote_slippage + $this->base_mid_slippage + $this->quote_mid_slippage + $this->base_quote_fee + $this->base_mid_fee + $this->quote_mid_fee;
    }
}
