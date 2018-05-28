<?php
namespace Home\Controller;
use JYS\huobi\req;

class PlaceOrderController extends TradController
{

    public $costRatio = 0.0015;
    public $holdPos;    //当前持有
    public $holdAsset;  //当前持有币的数量
    public $objPos;    //目标仓位
    public $objAsset;  //目标持有币的数量
    public $order_money=[];  //下单价格
    //public $stopLessMoney=[];  //止损价格
    public $maxUnitRatio = -1;
    public $minUnitRatio = -1;
    //public $upMoney=[];     //最高价格 每个币一个最高价
    public $maxSpread = 0.01;
    public $allowedTradeTime = 300;
    public $upMoney;    //最高价
    public $stopLessMoney;    //止损价

    public function __construct($system, $params)
    {
        parent::__construct($system, $params);
        if (isset($system['costRatio'])) {
            $this->costRatio = $system['costRatio'];
        }
        if (isset($params['maxUnitRatio'])) {
            $this->maxUnitRatio = $params['maxUnitRatio'];
        }
        if (isset($params['minUnitRatio'])) {
            $this->minUnitRatio = $params['minUnitRatio'];
        }
    }
    public function normal_money($money, $sym) {
        $amp = $this->price_precision[$sym];
        $pos = strpos($money, '.');
        return substr($money, 0, $pos+$amp+1);
    }
    
    public function normal_amount($amount, $sym) {
        $amp = $this->amount_precision[$sym];
        $pos = strpos($amount, '.');
        return substr($amount, 0, $pos+$amp+1);
    }
    
    public function trade($strName, $ti, $needBase, $needAsset, $symbol, $reverse, $estprice, $forceOrder) {

        $klineLast = count($this->kline_datas) - 1;

        /*
         * 下单
         * minUnit: 下单的最小份额。如果要买卖的量小于这个份额，直接吃市场挂单
         * maxUnit: 单个下单的最大份额。不能一次挂大于此份额的单，避免扰乱市场
         * 
         */
        if(!$this->is_live) {
            return [$this->objPos, $this->objAsset];
        }
        print('enter here'."\n");
//        var_dump($this->holdPos);
//        var_dump($this->objPos);
        $pidArr = [];
        for ($i=1; $i<=$this->assetLen; $i++) {
            $pid = pcntl_fork();
            if ($pid==-1) {
                die('could not fork');
            } else if($pid) {                
                $pidArr[$i] = $pid;
            } else {
                
                //if($needBase[$i]==0 && $needAsset[$i]==0) exit;
                if($needBase[$i]>0) {
                    //减持base，购买asset，利用减持的base下单
                    if($reverse[$i]) {
                        //减持symbol，减持量为needbase
                        printf("base sell %f %s with price  %s\n", $needBase[$i], $symbol[$i], $estprice[$i]);
//                        exit;
                        $this->place_sell_order($symbol[$i], $needBase[$i], $estprice[$i], $i, $this->minUnitRatio, $this->maxUnitRatio);
                    } else {
                        //购买symbol，购买资产为needBase
                        printf("base buy worth %f %s with price  %s\n", $needBase[$i], $symbol[$i], $estprice[$i]);
//                        exit;
                        $this->place_buy_order($symbol[$i], $needBase[$i], $estprice[$i], $i, $this->minUnitRatio, $this->maxUnitRatio);
                    }
                } elseif($needAsset[$i]>0) {
                    //减持asset，购买base，利用减持的asset下单
                    if($reverse[$i]) {
                        //购买symbol，购买资产为needasset
                        printf("base buy worth %f %s with price %s\n", $needAsset[$i], $symbol[$i], $estprice[$i]);
//                        exit;
                        
                        $this->place_buy_order($symbol[$i], $needAsset[$i], $estprice[$i], $i, 
                            $this->minUnitRatio/$this->kline_datas[$klineLast][$i]['close'],
                            $this->maxUnitRatio/$this->kline_datas[$klineLast][$i]['close']);
                    } else {
                        //减持symbol，减持量为needasset
                        printf("base sell %f %s with price %s\n", $needAsset[$i], $symbol[$i], $estprice[$i]);
//                        exit;
                        
                        $this->place_sell_order($symbol[$i], $needAsset[$i], $estprice[$i], $i,
                            $this->minUnitRatio/$this->kline_datas[$klineLast][$i]['close'],
                            $this->maxUnitRatio/$this->kline_datas[$klineLast][$i]['close']);
                    }
                }
                //此币种下单结束，子进程退出
                exit;
            }
        }
        while (count($pidArr) > 0) {
            foreach ($pidArr as $key => $pid) {
                $myId = pcntl_waitpid($pid, $status);
                printf("ps %s vs %s", $myId, $pid);
                if ($myId == $pid) {
                    unset($pidArr[$key]);
                    printf("order for %s complete\n", $this->assetName[$key]);
                }
            }
        }
        
        printf("all order completed\n");
        //update now asset
        //可能一些交易变化，手续费等等，从接口获取当前的仓位
        $balance = $this->crontab->getAccountIdBalance();
        for ($i=0; $i<=$this->assetLen; $i++) {
            $this->objAsset[$i] = $balance[$this->assetName[$i]]['trade'];
        }
        $this->updateObjPosFromAsset();
    } 

