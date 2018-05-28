<?php
namespace Home\Controller;

use JYS\huobi\req;
set_time_limit(0);

/**
 * macd数据统计及交易策略控制器
 */
class MACDController extends HomeController
{

    private $req = null;

    public function __construct()
    {
        $this->req = new req();
    }

    /**
     * 计算macd
     * 
     * @param unknown $df
     *            大盘数据
     * @param number $short
     *            短线
     * @param number $long
     *            长线
     * @param number $M
     *            复合次数
     */
    public function get_MACD($df, $short = 12, $long = 26, $M = 9)
    {
        foreach ($df as $i => $v) {
            if ($i == 0) {
                $df[$i]['ema' . $short] = $df[$i]['close'];
                $df[$i]['ema' . $long] = $df[$i]['close'];
                $df[$i]['diff'] = 0;
                $df[$i]['dea'] = 0;
                $df[$i]['macd'] = 0;
                
                $df[$i]['diffgo'] = 0;
                $df[$i]['ma5go'] = 0;
                $df[$i]['macdgo'] = 0;
            } else {
                $df[$i]['ema' . $short] = (2 * $df[$i]['close'] + ($short - 1) * $df[$i - 1]['ema' . $short]) / ($short + 1);
                $df[$i]['ema' . $long] = (2 * $df[$i]['close'] + ($long - 1) * $df[$i - 1]['ema' . $long]) / ($long + 1);
                $df[$i]['diff'] = $df[$i]['ema' . $short] - $df[$i]['ema' . $long];
                $df[$i]['dea'] = (2 * $df[$i]['diff'] + ($M - 1) * $df[$i - 1]['dea']) / ($M + 1);
                $df[$i]['macd'] = 4 * ($df[$i]['diff'] - $df[$i]['dea']);
                
                $df[$i]['diffgo'] = $df[$i]['diff'] - $df[$i - 1]['diff'];
                $df[$i]['ma5go'] = $df[$i]['ma5'] - $df[$i - 1]['ma5'];
                $df[$i]['macdgo'] = $df[$i]['macd'] - $df[$i - 1]['macd'];
            }
        }
        return $df;
    }

    public function getMA($df, $n)
    {
        foreach ($df as $k => $v) {
            for ($i = 0; $i < $n; $i ++) {
                $df[$k]['ma' . $n] += $df[$k + $i]['close'];
            }
            $df[$k]['ma' . $n] = $df[$k]['ma' . $n] / $n;
            if ($k > $n) { // 只计算最近几个的均值
                break;
            }
        }
        return $df;
    }

    public function getSAR($df, $n)
    {
        for ($i = 0; $i <= $n; $i ++) {
            if ($df[$n - $i]['open'] < $df[$n - $i]['close']) {
                $ep = $df[$n - $i + 1]['high'];
            } else {
                $ep = $df[$n - $i + 1]['low'];
            }
            if ($i == 0) {
                if ($df[$n - 1]['open'] < $df[$n - 1]['close']) {
                    $df[$n]['sar'] = $df[$n]['low'];
                } else {
                    $df[$n]['sar'] = $df[$n]['high'];
                }
                $af = 0.02;
            } else {
                if ((($df[$n - $i]['open'] < $df[$n - $i]['close']) && ($df[$n - $i + 1]['open'] < $df[$n - $i + 1]['close'])) || (($df[$n - $i]['open'] > $df[$n - $i]['close']) && ($df[$n - $i + 1]['open'] > $df[$n - $i + 1]['close']))) {}
                if (($df[$n - $i]['open'] <= $df[$n - $i]['close']) && ($df[$n - $i + 1]['open'] <= $df[$n - $i + 1]['close'])) {
                    if ($df[$n - $i]['high'] > $df[$n - $i + 1]['high']) {
                        $af += 0.02;
                        $af = ($af > 0.2) ? 0.02 : $af;
                    }
                } else 
                    if (($df[$n - $i]['open'] > $df[$n - $i]['close']) && ($df[$n - $i + 1]['open'] > $df[$n - $i + 1]['close'])) {
                        if ($df[$n - $i]['low'] < $df[$n - $i + 1]['low']) {
                            $af += 0.02;
                            $af = ($af > 0.2) ? 0.02 : $af;
                        }
                    } else {
                        $af = 0.02;
                    }
                
                $df[$n - $i]['sar'] = $df[$n - $i + 1]['sar'] + $af * ($ep - $df[$n - $i + 1]['sar']);
                
                if ($df[$n - $i]['open'] <= $df[$n - $i]['close']) {
                    if ($df[$n - $i]['sar'] > $df[$n - $i]['low'] || $df[$n - $i]['sar'] > $df[$n - $i + 1]['low']) {
                        $df[$n - $i]['sar'] = min($df[$n - $i]['low'], $df[$n - $i + 1]['low']);
                    }
                }
                if ($df[$n - $i]['open'] > $df[$n - $i]['close']) {
                    if ($df[$n - $i]['sar'] < $df[$n - $i]['high'] || $df[$n - $i]['sar'] < $df[$n - $i + 1]['high']) {
                        $df[$n - $i]['sar'] = max($df[$n - $i]['high'], $df[$n - $i + 1]['high']);
                    }
                }
            }
        }
        
        return $df;
    }

