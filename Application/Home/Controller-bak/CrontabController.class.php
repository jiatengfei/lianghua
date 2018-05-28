<?php
namespace Home\Controller;

use JYS\huobi\req;
set_time_limit(0);

/**
 * 计划任务控制器
 */
class CrontabController extends HomeController
{

    /*
     * 测试获取k线数据
     */
    public function ceshi()
    {
        var_dump(88888);
        exit();
        $req = new req();
        $list = $this->getComChannel();
        var_dump($list);
        exit();
        $data = $req->get_history_kline('eosbtc', '1min', 100);
        var_dump($data);
        exit();
    }

    /*
     * 添加火币网的交易对列表
     */
    public function addChannel()
    {
        $list = json_decode($list, 1);
        foreach ($list as $v) {
            M('channel')->add($v);
        }
    }

    /**
     * 添加常用的交易对列表
     */
    public function getComChannel()
    {
        $list = M()->query("SELECT chid,symbol FROM cb_channel WHERE `symbol-partition`='main' OR `base-currency`='ht'");
        return $list;
    }

    /*
     * 时间选择
     */
    public function data_time()
    {
        $arr = array(
            '1min',
            '5min',
            '15min',
            '30min',
            '60min',
            '1day',
            '1mon',
            '1week',
            '1year'
        );
        return $arr;
    }

    /*
     * 币对
     */
    public function bt()
    {
        $arr = array(
            'cb_depth_htusdt' => 'htusdt',
            'cb_depth_btcusdt' => 'btcusdt',
            'cb_depth_ethusdt' => 'ethusdt',
            'cb_depth_xrpusdt' => 'xrpusdt',
            'cb_depth_dashusdt' => 'dashusdt',
            'cb_depth_eosusdt' => 'eosusdt',
            'cb_depth_ethbtc' => 'ethbtc',
            'cb_depth_xrpbtc' => 'xrpbtc',
            'cb_depth_dashbtc' => 'dashbtc',
            'cb_depth_htbtc' => 'htbtc',
            'cb_depth_eosbtc' => 'eosbtc',
            'cb_depth_eoseth' => 'eoseth',
            'cb_depth_hteth' => 'hteth'
        ); // array('htusdt','btcusdt','ethusdt','xrpusdt','dashusdt','eosusdt','ethbtc','xrpbtc','dashbtc','htbtc,','eosbtc','eoseth','hteth');
        return $arr;
    }

    /**
     * 添加k线交易信息
     */
    public function getKline()
    {
        $req = new req();
        $list = $this->getComChannel();
        $day = $this->data_time();
        foreach ($day as $val_num) {
            foreach ($list as $v) {
                $symbol = $v['symbol'];
                // foreach($day as $val_num){
                $data = $req->get_history_kline($symbol, $val_num, 100);
                
                if ($data['status'] != 'ok') {
                    $this->notice('error:' . $symbol);
                    continue;
                }
                foreach ($data['data'] as $key => $val) {
                    // TODO 第一条最新的数据交易统计还不完整，暂时丢弃
                    if ($key === 0) {
                        continue;
                    }
                    
                    $val['chid'] = $v['chid'];
                    $val['kid'] = $val['id'];
                    $val['r_time'] = $val_num;
                    $val['ts'] = $data['ts'];
                    unset($val['id']);
                    
                    /*
                     * M('kline')->add($val);
                     * continue;
                     */
                    $id = M('cb_kline')->where("chid={$val['chid']} AND kid={$val['kid']} AND r_time = '{$val_num}'")->getField('id');
                    if (empty($id)) {
                        M('cb_kline')->add($val);
                    } else {
                        if (time() - $val['kid'] < 100) {
                            M('cb_kline')->where("chid={$val['chid']} AND kid={$val['kid']}")->save($val);
                        }
                    }
                }
                // }
            }
        }
    }