    public function place_buy_order($sym1,$sym2, $money, $estp, $force, $assetId, $minUnitMoney, $maxUnitMoney,$stop_ratio,$stop_ratio2,$genre,$sell_price) {
        $this->commonSymbols($sym1,$sym2);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        $remainMoney = $money;
        $begtime = time();
        while($remainMoney>0 || count($orderqueue)>0) {
            $ntime = time();
            if($ntime-$begtime)
            if(!$force && $ntime-$begtime>$this->allowedTradeTime) {
                printf("超过%s时间内没有完成交易，本次交易放弃", $this->allowedTradeTime);
                break;
            }
            $orderdepth = $this->get_depth_data2($sym,$ntime,$assetId, 5);
            $first_askprice = $orderdepth['asks'][0][0];
            $first_askvol = $orderdepth['asks'][0][1];
            $first_bidprice = $orderdepth['bids'][0][0];
            $first_bidvol = $orderdepth['bids'][0][1];
            if(!$force && $first_askprice/$estp>1+$this->maxSpread) {
                sleep(1);
                continue;
            }
            $orderPrice = $first_askprice+$minPrice;
            //先把单全挂出去
            while($remainMoney<$maxUnitMoney) {    //撤单
                if(count($orderqueue)==0) break; 
                //$poporder = array_shift($orderqueue);
                $poporder = $orderqueue[0];   //获取订单id
                $val_order = $this->getOrder($poporder); 
                if($val_order['status'] != 'ok'){
                    printf("查询订单失败：%s",$poporder);
                    continue;
                }
                if($val_order['data']['state']=='filled') {   //完全成交  跳出循环
                    break;
                    //continue;
<<<<<<< HEAD
                }
                //没有完全成交  可以撤单  比较当前订单是否是最高价  是 不需要撤销  不是  撤销订单
                if($val_order['data']['price'] >= $first_askprice){  //最高价  不需要撤单操作
                       $stat_type = 1;
                       break;      //跳出本次循环  不再下单
                }
=======
                }
                //没有完全成交  可以撤单  比较当前订单是否是最高价  是 不需要撤销  不是  撤销订单
                if($val_order['data']['price'] >= $first_askprice){  //最高价  不需要撤单操作
                       $stat_type = 1;
                       break;      //跳出本次循环  不再下单
                }
>>>>>>> 8cf79caa0432eb6749cf91983e65fb78a419a766
                //撤销订单
                $ret = $this->getSubmitcancel($poporder);
                if($ret<0) {
                    printf("提交撤单请求失败: %s \n", $poporder);
                    continue;
                }
                //查询订单接口
                for($i=1;$i<3;$i++){
                    sleep(1);
                    $val_order = $this->getOrder($poporder);
                    if($val_order['status'] != 'ok'){
                        continue;
<<<<<<< HEAD
                    }    //跳出循环
=======
                    }
>>>>>>> 8cf79caa0432eb6749cf91983e65fb78a419a766
                    if($val_order['data']['state'] == 'canceled') {  //撤销成功
                        $remainMoney += $val_order['data']['amount']*$val_order['data']['price']-$val_order['data']['field-cash-amount'];
                        array_shift($orderqueue);
                        break;
                    } elseif ($val_order['data']['state']=='filled'){  //完全成交
                        break;
                    }else{
                    }
                }
            }
            if($stat_type == 1){
                continue;
            }
            if($remainMoney<$minUnitMoney) {    //市价下单
                $amount = $remainMoney/$orderPrice;
                $amount = $this->normal_amount($amount, $sym1);
                if($amount==0) {
                    break;
                }
                $nMoney = $this->normal_money($remainMoney, $sym1);
                if($nMoney==0) {
                    printf("剩下的钱 %s 不够下单\n", $nMoney);
                    break;
                }
                $this->crontab = new \Home\Controller\CrontabController();
                $orderId = $this->crontab->getPlace($nMoney, $sym, 'buy-market', $orderPrice, 'api');
                   if($orderId<0) {
                       printf("order fail");
                   } else {
                       array_push($orderqueue,  $orderId);
                   }
                   break;
            } else {
                $orderMoney = 0.5*$remainMoney;
                if($orderMoney>$maxUnitMoney) {
                    $orderMoney = $maxUnitMoney;
                }
                $amount = $orderMoney/$orderPrice;   //需要买的数量
                $amount = $this->normal_amount($amount, $sym1);
                $this->crontab = new \Home\Controller\CrontabController();
                $orderId = $this->crontab->getPlace($amount, $sym, 'buy-limit', $orderPrice, 'api');
                 if($orderId<0) {
                     printf("order fail");
                 } else {
                     array_push($orderqueue,  $orderId);
                     $remainMoney -= $amount*$orderPrice; 
                 }
<<<<<<< HEAD
            }
                
        }
        sleep(2);
        //平均价格
        $details_order = $this->order_details($orderqueue);
        
        //设置最高价   止损价  初始订单价
        $add['order_upmoney'] = $details_order['average_price'];
        $add['order_stopmoney'] = $details_order['average_price'];
        $add['order_stopless_price'] = 0;
        $add['sym1']          = $sym1;
        $add['sym2']          = $sym2;
        $add['data']          = time();
        $add['order_num']     = $details_order['amount_num'];
        $add['ratio1']        = $stop_ratio;   
        $add['ratio2']        = $stop_ratio2;
        $add['status']        = 1;
        $add['maxunitmoney']  = $maxUnitMoney;
        $add['minunitmoney']  = $minUnitMoney;
        $add['estp']          = $estp;
        $add['genre']         = $genre;
        $add['sell_price']    = $sell_price;
        $result = M('cb_order_buy')->add($add);
    }

    /*
     * 查询订单详情
     */
    public function order_details($arr){
        $obj = A('Index');
        $order_average_price = $obj->getOrder($arr);
        return $order_average_price;
    }

    /*
     *   获取订单详情
     */
    public function getOrder($order_id){
        $req = new req();
        $result = $req->get_order($order_id);
        return $result;
    }

    //撤销订单   3056267732
    public function getSubmitcancel($order_id){
        $req = new req();
        $result = $req->cancel_order($order_id);
        return $result;
    }

    /*
     *  止损操作
     */
    public function stopPrice($result){
       // $result = M('cb_order_buy')->where("status = 1")->order('data DESC')->find();
        if(empty($result)){
            $str = '暂无数据'."\n";
            return $str;
        }
        //获取当前时间的最新一条数据
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误';
            return $str;
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        echo $closeMoney."\n";
        if($result['sell_price'] > 0 && $result['sell_price'] < $closeMoney){
            //到指定价格  卖出
             $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['estp'], FALSE,2697688, $result['minunitmoney'],$result['maxunitmoney'],$result['id']);
             return ;
        }
        if($closeMoney >= $result['order_upmoney']){
            $this->upMoney = $closeMoney;
            //计算增长的比例
            $proportion = ($closeMoney - $result['order_stopmoney']) / $result['order_stopmoney'];
            if ($proportion < $result['ratio1']){
                $this->stopLessMoney = $this->upMoney * $result['ratio2'];
            } elseif ($proportion < $result['retio1']+0.1){
                $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.025);
            }else{
                $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.05);
            }
            sleep(10);
            echo $this->upMoney."\n";
            echo $this->stopLessMoney."\n";
            $up = M('cb_order_buy');
            $up->order_upmoney = $this->upMoney;
            $up->order_stopless_price = $this->stopLessMoney;
            $up->where("id = {$result['id']}")->save();
            
            //$str = 1;
        } elseif ($closeMoney < $result['order_stopless_price']){
            $str = '闭盘价格已经小于止损价格,建议卖出';
            echo $str;
            echo $result['id'];
            //执行卖单操作   成功后将数据库中状态  status=0
            $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['estp'], FALSE,2697688, $result['minunitmoney'],$result['maxunitmoney'],$result['id']);

        }
        return $str;
    }


    /*
     *  按币的数量计算止损
     */
    public function bt_num_stopPrice($result){
        if(empty($result)){
            $str = '暂无数据'."\n";
            return $str;
        }
        //获取当前时间的最新一条数据
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误';
            return;
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        //$result = M('cb_order_buy')->order('data DESC')->find();
        if($closeMoney < $result['order_upmoney']){
            //止损价格
            $proportion = ($result['order_stopmoney'] - $closeMoney) / $result['order_stopmoney'];
            if ($proportion < $result['ratio1']){
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - $result['ratio2']);
                $this->upMoney = $this->stopLessMoney;
            } elseif ($proportion < $result['ratio1'] + 0.1){
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ratio2'] - 0.025));//0.075;
                $this->upMoney = $this->stopLessMoney;
            }else{
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ratio2'] - 0.5));//0.1;
                $this->upMoney = $this->stopLessMoney;
            }
            $up = M('cb_order_buy');
            $up->order_upmoney = $this->upMoney;
            $up->order_stopless_price = $this->stopLessMoney;
            $up->where("id = {$result['id']}")->save();
        } elseif ($closeMoney > $this->upMoney){
            $str = '建议买入';  //下单之后将该订单取消
        }
        return $str;
    }

     /*
      *  监听项
     */
     public function listening(){
        $result = M('cb_order_buy')->where("status = 1")->order('data DESC')->find();
        if($result['genre'] == 1){
            //钱
            $this->stopPrice($result);
        }else{
            //币
            $this->bt_num_stopPrice($result);
        }
     }

