<<<<<<< HEAD
<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class BuyOrderController extends Controller
{
    public $order_price;
    public $upMoney;    //最高价
    public $stopLessMoney;    //止损价
    public $symbol;
    public $price_precision;
    public $amount_precision;
    public $jump=0;
    public $once_num;



    




   public function index(){
       if(IS_POST){
            $symbol = explode('-',I('sym'));
            $sym1 = $symbol[0];
            $sym2 = $symbol[1];
            $zNum = I('zNum');
            $once_num = I('once_num');
            $minUnitMoney = I('minUnitMoney');
            $maxUnitMoney = I('maxUnitMoney');
            $stop_ratio1  = I('stop_ratio')?I('stop_ratio'):0.15;
            $stop_ratio2  = I('stop_ratio2')?I('stop_ratio2'):0.95;
            $genre        = I('genre')?I('genre'):1;
            $this->buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$stop_ratio1,$stop_ratio2,$genre);
       }else{
            $this->display();
       }
   }


   //下单操作
   public function buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$stop_ratio1,$stop_ratio2,$genre){
        $this->once_num = $once_num;
        $this->commonSymbols($sym1,$sym2);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        echo $minPrice;
        while($zNum > 0){   //购买总数量大于0  继续购买
             //获取当前最新的挂单数据
            $order_depth = $this->get_depth_data2($sym);
            $first_askprice = $order_depth['asks'][0][0];
            //计算要买的价格
            $order_price = $first_askprice + $minPrice;
            if($order_price > $maxUnitMoney && $order_price < $minUnitMoney){
                //价格超出规定的范围
                break;
            }
            while(!empty($orderqueue)){  //撤单操作
                $order_id = $orderqueue[count($orderqueue) - 1];
                $val_order = $this->getOrder($order_id);
                if($val_order['status'] != 'ok'){
                    var_dump('查询订单状态错误');
                    continue;
                }
                if($val_order['data']['state'] == 'filled'){   //订单完全成交  跳出循环  继续下单
                    break;
                }
                //判断当前订单的价格是否是当前最大价格
                if($val_order['data']['price'] >= $order_price){  //为最大价格  不再下单
                    $this->jump = 1;
                    break;
                }
                $repal_order = $this->getSubmitcancel($order_id);
                if($repal_order<0){
                    var_dump('撤销订单失败');
                    continue;
                }
                //查询订单状态  执行操作状态后
                for($i=0;$i<3;$i++){
                    sleep(1);
                    $val_order = $this->getOrder($order_id);
                    if($val_order['status'] != 'ok'){
                        var_dump('查询订单状态错误2');
                        continue;
                    };
                    if($val_order['data']['state'] == 'filled'){  //订单完全成交
                        //跳出循环   继续执行下单操作
                        break;
                    } elseif ($val_order['data']['state'] == 'canceld'){  //撤销成功
                        //删除该订单号
                        $orderqueue = array_diff($orderqueue,[$order_id]);
                        //总数量 + 下单数量
                        $zNum = $zNum + ($val_order['data']['amount'] - $val_order['data']['field-amount']);
                        $save['order_num'] = $val_order['data']['field-amount'];
                        $where['order_id'] = $order_id;
                        $where['status']   = 1;
                        M("cb_buy_order")->where($where)->save($save);
                        break;
                    }
                }
            }
            if($this->jump == 1){
                $this->jump = 0;
                continue;
            }
            //下单操作
            if($once_num > 0){   //限价下单
                if($once_num > $zNum){
                    $once_num = $zNum;
                }
                $crontab = new \Home\Controller\CrontabController();
                $orderId = $crontab->getPlace($once_num, $sym, 'buy-limit', $order_price, 'api');
                if($orderId < 0){
                    $str = $orderId['err-msg'];
                    echo $str;
                }else{
                    array_push($orderqueue,$orderId);
                    $zNum -= $once_num;
                    //将之前的订单状态修改为0

                    //保存订单
                    $add['order_num'] = $once_num;
                    $add['order_id']  = $orderId;
                    $add['symbol']    = $sym;
                    $add['bt_type']   = 'buy-limit';
                    $add['status']    = 1;
                    $add['order_price'] = $order_price;
                    M('cb_buy_order')->add();
                }
            }
        }

        //如果是市价下单   下单价格请求订单的状态获取     如果是限价下单  直接请求数据库

        //不包含市价单
        $where2['status'] = 1;
        $where2['order_num'] = ['gt',0];
        $result = M('cb_buy_order')->where($where2)->field("order_num,order_price")->select();
        if(empty($result)){ return ; }
        $val_order_num = array_column($result,'order_num');
        $val_order_price = array_column($result,'order_price');

        $add2['order_upmoney'] = array_sum($val_order_price) / count($val_order_price);
        $add2['order_stopmoney'] = array_sum($val_order_price) / count($val_order_price);
        $add2['order_stopless_price'] = 0;
        $add2['sym1'] = $sym1;
        $add2['sym2'] = $sym2;
        $add2['data'] = time();
        $add2['order_num'] = array_sum($val_order_num);
        $add2['ratio1'] = $stop_ratio1;
        $add2['ratio2'] = $stop_ratio2;
        $add2['status'] = 1;
        $add2['maxunitmoney'] = $maxUnitMoney;
        $add2['minunitmoney'] = $minUnitMoney;
        $add2['genre']        = $genre;
        $add2['once_num']     = $this->once_num;
        M('cb_order_buy')->add($add2);
        M('cb_buy_order')->where("status = 1")->save("status = 0");
   }


   /*
    *   卖单操作
    */
   public function place_sell_order($sym1,$sym2,$order_num,$once_num,$minunitmoney,$maxunitmoney,$id,$type=''){
        $this->commonSymbols($sym1,$sym2);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        while($order_num > 0){
            $order_depth = $this->get_depth_data2($sym);
            $first_bidprice = $order_depth['bids'][0][0];
            $orderPrice = $first_bidprice-$minPrice;
            if(!empty($type)){
                if($order_price > $maxUnitMoney && $order_price < $minUnitMoney){
                    //价格超出规定的范围
                    break;
                }
            }
            echo $orderPrice;
            while(!empty($orderqueue)){  //撤单操作
                $order_id = $orderqueue[count($orderqueue) - 1];
                $val_order = $this->getOrder($order_id);
                if($val_order['status'] != 'ok'){
                    var_dump('请求订单详情错误');
                    continue;
                }
                if($val_order['data']['state'] == 'filled'){
                    break;
                }
                if($val_order['data']['price'] >= $orderPrice){
                    var_dump('当前为最大价格，无需撤单');
                    $this->jump = 1;
                    break;
                }

                $repal_order = $this->getSubmitcancel($order_id);
                if($repal_order<0){
                    var_dump('撤销订单失败');
                    continue;
                }
                //查询订单状态  执行操作状态后
                for($i=0;$i<3;$i++){   
                    sleep(1);
                    $val_order = $this->getOrder($order_id);
                    if($val_order['status'] != 'ok'){
                        var_dump('查询订单状态错误2');
                        continue;
                    };
                    if($val_order['data']['state'] == 'filled'){  //订单完全成交
                        //跳出循环   继续执行下单操作
                        $this->jump = 1;
                        break;
                    } elseif ($val_order['data']['state'] == 'canceld'){  //撤销成功
                        //删除该订单号
                        $orderqueue = array_diff($orderqueue,[$order_id]);
                        //总数量 + 下单数量
                        $order_num = $order_num + ($val_order['data']['amount'] - $val_order['data']['field-amount']);
                        // $save['order_num'] = $val_order['data']['field-amount'];
                        // $where['order_id'] = $order_id;
                        // $where['status']   = 1;
                        // M("cb_buy_order")->where($where)->save($save);
                        // break;
                    }
                }
            }
            if($this->jump == 1){
                $this->jump = 0;
                continue;
            }
            //下单操作
            if($once_num > 0){
                if($once_num > $order_num){
                    $once_num = $order_num;
                }
                $crontab = new \Home\Controller\CrontabController();
                $orderId = $crontab->getPlace($once_num, $sym, 'sell-limit', $order_price, 'api');
                if($orderId < 0){
                    var_dump('卖单失败');
                }else{
                    array_push($orderqueue,$orderId);
                    $order_num -= $once_num;
                }
            }
        }
        $sell_save['status'] = 0;
        $sell_where['id'] = $id;
        M('cb_order_buy')->where($sell_where)->save($sell_save);
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
                     M()->db(0,"",true);
                     $return = $this->listening();  //监听 
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


    /*
      *  监听项
     */
     public function listening(){
        var_dump(8989);
        $result = M('cb_order_buy')->where("status = 1")->order('data DESC')->find();
        var_dump($result);exit;
        if(empty($result)){
            $str = "暂无数据操作\n";
            return $str;
        }
        if($result['genre'] == 1){
            //钱
            $this->stopPrice($result);
        }else{
            //币
            $this->bt_num_stopPrice($result);
        }
     }


     /*
     *  止损操作
     */
    public function stopPrice($result){
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
            $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id']);

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
     *   获取精度
     */
    public function commonSymbols($sym,$sym2){
        $crontab = new \Home\Controller\CrontabController();
        $commonSymbols = $crontab->getCommonSymbols();
        foreach($commonSymbols as $k=>$v){
            if($sym == $v['base-currency'] && $sym2 == $v['quote-currency']){
                $this->price_precision[$sym] = $v['price-precision'];
                $this->amount_precision[$sym] = $v['amount-precision'];
            }
        }
    }

    //获取当前最新的depth
    public function get_depth_data2($sym,$depth="10"){
         // 实时获取数据
        $req = new req();
        $val_data = $req->get_market_depth($sym, 'step0');
        // 分别中bids 和 asks中 获取$depth个
        $arr_val['bids'] = array_slice($val_data['tick']['bids'], 0, $depth);
        $arr_val['asks'] = array_slice($val_data['tick']['asks'], 0, $depth);
        
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['bids']), SORT_DESC, $arr_val['bids']);
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['asks']), SORT_ASC, $arr_val['asks']);
        return $arr_val;
    }

    /*
     *   获取订单详情
     */
    public function getOrder($order_id){
        $req = new req();
        $result = $req->get_order($order_id);
        return $result;
    }


    //撤销订单
    public function getSubmitcancel($order_id){
        $req = new req();
        $result = $req->cancel_order($order_id);
        return $result;
    }


    //查看个人账户资产
    public function getAccoundId(){
        $req = new req();
        $result = $req->get_account_balance();
        if($result['status'] != 'ok'){
            var_dump('数据请求错误');exit;
        }
        foreach($result['data']['list'] as $k=>$v){
            if($v['type'] == 'trade' && $v['balance'] > 0){
                $arr[] = $v;
            }
        }
        var_dump($arr);exit;
    }
}
=======
<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class BuyOrderController extends Controller
{
    public $order_price;
    public $upMoney;    //最高价
    public $stopLessMoney;    //止损价
    public $symbol;
    public $price_precision;
    public $amount_precision;
    public $jump=0;
    public $once_num;



    




   public function index(){
       if(IS_POST){
            $symbol = explode('-',I('sym'));
            $sym1 = $symbol[0];
            $sym2 = $symbol[1];
            $zNum = I('zNum');
            $once_num = I('once_num');
            $minUnitMoney = I('minUnitMoney');
            $maxUnitMoney = I('maxUnitMoney');
            $stop_ratio1  = I('stop_ratio')?I('stop_ratio'):0.15;
            $stop_ratio2  = I('stop_ratio2')?I('stop_ratio2'):0.95;
            $genre        = I('genre')?I('genre'):1;
            $this->buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$stop_ratio1,$stop_ratio2,$genre);
       }else{
            $this->display();
       }
   }


   //下单操作
   public function buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$stop_ratio1,$stop_ratio2,$genre){
        $this->once_num = $once_num;
        $this->commonSymbols($sym1,$sym2);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        echo $minPrice;
        while($zNum > 0){   //购买总数量大于0  继续购买
             //获取当前最新的挂单数据
            $order_depth = $this->get_depth_data2($sym);
            $first_askprice = $order_depth['asks'][0][0];
            //计算要买的价格
            $order_price = $first_askprice + $minPrice;
            if($order_price > $maxUnitMoney && $order_price < $minUnitMoney){
                //价格超出规定的范围
                break;
            }
            while(!empty($orderqueue)){  //撤单操作
                $order_id = $orderqueue[count($orderqueue) - 1];
                $val_order = $this->getOrder($order_id);
                if($val_order['status'] != 'ok'){
                    var_dump('查询订单状态错误');
                    continue;
                }
                if($val_order['data']['state'] == 'filled'){   //订单完全成交  跳出循环  继续下单
                    break;
                }
                //判断当前订单的价格是否是当前最大价格
                if($val_order['data']['price'] >= $order_price){  //为最大价格  不再下单
                    $this->jump = 1;
                    break;
                }
                $repal_order = $this->getSubmitcancel($order_id);
                if($repal_order<0){
                    var_dump('撤销订单失败');
                    continue;
                }
                //查询订单状态  执行操作状态后
                for($i=0;$i<3;$i++){
                    sleep(1);
                    $val_order = $this->getOrder($order_id);
                    if($val_order['status'] != 'ok'){
                        var_dump('查询订单状态错误2');
                        continue;
                    };
                    if($val_order['data']['state'] == 'filled'){  //订单完全成交
                        //跳出循环   继续执行下单操作
                        break;
                    } elseif ($val_order['data']['state'] == 'canceld'){  //撤销成功
                        //删除该订单号
                        $orderqueue = array_diff($orderqueue,[$order_id]);
                        //总数量 + 下单数量
                        $zNum = $zNum + ($val_order['data']['amount'] - $val_order['data']['field-amount']);
                        $save['order_num'] = $val_order['data']['field-amount'];
                        $where['order_id'] = $order_id;
                        $where['status']   = 1;
                        M("cb_buy_order")->where($where)->save($save);
                        break;
                    }
                }
            }
            if($this->jump == 1){
                $this->jump = 0;
                continue;
            }
            //下单操作
            if($once_num > 0){   //限价下单
                if($once_num > $zNum){
                    $once_num = $zNum;
                }
                $crontab = new \Home\Controller\CrontabController();
                $orderId = $crontab->getPlace($once_num, $sym, 'buy-limit', $order_price, 'api');
                if($orderId < 0){
                    $str = $orderId['err-msg'];
                    echo $str;
                }else{
                    array_push($orderqueue,$orderId);
                    $zNum -= $once_num;
                    //将之前的订单状态修改为0

                    //保存订单
                    $add['order_num'] = $once_num;
                    $add['order_id']  = $orderId;
                    $add['symbol']    = $sym;
                    $add['bt_type']   = 'buy-limit';
                    $add['status']    = 1;
                    $add['order_price'] = $order_price;
                    M('cb_buy_order')->add();
                }
            }
        }

        //如果是市价下单   下单价格请求订单的状态获取     如果是限价下单  直接请求数据库

        //不包含市价单
        $where2['status'] = 1;
        $where2['order_num'] = ['gt',0];
        $result = M('cb_buy_order')->where($where2)->field("order_num,order_price")->select();
        if(empty($result)){ return ; }
        $val_order_num = array_column($result,'order_num');
        $val_order_price = array_column($result,'order_price');

        $add2['order_upmoney'] = array_sum($val_order_price) / count($val_order_price);
        $add2['order_stopmoney'] = array_sum($val_order_price) / count($val_order_price);
        $add2['order_stopless_price'] = 0;
        $add2['sym1'] = $sym1;
        $add2['sym2'] = $sym2;
        $add2['data'] = time();
        $add2['order_num'] = array_sum($val_order_num);
        $add2['ratio1'] = $stop_ratio1;
        $add2['ratio2'] = $stop_ratio2;
        $add2['status'] = 1;
        $add2['maxunitmoney'] = $maxUnitMoney;
        $add2['minunitmoney'] = $minUnitMoney;
        $add2['genre']        = $genre;
        $add2['once_num']     = $this->once_num;
        M('cb_order_buy')->add($add2);
        M('cb_buy_order')->where("status = 1")->save("status = 0");
   }


   /*
    *   卖单操作
    */
   public function place_sell_order($sym1,$sym2,$order_num,$once_num,$minunitmoney,$maxunitmoney,$id,$type=''){
        $this->commonSymbols($sym1,$sym2);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        while($order_num > 0){
            $order_depth = $this->get_depth_data2($sym);
            $first_bidprice = $order_depth['bids'][0][0];
            $orderPrice = $first_bidprice-$minPrice;
            if(!empty($type)){
                if($order_price > $maxUnitMoney && $order_price < $minUnitMoney){
                    //价格超出规定的范围
                    break;
                }
            }
            echo $orderPrice;
            while(!empty($orderqueue)){  //撤单操作
                $order_id = $orderqueue[count($orderqueue) - 1];
                $val_order = $this->getOrder($order_id);
                if($val_order['status'] != 'ok'){
                    var_dump('请求订单详情错误');
                    continue;
                }
                if($val_order['data']['state'] == 'filled'){
                    break;
                }
                if($val_order['data']['price'] >= $orderPrice){
                    var_dump('当前为最大价格，无需撤单');
                    $this->jump = 1;
                    break;
                }

                $repal_order = $this->getSubmitcancel($order_id);
                if($repal_order<0){
                    var_dump('撤销订单失败');
                    continue;
                }
                //查询订单状态  执行操作状态后
                for($i=0;$i<3;$i++){   
                    sleep(1);
                    $val_order = $this->getOrder($order_id);
                    if($val_order['status'] != 'ok'){
                        var_dump('查询订单状态错误2');
                        continue;
                    };
                    if($val_order['data']['state'] == 'filled'){  //订单完全成交
                        //跳出循环   继续执行下单操作
                        $this->jump = 1;
                        break;
                    } elseif ($val_order['data']['state'] == 'canceld'){  //撤销成功
                        //删除该订单号
                        $orderqueue = array_diff($orderqueue,[$order_id]);
                        //总数量 + 下单数量
                        $order_num = $order_num + ($val_order['data']['amount'] - $val_order['data']['field-amount']);
                        // $save['order_num'] = $val_order['data']['field-amount'];
                        // $where['order_id'] = $order_id;
                        // $where['status']   = 1;
                        // M("cb_buy_order")->where($where)->save($save);
                        // break;
                    }
                }
            }
            if($this->jump == 1){
                $this->jump = 0;
                continue;
            }
            //下单操作
            if($once_num > 0){
                if($once_num > $order_num){
                    $once_num = $order_num;
                }
                $crontab = new \Home\Controller\CrontabController();
                $orderId = $crontab->getPlace($once_num, $sym, 'sell-limit', $order_price, 'api');
                if($orderId < 0){
                    var_dump('卖单失败');
                }else{
                    array_push($orderqueue,$orderId);
                    $order_num -= $once_num;
                }
            }
        }
        $sell_save['status'] = 0;
        $sell_where['id'] = $id;
        M('cb_order_buy')->where($sell_where)->save($sell_save);
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
                     M()->db(0,"",true);
                     $return = $this->listening();  //监听 
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


    /*
      *  监听项
     */
     public function listening(){
        var_dump(8989);
        $result = M('cb_order_buy')->where("status = 1")->order('data DESC')->find();
        var_dump($result);exit;
        if(empty($result)){
            $str = "暂无数据操作\n";
            return $str;
        }
        if($result['genre'] == 1){
            //钱
            $this->stopPrice($result);
        }else{
            //币
            $this->bt_num_stopPrice($result);
        }
     }


     /*
     *  止损操作
     */
    public function stopPrice($result){
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
            $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id']);

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
     *   获取精度
     */
    public function commonSymbols($sym,$sym2){
        $crontab = new \Home\Controller\CrontabController();
        $commonSymbols = $crontab->getCommonSymbols();
        foreach($commonSymbols as $k=>$v){
            if($sym == $v['base-currency'] && $sym2 == $v['quote-currency']){
                $this->price_precision[$sym] = $v['price-precision'];
                $this->amount_precision[$sym] = $v['amount-precision'];
            }
        }
    }

    //获取当前最新的depth
    public function get_depth_data2($sym,$depth="10"){
         // 实时获取数据
        $req = new req();
        $val_data = $req->get_market_depth($sym, 'step0');
        // 分别中bids 和 asks中 获取$depth个
        $arr_val['bids'] = array_slice($val_data['tick']['bids'], 0, $depth);
        $arr_val['asks'] = array_slice($val_data['tick']['asks'], 0, $depth);
        
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['bids']), SORT_DESC, $arr_val['bids']);
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['asks']), SORT_ASC, $arr_val['asks']);
        return $arr_val;
    }

    /*
     *   获取订单详情
     */
    public function getOrder($order_id){
        $req = new req();
        $result = $req->get_order($order_id);
        return $result;
    }


    //撤销订单
    public function getSubmitcancel($order_id){
        $req = new req();
        $result = $req->cancel_order($order_id);
        return $result;
    }


    //查看个人账户资产
    public function getAccoundId(){
        $req = new req();
        $result = $req->get_account_balance();
        if($result['status'] != 'ok'){
            var_dump('数据请求错误');exit;
        }
        foreach($result['data']['list'] as $k=>$v){
            if($v['type'] == 'trade' && $v['balance'] > 0){
                $arr[] = $v;
            }
        }
        var_dump($arr);exit;
    }
}
>>>>>>> 8cf79caa0432eb6749cf91983e65fb78a419a766