    public function showMarket($pair, $sell_person_num = 0)
    {
        $req = new req();
        $data1 = $req->get_market_depth($pair, 'step0');
        if (! $sell_person_num) {
            $person_num = count($data1['tick']['asks']); // 总单/人数
        } else {
            $person_num = $sell_person_num;
        }
        $cur_num = 0; // 总币数
        $price_num = 0; // 单子出价和
        $money_num = 0; // 总资产
        $i = 1;
        foreach ($data1['tick']['asks'] as $v) {
            $cur_num += $v[1];
            $price_num += $v[0];
            $money_num += $v[1] * $v[0];
            $i ++;
            if ($i > $person_num)
                break;
        }
        $price_avg = $price_num / $person_num; // 单子均价
        $money_avg = $money_num / $cur_num; // 加权均价
        /*
         * echo "总单/人数: $person_num <br>";
         * echo "总币数: $cur_num <br>";
         * echo "总资产: $money_num <br>";
         * echo "单子均价: $price_avg <br>";
         * echo "加权均价: $money_avg <br><br><br>";
         */
        
        // 备份 计算比率使用
        $sell_cur_num = $cur_num;
        $sell_money_num = $money_num;
        $sell_price_avg = $price_avg;
        $sell_money_avg = $money_avg;
        
        if (! $sell_person_num) {
            $person_num = count($data1['tick']['bids']); // 总单/人数
        } else {
            $person_num = $sell_person_num;
        }
        $cur_num = 0; // 总币数
        $price_num = 0; // 单子出价和
        $money_num = 0; // 总资产
        $i = 1;
        foreach ($data1['tick']['bids'] as $v) {
            $cur_num += $v[1];
            $price_num += $v[0];
            $money_num += $v[1] * $v[0];
            $i ++;
            if ($i > $person_num)
                break;
        }
        $price_avg = $price_num / $person_num; // 单子均价
        $money_avg = $money_num / $cur_num; // 加权均价
        /*
         * echo "总单/人数: $person_num <br>";
         * echo "总币数: $cur_num <br>";
         * echo "总资产: $money_num <br>";
         * echo "单子均价: $price_avg <br>";
         * echo "加权均价: $money_avg <br><br>";
         */
        
        $a = $cur_num / $sell_cur_num;
        $b = $money_num / $sell_money_num;
        $c = $price_avg / $sell_price_avg;
        $d = $money_avg / $sell_money_avg;
        /*
         * echo "币数比: ".$a." <br>";
         * echo "资产比: ".$b." <br>";
         * echo "单子均价比: ".$c." <br>";
         * echo "加权均价比: ".$d." <br>-------------------<br>";
         */
        
        return array(
            '挂单币数比' . $sell_person_num => $a,
            '挂单资产比' . $sell_person_num => $b,
            '挂单单子均价比' . $sell_person_num => $c,
            '挂单加权均价比' . $sell_person_num => $d
        );
    }

    public function getDepth()
    {
        $symbol = 'htusdt';
        $req = new req();
        foreach ($this->bt() as $k => $val) {
            $data1 = $req->get_market_depth($val, 'step0');
            if ($data1) {
                foreach ($data1['tick']['bids'] as $v) {
                    $arr['ts'] = $data1['tick']['ts'];
                    $arr['price'] = $v[0];
                    $arr['qty'] = $v[1];
                    $arr['type'] = 'bids';
                    $arr['symbol'] = $val;
                    M($k)->add($arr);
                }
                foreach ($data1['tick']['asks'] as $vall) {
                    $arr2['ts'] = $data1['tick']['ts'];
                    $arr2['price'] = $vall[0];
                    $arr2['qty'] = $vall[1];
                    $arr2['type'] = 'asks';
                    $arr2['symbol'] = $val;
                    M($k)->add($arr2);
                }
            }
        }
        
        /*
         * $data2 = $req->get_market_depth('btcusdt','step0');
         * $data3 = $req->get_market_depth('eosusdt','step0');
         * $data4 = $req->get_market_depth('xrpusdt','step0');
         * $data5 = $req->get_market_depth('gntusdt','step0');
         * $data6 = $req->get_market_depth('xemusdt','step0');
         */
        
        // $this->showMarket($data1, 10);
        /*
         * $this->showMarket($data2);
         * $this->showMarket($data3);
         * $this->showMarket($data4);
         * $this->showMarket($data5);
         * $this->showMarket($data6);
         */
        // die;
        
        // $this->p($data1['tick']['bids'], 0);
        // $this->p($data1['tick']['asks']);
        
        // echo $data1['tick']['asks'][0][0].'<br>';
        // echo $data2['tick']['asks'][0][0].'<br>';
        // echo $data3['tick']['asks'][0][0].'<br>';
        // echo $data1['tick']['asks'][0][0]/$data2['tick']['asks'][0][0];
        
        // 买盘/卖盘
        /*
         * echo $data['tick']['bids'][0][0].'|'.$data['tick']['bids'][0][1];
         * echo '<br>';
         * echo $data['tick']['asks'][0][0].'|'.$data['tick']['asks'][0][1];
         */
    }