=======
            }
                
        }
        sleep(2);
        //平均价格
        $details_order = $this->order_details($orderqueue);
        
        //设置最高价   止损价  初始订单价
        $add['order_upmoney'] = $details_order['average_price'];
        $add['order_stopmoney'] = $details_order['average_price'];
        $add['order_stopless_price'] = 0;
        $add['sym1']          = $sym1;
        $add['sym2']          = $sym2;
        $add['data']          = time();
        $add['order_num']     = $details_order['amount_num'];
        $add['ratio1']        = $stop_ratio;   
        $add['ratio2']        = $stop_ratio2;
        $add['status']        = 1;
        $add['maxunitmoney']  = $maxUnitMoney;
        $add['minunitmoney']  = $minUnitMoney;
        $add['estp']          = $estp;
        $add['genre']         = $genre;
        $add['sell_price']    = $sell_price;
        $result = M('cb_order_buy')->add($add);
    }

    /*
     * 查询订单详情
     */
    public function order_details($arr){
        $obj = A('Index');
        $order_average_price = $obj->getOrder($arr);
        return $order_average_price;
    }

    /*
     *   获取订单详情
     */
    public function getOrder($order_id){
        $req = new req();
        $result = $req->get_order($order_id);
        return $result;
    }

    //撤销订单   3056267732
    public function getSubmitcancel($order_id){
        $req = new req();
        $result = $req->cancel_order($order_id);
        return $result;
    }

    /*
     *  止损操作
     */
    public function stopPrice($result){
       // $result = M('cb_order_buy')->where("status = 1")->order('data DESC')->find();
        if(empty($result)){
            $str = '暂无数据'."\n";
            return $str;
        }
        //获取当前时间的最新一条数据
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误';
            return $str;
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        echo $closeMoney."\n";
        if($result['sell_price'] > 0 && $result['sell_price'] < $closeMoney){
            //到指定价格  卖出
             $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['estp'], FALSE,2697688, $result['minunitmoney'],$result['maxunitmoney'],$result['id']);
             return ;
        }
        if($closeMoney >= $result['order_upmoney']){
            $this->upMoney = $closeMoney;
            //计算增长的比例
            $proportion = ($closeMoney - $result['order_stopmoney']) / $result['order_stopmoney'];
            if ($proportion < $result['ratio1']){
                $this->stopLessMoney = $this->upMoney * $result['ratio2'];
            } elseif ($proportion < $result['retio1']+0.1){
                $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.025);
            }else{
                $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.05);
            }
            sleep(10);
            echo $this->upMoney."\n";
            echo $this->stopLessMoney."\n";
            $up = M('cb_order_buy');
            $up->order_upmoney = $this->upMoney;
            $up->order_stopless_price = $this->stopLessMoney;
            $up->where("id = {$result['id']}")->save();
            
            //$str = 1;
        } elseif ($closeMoney < $result['order_stopless_price']){
            $str = '闭盘价格已经小于止损价格,建议卖出';
            echo $str;
            echo $result['id'];
            //执行卖单操作   成功后将数据库中状态  status=0
            $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['estp'], FALSE,2697688, $result['minunitmoney'],$result['maxunitmoney'],$result['id']);

        }
        return $str;
    }


    /*
     *  按币的数量计算止损
     */
    public function bt_num_stopPrice($result){
        if(empty($result)){
            $str = '暂无数据'."\n";
            return $str;
        }
        //获取当前时间的最新一条数据
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误';
            return;
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        //$result = M('cb_order_buy')->order('data DESC')->find();
        if($closeMoney < $result['order_upmoney']){
            //止损价格
            $proportion = ($result['order_stopmoney'] - $closeMoney) / $result['order_stopmoney'];
            if ($proportion < $result['ratio1']){
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - $result['ratio2']);
                $this->upMoney = $this->stopLessMoney;
            } elseif ($proportion < $result['ratio1'] + 0.1){
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ratio2'] - 0.025));//0.075;
                $this->upMoney = $this->stopLessMoney;
            }else{
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ratio2'] - 0.5));//0.1;
                $this->upMoney = $this->stopLessMoney;
            }
            $up = M('cb_order_buy');
            $up->order_upmoney = $this->upMoney;
            $up->order_stopless_price = $this->stopLessMoney;
            $up->where("id = {$result['id']}")->save();
        } elseif ($closeMoney > $this->upMoney){
            $str = '建议买入';  //下单之后将该订单取消
        }
        return $str;
    }

     /*
      *  监听项
     */
     public function listening(){
        $result = M('cb_order_buy')->where("status = 1")->order('data DESC')->find();
        if($result['genre'] == 1){
            //钱
            $this->stopPrice($result);
        }else{
            //币
            $this->bt_num_stopPrice($result);
        }
     }

