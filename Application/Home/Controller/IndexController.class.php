<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class IndexController extends Controller
{
    public $order_price;
    public $upMoney;    //最高价
    public $stopLessMoney;    //止损价
    public $symbol;

    public function index()
    {
        if(IS_POST){
             $sym = explode('-',I('sym'));
             $sym1 = $sym[0];//I('bt_name1');
             $sym2 = $sym[1];//I('bt_name2');
             $money  = I('bt_price');
             $presentPrice = I('presentPrice');
             $maxUnitMoney = I('maxUnitMoney');
             $minUnitMoney = I('minUnitMoney');
             $stop_ratio   = I('stop_ratio');
             $stop_ratio2  = I('stop_ratio2');
             $genre        = I('genre')?I('genre'):1;
             $sell_price   = I('sell_price')?I('sell_price'):0;
            //$assetId = 2697688;
             $system = [
                 'r_time' => '15min',
                 'base'   => I('bt_name1'),
                 'money'  => 10000
             ];
             $params = [
                 'is_live' => true,
                 'back_segs' => 50,  
             ];
            $placeOrder = new \Home\Controller\PlaceOrderController($params,$system);
            $placeOrder->place_buy_order($sym1,$sym2,$money,$presentPrice,FALSE,2697688,$minUnitMoney,$maxUnitMoney,$stop_ratio,$stop_ratio2,$genre,$sell_price);
        }else{
            $this->display();
        }
    }

    public function sell(){
        if(IS_POST){
             $sym1 = I('bt_name1');
             $sym2 = I('bt_name2');
             $money  = I('bt_price');
             $presentPrice = I('presentPrice');
             $maxUnitMoney = I('maxUnitMoney');
             $minUnitMoney = I('minUnitMoney');
              $system = [
                 'r_time' => '15min',
                 'base'   => I('bt_name1'),
                 'money'  => 10000
             ];
             $params = [
                 'is_live' => true,
                 'back_segs' => 50,  
             ];
            $placeOrder = new \Home\Controller\PlaceOrderController($params,$system);
            $placeOrder->place_sell_order($sym1,$sym2,$money,$presentPrice,FALSE,2697688,$minUnitMoney,$maxUnitMoney);
            //$sell_order = $placeOrder->place_sell_order('ht','btc',5.89290939,1000,FALSE,2697688,1000,10001);
        }else{
            $this->display();
        }
    }

    /*
     *  止损操作
     */
    public function stopPrice(){
        //获取当前时间的最新一条数据
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline('htbtc','15min',2);
        if($kline['status'] != 'ok'){
            var_dump('接口数据请求错误');exit;
            $str = '接口数据请求有误';
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        $result = M('cb_order_buy')->order('data DESC')->find();
        if($closeMoney > $result['upmoney']){
            $this->upMoney = $closeMoney;
            //计算增长的比例
            $proportion = ($closeMoney - $result['order_price']) / $result['order_price'];
            if ($proportion < 0.15){
                $this->stopLessMoney = $this->upMoney * 0.95;
            } elseif ($proportion < 0.25){
                $this->stopLessMoney = $this->upMoney * 0.925;
            }else{
                $this->stopLessMoney = $this->upMoney * 0.9;
            }
            $save['upmoney'] = $this->upMoney;
            $save['stoplessmoney'] = $this->stopLessMoney;
            M('cb_order_buy')->where("id = {$result['id']}")->save($save);
        } elseif ($this->closeMoney < $result['stoplessmoney']){
            $str = '闭盘价格已经小于止损价格,建议卖出';
        }
        return $str;
    }

    /*
     *  按币的数量计算止损
     */
    public function bt_num_stopPrice(){
        //获取当前时间的最新一条数据
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline('htbtc','15min',2);
        if($kline['status'] != 'ok'){
            var_dump('接口数据请求错误');exit;
            $str = '接口数据请求有误';
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        $result = M('cb_order_buy')->order('data DESC')->find();
        if($closeMoney < $result['upMoney']){
            //止损价格
            $proportion = ($result['order_price'] - $closeMoney) / $result['order_price'];
            if ($proportion < 0.15){
                $this->stopLessMoney = $closeMoney + $closeMoney * 0.05;
                $this->upMoney = $stopLessMoney;
            } elseif ($proportion < 0.25){
                $this->stopLessMoney = $closeMoney + $closeMoney * 0.075;
                $this->upMoney = $stopLessMoney;
            }else{
                $this->stopLessMoney = $closeMoney + $closeMoney * 0.1;
                $this->upMoney = $stopLessMoney;
            }
        } elseif ($closeMoney > $upMoney){
            $str = '建议买入';
        }
        return $str;
    }

    /*
     *  监听止损
     */
    public function monitor(){
           $i = 0;
           while (true) {
            $pid = pcntl_fork();
            if ($pid == - 1) {
                die('could not fork');
            } elseif ($pid) {
                $pidArr[] = $pid;   //父进程
            } else {
                $return = $this->stopPrice();  //监听  
                echo $return;
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

    public function order($account,$symbol,$type,$price){
        $crontab = new \Home\Controller\CrontabController();
        $getPlace = $crontab->getPlace($account,$symbol,$type,$price);
        var_dump($getPlace);exit;
    }


    //htbtc  ht转换为btc  卖掉ht(卖)   btc转换ht 买入ht(买)    限价
     public function sell_order(){
         $crontab = new \Home\Controller\CrontabController();
         $getPlace = $crontab->getPlace(5,'htbtc','sell-limit',10000);
         var_dump($getPlace);exit;
     }

    public function buy_order(){
        $crontab = new \Home\Controller\CrontabController();
        $getPlace = $crontab->getPlace(5,'htbtc','buy-market',1000);
        var_dump($getPlace);exit;
    }



    //获取最近的交易记录
    public function trade($sym){
        $req = new req();
        $result = $req->get_history_trade($sym,500);
        if($result['status'] != 'ok'){
            var_dump('获取历史交易订单数据错误');exit;
        }
        foreach($result['data'] as $k=>$v){
            foreach($v['data'] as $key=>$val){
                if($val['direction'] == 'buy'){
                    $val['order_id'] = $v['id'];
                    $arr[] = $val;
                }
            }
        }
        return $arr;
    }

    //查询订单详情
    public function getOrder($arr){
        $req = new req();
        foreach($arr as $k=>$v){
          $result = $req->get_order($v);
          if($result['status'] != 'ok'){
            var_dump('获取订单详情错误');exit;
          }
          if($result['data']['price'] == 0.0){
            $price = $result['data']['field-cash-amount'] / $result['data']['field-amount'];
          }else{
            $price = $result['data']['price'];
          }
          $val_amount[] = $result['data']['field-amount'];
          $val_price[] = $price;
        }
          $val['average_price'] = array_sum($val_price) / count($val_price);
          $val['amount_num']    = array_sum($val_amount);
          return $val;
    }

    //撤销订单   3056267732
    public function getSubmitcancel(){
        $req = new req();
        $result = $req->cancel_order('3058630477');
        var_dump($result);exit;
    }


    /*
     *  system测试操作
     */
    public function sys(){
        //system('rm -rf /ceshi.php');
        //$a = system('ls /data/',$return);
        //var_dump($return);exit;
        echo 'ceshi454848';
    }

    public function system(){
       $qq = system('/usr/bin/php /data/wwwroot/default/quantitative-s/cli.php Home/Index/sys');
       var_dump($qq);
    }

    //redis操作
    public function redis(){
        $aa = M('cb_order_buy');
        $aa->order_upmoney = 0.002;
        $aa->order_stopless_price = 0.36646;
        $aa->where("id = 5")->save();
        var_dump($aa);exit;
    }
}