    public function tradeList()
    {
        $this->showTrade(100);
        $this->showTrade(800);
        $this->showTrade(2000);
    }

    public function showTrade($pair, $num = 100)
    {
        $req = new req();
        $data = $req->get_history_trade($pair, $num);
        // 成交单子所用时间
        $usetime = time() - substr($data['data'][$num - 1]['data'][0]['ts'], 0, 10);
        $buy = $buy_amount = $sell = $sell_amount = 0;
        foreach ($data['data'] as $v) {
            if ($v['data'][0]['direction'] == 'buy') {
                $buy ++;
                $buy_amount += $v['data'][0]['amount'];
            } else {
                $sell ++;
                $sell_amount += $v['data'][0]['amount'];
            }
        }
        /*
         * echo "购买时间: $usetime ($num 条)<br>";
         * echo "购买单数: $buy <br>";
         * echo "购买总量: $buy_amount <br>";
         * echo "卖数量: $sell <br>";
         * echo "卖总量: $sell_amount <br><br>";
         */
        
        $a = $num / $usetime;
        $b = $buy / $sell;
        $c = $buy_amount / $sell_amount;
        
        return array(
            '成单效率' . $num => $a,
            '成单买卖单比' . $num => $b,
            '成单买卖量比' . $num => $c
        );
    }

    /**
     * 实时查看市场行情
     */
    public function runR()
    {
        $i = 0;
        while (true) {
            $return = $this->record(false);
            $data = array(
                // '时间' => date("H:i:s"),
                'price' => substr($return['price'], 0, 6),
                '效率10' => substr($return['成单效率100'], 0, 6),
                '买卖10' => substr($return['成单买卖量比100'], 0, 6),
                '买卖80' => substr($return['成单买卖量比800'], 0, 6),
                '买卖200' => substr($return['成单买卖量比2000'], 0, 6),
                '资产10' => substr($return['挂单资产比10'], 0, 6),
                '资产50' => substr($return['挂单资产比50'], 0, 6),
                '资产150' => substr($return['挂单资产比150'], 0, 6),
                '均价10' => substr($return['挂单加权均价比10'], 0, 6),
                '均价50' => substr($return['挂单加权均价比50'], 0, 6),
                '均价150' => substr($return['挂单加权均价比150'], 0, 6)
            );
            if ($i % 10 == 0) {
                foreach ($data as $k => $v) {
                    echo $k . "\t";
                }
                echo "\n";
            }
            foreach ($data as $k => $v) {
                echo $v . "\t";
            }
            echo "\n";
            // sleep(1);
            $i ++;
        }
    }

    /**
     * 将市场交易行情存入数据库
     * 
     * @param string $show            
     */
    public function record($show = TRUE)
    {
        $pair = $_GET['pair'] ? $_GET['pair'] : 'htusdt';
        $data = array();
        $req = new req();
        $a = $req->get_history_kline($pair, '1min', 1);
        $data['pair'] = $pair;
        $data['dateline'] = date("Y-m-d H:i:s", $a['data'][0]['id']);
        $data['timeline'] = $a['data'][0]['id'];
        $data['price'] = $a['data'][0]['vol'] / $a['data'][0]['amount'];
        $data = array_merge($data, $this->showTrade($pair, 100));
        $data = array_merge($data, $this->showTrade($pair, 800));
        $data = array_merge($data, $this->showTrade($pair, 2000));
        $data = array_merge($data, $this->showMarket($pair, 10));
        $data = array_merge($data, $this->showMarket($pair, 50));
        $data = array_merge($data, $this->showMarket($pair, 150));
        if (! $_GET['nosql']) {
            M('maket')->add($data);
        }
        if ($show) {
            $this->p($data, 0);
        }
        return $data;
    }