>>>>>>> 8cf79caa0432eb6749cf91983e65fb78a419a766
     /*
     *  监听止损   $sym,$num,$num2
     */
    public function monitor(){
        //$return = $this->stopPrice();
             $i = 0;
             while (true) {
                $pid = pcntl_fork();//$this->db(0);
                if ($pid == - 1) {
                    die('could not fork');
                } elseif ($pid) {
                    $pidArr[] = $pid;   //父进程
                } else {
                     M()->db(0,"",true);
                     $return = $this->listening();  //监听    $sym,$num,$num2
                      // if($return != 1){
                      //     $add2['cause'] = $return;
                      //     $add2['data']  = time();
                      //     $add2['status'] = 1;
                      //     M('cb_order_cause')->add($add2);
                      // }
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
    
    public function place_sell_order($sym1,$sym2, $assetNum, $estp, $force, $assetId, $minUnitMoney, $maxUnitMoney,$order_id) {
        $this->commonSymbols($sym1,$sym2);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        $remainAsset = $assetNum;
        $begtime = time();
        while($remainAsset>0 || count($orderqueue)>0) {
            $ntime = time();
             if($ntime-$begtime)
                 if(!$force && $ntime-$begtime>$this->allowedTradeTime) {
                     printf("超过%s时间内没有完成交易，本次交易放弃", $this->allowedTradeTime);
                     break;
                  }

            $orderdepth = $this->get_depth_data2($sym,$ntime,$assetId, 5);
            $first_bidprice = $orderdepth['bids'][0][0];
            $first_bidvol = $orderdepth['bids'][0][1];
            if(!$force && $first_bidprice/$estp<1-$this->maxSpread) {
                //价差过大，休息一下再买
                sleep(1);
                continue;   //终止
            }
            $orderPrice = $first_bidprice-$minPrice;
            while($remainAsset<$minUnitMoney) {
                //钱太少，逐渐撤单，直至全部撤掉或者钱足够
                if(count($orderqueue)==0) break;   //无单可撤
                $poporder = array_shift($orderqueue);
                $val_order = $this->getOrder($poporder);
                if($val_order['status'] != 'ok'){
                    printf("查询订单失败：%s",$poporder);
                    continue;
                }
                if($val_order['data']['state']=='filled') {
                    continue;
                }
                $ret = $this->getSubmitcancel($poporder);
                if($ret<0) {
                    printf("提交撤单请求失败: %s \n", $poporder);
                    continue;
                }
                //查询订单接口
                for($i=1;$i<3;$i++){
                    sleep(1);
                    $val_order = $this->getOrder($poporder);
                    if($val_order['status'] != 'ok'){
                        continue;
                    }
                    if($val_order['data']['state'] == 'canceled') {
                        $remainAsset += $val_order['data']['amount']-$val_order['data']['field-amount'];
                        break;
                    } elseif ($val_order['data']['state']=='filled'){
                        break;
                    }else{
                    }
                }
            }
            if($remainAsset<$minUnitMoney) {
        
                //钱太少，直接下单
                $amount = $this->normal_amount($remainAsset, $sym1);
                if($amount==0) {
                    //剩下的钱都不够下单了，直接退出
                    break;
                }
                $this->crontab = new \Home\Controller\CrontabController();
                $orderId = $this->crontab->getPlace($amount, $sym, 'sell-market', $orderPrice, 'api');
                if($orderId < 0){
                    echo '交易失败';
                    break;
                }
                break;
            } else {
                $orderAsset = 0.5*$remainAsset;
                if($orderAsset>$maxUnitMoney) {
                    $orderAsset = $maxUnitMoney;
                }
                $amount = $this->normal_amount($orderAsset, $sym1);
                //$amount = 0.8;
                $this->crontab = new \Home\Controller\CrontabController();
                $orderId = $this->crontab->getPlace($amount, $sym, 'sell-limit', $orderPrice, 'api');
                if($orderId<0) {
                    printf("order fail");
                } else {
                    array_push($orderqueue,  $orderId);
                    $remainAsset -= $amount;
                }
            }
            sleep(1);
        }
        $save['status'] = 0;
        M('cb_order_buy')->where("id = {$order_id}")->save($save);
    }
    
}
