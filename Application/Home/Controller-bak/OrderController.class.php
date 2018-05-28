<?php
namespace Home\Controller;

use JYS\huobi\req;
set_time_limit(0);

/**
 * 交易控制器
 */
class OrderController extends HomeController
{

    private $req = null;

    public function __construct()
    {
        $this->req = new req();
    }

    /**
     * 砸盘
     */
    public function down()
    {
        $symbol = $_GET['pair'] ? $_GET['pair'] : 'htusdt';
        
        $many = $this->req->get_history_kline($symbol, '15min', 1);
        $close = $many['data'][0]['close'];
        $data = $this->req->get_market_depth($symbol, 'step0');
        $buy = $data['tick']['bids'][0][0];
        // 差价太小不砸盘
        if ($close - $buy < 0.0003) {
            return;
        }
        
        $result = $this->req->place_order(ACCOUNT_ID, 0.1, 0, $symbol, 'sell-market');
        usleep(200000);
        $orderid = $result['data'];
        $data = $this->req->get_order($orderid);
        $this->p($data, 0);
    }

    /**
     * 托盘
     */
    public function up()
    {
        $symbol = $_GET['pair'] ? $_GET['pair'] : 'htusdt';
        
        $many = $this->req->get_history_kline($symbol, '15min', 1);
        $close = $many['data'][0]['close'];
        $data = $this->req->get_market_depth($symbol, 'step0');
        $sell = $data['tick']['asks'][0][0];
        // 差价太小不托盘
        if ($sell - $close < 0.0003) {
            return;
        }
        $result = $this->req->place_order(ACCOUNT_ID, 0.1 * $close, 0, $symbol, 'buy-market');
        usleep(200000);
        $orderid = $result['data'];
        $data = $this->req->get_order($orderid);
        $this->p($data, 0);
    }

    public function run()
    {
        $i = 0;
        $symbol = $_GET['pair'] ? $_GET['pair'] : 'htusdt';
        $front = $_GET['front'] ? $_GET['front'] : 'down';
        while (true) {
            $pid = pcntl_fork(); // 利用线程自动回收内存，避免内存溢出
            if ($pid == - 1) {
                die('could not fork');
            } elseif ($pid) {
                // 父进程获得子进程的pid，存入数组
                $pidArr[] = $pid;
            } else {
                if ($front == 'down') {
                    $this->down();
                } else {
                    $this->up();
                }
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