    /**
     * 获取投票数量
     */
    public function vote()
    {
        echo "
            <table width='100%  '>
                <tr>
                    <td width='25%'>时间</td>
                    <td width='20%'>总票数</td>
                    <td width='20%'>增加票数</td>
                    <td width='20%'>人数</td>
                    <td width='20%'>增加人数</td>
                </tr>
            ";
        $result = M()->query("select * from cb_vote order by id desc limit 51");
        foreach ($result as $k => $v) {
            $result[$k]['polladd'] = $v['poll'] - $result[$k + 1]['poll'];
            $result[$k]['useradd'] = $v['user'] - $result[$k + 1]['user'];
            if ($k == count($result) - 1) {
                break;
            }
            
            echo "
                <tr>
                    <td>{$v['dateline']}</td>
                    <td>{$v['poll']}</td>
                    <td>{$result[$k]['polladd']}</td>
                    <td>{$v['user']}</td>
                    <td>{$result[$k]['useradd']}</td>
                </tr>
            ";
        }
        echo "
            </table>
            ";
    }

    function addVote($num = 0)
    {
        $poll = 0;
        $user = 0;
        $pdf = array();
        for ($i = 1; $i < 76; $i ++) {
            $url = 'https://api.hadax.com/vote/open/vote/item/get?r=qpndgbk5f8&id=' . $i;
            $con = file_get_contents($url);
            $data = json_decode($con, 1);
            if ($data['success'] == true) {
                $poll += $data['data']['poll'];
                $user += $data['data']['user_count'];
                $pdf[] = $data['data']['whitepaper'];
            } else {
                $num ++;
                if ($num < 5) {
                    $this->addVote($num);
                }
                die('error');
            }
        }
        
        $data = array(
            'dateline' => date("Y-m-d H:i:s"),
            'poll' => $poll,
            'user' => $user
        );
        M('vote')->add($data);
    }

    /*
     * 获取 Trade Detail 数据
     */
    public function getTrade()
    {
        var_dump(44444);
        exit();
        $symbol = 'btcusdt';
        $req = new req();
        $result = $req->get_market_trade($symbol);
    }

    /*
     * 批量获取最近的交易记录
     */
    public function getHtrade($size = '500')
    {
        $req = new req();
        foreach ($this->bt() as $symbol) {
            $result = $req->get_history_trade($symbol, $size);
            if ($result['status'] == 'ok') {
                foreach ($result['data'] as $v) {
                    foreach ($v['data'] as $val) {
                        $where['id'] = $v['id'];
                        $where['ts'] = $val['ts'];
                        $where['amount'] = $val['amount'];
                        $se_val = M('cb_history')->where($where)->find();
                        if (! $se_val) {
                            $arr['amount'] = $val['amount'];
                            $arr['ts'] = $val['ts'];
                            $arr['id'] = $v['id'];
                            $arr['price'] = $val['price'];
                            $arr['symbol'] = $symbol;
                            $arr['direction'] = $val['direction'];
                            M('cb_history')->add($arr);
                        }
                    }
                }
            }
        }
    }

    /*
     * 获取 Market Detail 24小时成交量数据
     */
    public function get24($symbol)
    {
        $req = new req();
        $result = $req->get_market_detail($symbol);
        var_dump($result);
        exit();
    }

    /*
     * 查询Pro站支持的所有交易对及精度
     */
    public function getCommonSymbols()
    {
        $req = new req();
        $result = $req->get_common_symbols();
        var_dump($result);
        exit();
    }

    /*
     * 查询Pro站支持的所有币种
     */
    public function getCurrencys()
    {
        $req = new req();
        $result = $req->get_common_currencys();
        var_dump($result);
        exit();
    }

    /*
     * 查询系统当前时间
     */
    public function getTimestamp()
    {
        $req = new req();
        $result = $req->get_common_timestamp();
        var_dump($result);
        exit();
    }

    /*
     * 查询当前用户的所有账户(即account-id)
     */
    public function getAccounts()
    {
        $req = new req();
        $result = $req->get_account_accounts();
        var_dump($result);
        exit();
    }

    /*
     * 查询Pro站指定账户的余额
     */
    public function getAccountIdBalance()
    {
        $req = new req();
        $result = $req->get_account_balance();
        var_dump($result);
        exit();
    }

    /*
     * Pro站下单
     */
    public function getPlace($account_id = '2697688', $amount = '0.1', $symbol = 'btcusdt', $type = 'buy-limit', $price = '10.1', $source = '')
    {
        $req = new req();
        $result = $req->place_order($account_id, $amount, $price, $symbol, $type);
        if ($result['status'] == 'error') {
            var_dump($result['err-msg']);
            exit();
        }
    }