    //macd修改
    public function macd2($data){

        $data = $this->getMA($data,5);
        $data = $this->getMA($data,10);

        $arr = array();
        krsort($data);
        foreach ($data as $v) {
            // $v['close'] = substr($v['close'], 0, 6);
            $arr[] = $v;
        }
        $df = $this->get_MACD($arr, 12, 26, 9);
        
        unset($arr);
        unset($data);
        
        $max = count($df) - 1;
        $dateline = date("Y-m-d H:i:s");

        if($df[$max]['macd'] < $df[$max - 1]['macd'] && $df[$max - 2]['macd'] < $df[$max - 1]['macd'] && $df[$max - 3]['macd'] > 0 && $df[$max]['macd'] > 0 && $df[$max - 1]['macd'] > 0 && $df[$max - 2]['macd'] > 0){

            //卖出操作
            return 2;
        }

        //买入操作
        if($df[$max]['macd'] > $df[$max- 1]['macd'] && $df[$max - 2]['macd'] > $df[$max - 1]['macd'] && $df[$max - 3]['macd'] < 0 && $df[$max]['macd'] < 0 && $df[$max - 1]['macd'] < 0 && $df[$max - 2]['macd'] < 0){
            return 1;
        }

        return 3;
    }

    public function macd()
    {
        //$symbol = $_GET['pair'] ? $_GET['pair'] : 'htusdt';
        $symbol = 'htusdt';
        $CURRENCY = C('CURRENCY');
        $time = $CURRENCY[$symbol]['time'] ? $CURRENCY[$symbol]['time'] : '15min';
        $tranum = $CURRENCY[$symbol]['num'] ? $CURRENCY[$symbol]['num'] : '0.1';
        // 判断交易量 预测波动幅度
        /*
         * $many = $this->req->get_history_kline($symbol, '4hour', 1);
         * $times = $many['data'][0]['high']/$many['data'][0]['low'];
         * if ($times<1.02) { //没有足够套利空间
         * //return ;
         * $dotrade = false;
         * } else {
         * $dotrade = true;
         * }
         * if ($CURRENCY[$symbol]['dotrade']) {
         * $dotrade = true;
         * }
         */
        
        $data = $this->req->get_history_kline($symbol, $time, 266);

        if ($data['status'] != 'ok') {
            $this->notice('error:' . $symbol);
            // $this->sms('404', 'macd');
            // die('error:'.$symbol);
        }
        var_dump(count($data['data']));exit;
        // sar
        /*
         * $data['data'] = $this->getSAR($data['data'], 80);
         * $this->p($data);
         */
        
        // ma5 ma10
        $data['data'] = $this->getMA($data['data'], 5);
        $data['data'] = $this->getMA($data['data'], 10);
        
        $arr = array();
        krsort($data['data']);
        foreach ($data['data'] as $v) {
            // $v['close'] = substr($v['close'], 0, 6);
            $arr[] = $v;
        }
        $df = $this->get_MACD($arr, 12, 26, 9);
        
        unset($arr);
        unset($data);
        // $this->p($df);
        
        $max = count($df) - 1;
        $dateline = date("Y-m-d H:i:s");
        
        // macd朝上 macd下降 diff下降或者前一个macd为顶点 价格下降 卖出 出现死叉再次卖出
        if ( // $df[$max]['ma5']<$df[$max-1]['ma5'] &&
$df[$max]['macd'] < $df[$max - 1]['macd'] && ($df[$max - 2]['macd'] < $df[$max - 1]['macd'] || $df[$max]['diff'] < $df[$max - 1]['diff']) && $df[$max - 3]['macd'] > 0 && $df[$max]['macd'] > 0 && $df[$max - 1]['macd'] > 0 && $df[$max - 2]['macd'] > 0) {
            // 第一次交易或者 上次为买入动作并且买入价格比现在小,则进行卖出 差价要求千分五以上，没有达到需要人工查看原因
            $pre = M()->query("SELECT * FROM cb_order 
                        WHERE pair='$symbol' ORDER BY id DESC LIMIT 1");
            var_dump(5555555);var_dump($pre);exit;
            if (empty($pre) || ($pre[0]['order'] == 'buy' && $pre[0]['price'] < ($df[$max]['close'] * 0.995))) {
                if ($CURRENCY[$symbol]['num']) {
                    $result = $this->req->place_order(ACCOUNT_ID, $tranum, 0, $symbol, 'sell-market');
                    usleep(200000);
                    $orderid = $result['data'];
                    $data = $this->req->get_order($orderid);
                    $field_amount = $data['data']['field-amount'];
                    $price = $data['data']['field-cash-amount'] / $data['data']['field-amount'];
                } else {
                    $price = $df[$max]['close'];
                }
                $sell = array(
                    'order' => 'sell',
                    'pair' => $symbol,
                    'dateline' => $dateline,
                    'price' => $price,
                    'num' => $field_amount,
                    'close' => $df[$max]['close']
                );
                M('order'.'cb_')->add($sell);
                return 2;
                // $this->sms('200:' . date('Y-m-d H:i:s') . $symbol, 'down:' . $df[$max]['close']);
                // think_send_mail('80125476@qq.com', '80125476@qq.com', '量化', date('Y-m-d H:i:s') . $symbol . '|down:' . $df[$max]['close'], 2, 2);
            }
        }
        
        // macd朝下 macd上升 diff上升或者前一个macd为顶点 价格上升 买入 出现金叉再次买入
        if ( // $df[$max]['ma5']>$df[$max-1]['ma5'] &&
$df[$max]['macd'] > $df[$max - 1]['macd'] && ($df[$max - 2]['macd'] > $df[$max - 1]['macd'] || $df[$max]['diff'] > $df[$max - 1]['diff']) && $df[$max - 3]['macd'] < 0 && $df[$max]['macd'] < 0 && $df[$max - 1]['macd'] < 0 && $df[$max - 2]['macd'] < 0) {
            // 第一次交易或者 上次为卖出动作并且卖出价格比现在大,则进行买入 差价要求千分五以上，没有达到需要人工查看原因
            $pre = M()->query("SELECT * FROM cb_order
                WHERE pair='$symbol' ORDER BY id DESC LIMIT 1");
            //M('order','cb_')->where("pair = '{$symbol}'")->order('id DESC')->find();
            var_dump(515451548);var_dump($pre);
            if (empty($pre) || ($pre[0]['order'] == 'sell' && $pre[0]['price'] > ($df[$max]['close'] * 1.005))) {
                
                if ($CURRENCY[$symbol]['num']) {
                    $result = $this->req->place_order(ACCOUNT_ID, $tranum * $df[$max]['close'], 0, $symbol, 'buy-market');
                    usleep(200000);
                    $orderid = $result['data'];
                    $data = $this->req->get_order($orderid);
                    $field_amount = $data['data']['field-amount'];
                    $price = $data['data']['price'];
                } else {
                    $price = $df[$max]['close'];
                }
                $sell = array(
                    'order' => 'buy',
                    'pair' => $symbol,
                    'dateline' => $dateline,
                    'price' => $df[$max]['close'],
                    'num' => $field_amount,
                    'close' => $df[$max]['close']
                );
                M('order','cb_')->add($sell);
                return 1;
                // $this->sms('200:' . date('Y-m-d H:i:s') . $symbol, 'up:' . $df[$max]['close']);
                // think_send_mail('80125476@qq.com', '80125476@qq.com', '量化', date('Y-m-d H:i:s') . $symbol . '|up:' . $df[$max]['close'], 2, 2);
            }
        }
        
        // $return = $df[$max];
        // unset($df);
        // var_dump(1111111111);
        // var_dump($return);exit;
        // return $return;
    }

    public function run()
    {
        $i = 0;
        $symbol = $_GET['pair'] ? $_GET['pair'] : 'htusdt';
        while (true) {
            $pid = pcntl_fork(); // 利用线程自动回收内存，避免内存溢出
            if ($pid == - 1) {
                die('could not fork');
            } elseif ($pid) {
                // 父进程获得子进程的pid，存入数组
                $pidArr[] = $pid;
            } else {
                $return = $this->macd();
                $data = array(
                    $symbol . '时间' => date("H:i:s"),
                    'pair' => $symbol,
                    'price' => substr($return['close'], 0, 6),
                    'ma5' => substr($return['ma5'], 0, 6),
                    'diff' => substr($return['diff'], 0, 6),
                    'macd' => substr($return['macd'], 0, 6),
                    'diffgo' => substr($return['diffgo'], 0, 6),
                    'ma5go' => substr($return['ma5go'], 0, 6),
                    'macdgo' => substr($return['macdgo'], 0, 6)
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
                // echo "\n".memory_get_usage()."\n";
                echo "\n";
                
                sleep(1);
                exit();
            }
            while (count($pidArr) > 0) {
                $myId = pcntl_waitpid(- 1, $status, WNOHANG);
                foreach ($pidArr as $key => $pid) {
                    if ($myId == $pid)
                        unset($pidArr[$key]);
                }
            }
            $i ++;
        }
    }
}