    /*
     * 申请撤销一个订单请求
     */
    public function getSubmitcancel()
    {
        $order_id = '59378';
        $req = new req();
        $result = $req->cancel_order($order_id);
        var_dump($result);
        exit();
    }

    /*
     * 批量撤销订单
     */
    public function getBatchcancel()
    {
        $order_ids = array(); // list 单次不超出50
        $req = new req();
        $result = $req->cancel_orders($order_ids);
        var_dump($result);
        exit();
    }

    /*
     * 查询某个订单详情
     */
    public function getOrder()
    {
        $order_id = '59378';
        $req = new req();
        $result = $req->get_order($order_id);
        var_dump($result);
        exit();
    }

    /*
     * 查询某个订单的成交明细
     */
    public function getMatchresults()
    {
        $order_id = '1';
        $req = new req();
        $result = $req->get_order_matchresults($order_id);
        var_dump($result);
        exit();
    }

    /*
     * 查询当前委托、历史委托
     */
    public function getOrders()
    {
        $symbol = 'btcusdt';
        $types = '';
        $start_date = date('Y-m-d', time() - 86400);
        $end_date = date('Y-m-d', time());
        $status = 'pre-submitted';
        $from = '';
        $direct = '';
        $size = '';
        $req = new req($symbol, '', '', '', $status);
        var_dump($req);
        exit();
    }

    /*
     * 查询当前成交、历史成交
     */
    public function getOrderMatchresults()
    {
        $symbol = 'btcusdt';
        $types = '';
        $start_date = '';
        $end_date = '';
        $from = '';
        $direct = '';
        $size = '';
        $req = new req();
        $result = $req->get_orders_matchresults($symbol);
        var_dump($result);
        exit();
    }

    /*
     * 现货账户划入至借贷账户
     */
    public function getTransferInMargin()
    {
        $symbol = 'btcusdt';
        $currency = 'eth';
        $amount = '1.0';
        $req = new req();
        $result = $req->dw_transfer_in($symbol, $currency, $amount);
        var_dump($result);
        exit();
    }

    /*
     * 借贷账户划出至现货账户
     */
    public function getTransferOutMargin()
    {
        $symbol = 'btcusdt';
        $currency = 'eth';
        $amount = '1.0';
        $req = new req();
        $result = $req->dw_transfer_out($symbol, $currency, $amount);
        var_dump($result);
        exit();
    }

    /*
     * 申请借贷
     */
    public function getMarginOrders()
    {
        $symbol = 'btcusdt';
        $currency = 'eth';
        $amount = '10.1';
        $req = new req();
        $result = $req->margin_orders($symbol, $currency, $amount);
        var_dump($result);
        exit();
    }

    /*
     * 归还借贷
     */
    public function getOrderRepay()
    {
        $order_id = '';
        $amount = '10.1';
        $req = new req();
        $result = $req->repay_margin_orders($order_id, $amount);
        var_dump($result);
        exit();
    }

    /*
     * 借贷订单
     */
    public function getLoanOrders()
    {
        $symbol = 'btcusdt';
        $start_date = '';
        $end_date = '';
        $status = '';
        $from = '';
        $direct = '';
        $size = '';
        $req = new req();
        $result = $req->get_loan_orders($symbol);
        var_dump($result);
        exit();
    }

    /*
     * 借贷账户详情
     */
    public function getAccountsBalance()
    {
        $symbol = 'btcusdt';
        $req = new req();
        $result = $req->margin_balance($symbol);
        var_dump($result);
        exit();
    }

    /*
     * 申请提现虚拟币
     */
    public function getApiCreate()
    {
        $address = '';
        $amount = '';
        $currency = 'btc';
        $free = ''; // f
        $addr_tag = ''; // f
        $req = new req();
        $result = $req->withdraw_create($address, $amount, $currency);
        var_dump($result);
        exit();
    }

    /*
     * 申请取消提现虚拟币
     */
    public function getWithdrawCancel()
    {
        $withdraw_id = '';
        $req = new req();
        $result = $req->withdraw_cancel($withdraw_id);
        var_dump($result);
        exit();
    }
}
