<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class QuanController extends Controller
{

    public $order_price;
    public $upMoney;    //最高价
    public $stopLessMoney;    //止损价
    public $symbol;
    public $price_precision;
    public $amount_precision;
    public $jump=0;
    public $jump_buy=0;
    public $once_num;
    public $add_my=1;
    public $buy_add_my=1;


   /*
     *  监听止损
     */
    public function monitor($symbol){
          if(!strpos($symbol,'-')){
            var_dump('格式输入错误:  ht-usdt');exit;
          }
          $sym_val = explode('-',$symbol);
          $sym1 = $sym_val[0];
          $sym2 = $sym_val[1];
             $i = 0;
             while (true) {
                $pid = pcntl_fork();
                if ($pid == - 1) {
                    die('could not fork');
                } elseif ($pid) {
                    $pidArr[] = $pid;   //父进程
                } else {
                    M()->db(0,"",true);
                    $return = $this->listening($sym1,$sym2);  //监听 
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
     public function listening($sym1,$sym2){
        //$result = M('cash_order_buy')->where("status = 1")->select();
        //查询指定币对的止损信息
        $where['sym1'] = $sym1;
        $where['sym2'] = $sym2;
        $where['status'] = 1;
        $result = M('order_buy','cash_')->where($where)->order('id DESC')->find();
       // var_dump($result);exit;
        if(empty($result)){
            $str = "暂无数据操作\n";
            var_dump($str);exit;
            return $str;exit;
        }
        if($result['stype'] == 'sell'){
          //卖单时  止损
          $this->bt_num_stopPrice($result);
          return ;
        }
        if($result['genre'] == 1){  
          //钱
          $this->stopPrice($result);
        }else{
          //币
          $this->bt_num_stopPrice($result);
        }
       // return $val_str;
     }


     /*
     *  止损操作
     */
    public function stopPrice($result){
        if(empty($result)){
            $str = '暂无数据'."\n";
            sleep(5);
            return $str;
        }
        $time = time();
        $req = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误'; 
            return $str;
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        echo "闭盘价格===".$closeMoney."\n";
        
        if($closeMoney >= $result['order_upmoney']){
            //  1:  0.975   2: 1-(1-0.975)*2  3: 1-(1-0.975)*3   4: 1-(1-0.975)*4
            //  1:  <=0.05  2: 0.05<??<=0.15  3: 0.15<??<=0.25   4:  >0.25     
          echo "====闭盘价格大于等于最高价格===\n";
            $this->upMoney = $closeMoney;
            $proportion = ($closeMoney - $result['order_stopmoney']) / $result['order_stopmoney'];
            echo "+++++计算增长的比例+++++\n\n";
            echo $proportion."\n\n";
             if($proportion <= $result['ratio1']){
               echo "11111\n\n";
                 $this->stopLessMoney = $this->upMoney * $result['ratio2'];

             } elseif ($result['ratio1'] < $proportion && $proportion <= $result['ratio1'] + 0.1){
                echo "222222\n\n";
                 $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.025);

             } elseif ($result['ratio1'] + 0.1 < $proportion && $proportion <= $result['ratio1'] + 0.2){
                echo "3333333\n\n";
                 $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.5);

             } elseif ($proportion > $result['ratio1'] + 0.2){
                 echo "444444\n\n";
                 $this->stopLessMoney = $this->upMoney * ($result['ratio2'] - 0.75);

             }

            sleep(3);
            $up = M('order_buy','cash_');
            $up->order_upmoney = $this->upMoney;
            $up->order_stopless_price = $this->stopLessMoney;
            $up->where("id = {$result['id']}")->save();
            
            //$str = 1;
        } elseif ($closeMoney < $result['order_stopless_price']){
            $str = '闭盘价格已经小于止损价格,建议卖出';
            echo $str;
            //执行卖单操作   成功后将数据库中状态  status=0
            $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['buy_order_id'],'',$result['uid']);   //没有价格区间

        }else{
        	$str = "====闭盘价格小于最大价格  大于止损价格===";        
          echo $str."\n\n";
        }
        M('buysell_cause','cash_')->add(["cause"=>"'{$str}'","symbol"=>$result['sym1'].$result['sym2'],"data"=>time()]);
        return $str;
    }

    /*
     *  监听下单
     */
    public function buy_sell_montior(){
            while (true) {
                $pid = pcntl_fork();

                if ($pid == - 1) {
                    die('could not fork');
                } elseif ($pid) {
                    $pidArr[] = $pid;   //父进程
                } else {
                    M()->db(0,"",true);
                    $return = $this->buy_sell();  //监听 
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
          }
    }


    /*
     * 买卖后台运行
     */
    public function buy_sell(){
        $result = M('sellbuy_order','cash_')->where("status = 5")->select();
       // var_dump($result);exit;
        if(empty($result)){
          echo "暂时没有要操作的数据\n";
          sleep(5);
          return ;
        }
        foreach($result as $v){
          if($v['buy_type'] == "buy"){
            //下单操作
            $this->buy_order($v['sym1'],$v['sym2'],$v['order_num'],$v['once_num'],$v['minunitmoney'],$v['maxunitmoney'],$v['stop_ratio1'],$v['stop_ratio2'],$v['genre'],$v['id'],$v['stopless'],$v['uid']);
          }else{
            //卖单操作
            $this->place_sell_order($v['sym1'],$v['sym2'],$v['order_num'],$v['once_num'],$v['minunitmoney'],$v['maxunitmoney'],'','',$v['id'],$v['uid'],$v['stopless'],$v['stop_ratio1'],$v['stop_ratio2'],$v['genre']);   //有价格区间
          }
        }
        sleep(2);
    }


    //买卖订单接口
    // public function buy_sell(){
    //   if(IS_POST){
    //         var_dump($_REQUEST);exit;
    //   }else{
    //     var_dump('使用post请求');
    //   }
    // }


     //下单操作
   public function buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$stop_ratio1,$stop_ratio2,$genre,$buysell_id="",$stopless,$uid){

        switch($stopless){
          case 1:
              $stop_less = 1;
              break;
          case 2:
              $stop_less = 2;
              break;
        }
        $this->once_num = $once_num;
        $this->commonSymbols($sym1,$sym2,$uid);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);

        while($zNum > 0){
              //手动终止程序
             $buy_sell_status = M('sellbuy_order','cash_')->where("id = {$buysell_id}")->getField('status');
             if($buy_sell_status == 0){
              $this->db_error("买单--强制关闭",$symbol);
              break;
             }

            //获取depth
            $order_depth = $this->get_depth_data2($sym,$uid);
            $order_price = $order_depth['first_bidprice'] + $minPrice;
            
            if($order_price == $order_depth['first_askprice']){
              echo "5555555555\n\n";
              $order_price = $order_depth['first_bidprice'];
            }


            if($order_price > $maxUnitMoney || $order_price < $minUnitMoney){
                echo "买单---价格超出规定的范围\n";
                $this->db_error('买单---价格超出规定的范围',$sym);
                continue ;
            }

            while(!empty($orderqueue)){  //撤单操作
               
               $val_stop = $this->buy_sell_order_status($orderqueue,$uid,$order_price,$sym,$zNum,$stop_less,'买单');
               var_dump($val_stop);
               

               $orderqueue = $val_stop['orderqueue'];
               $zNum       = $val_stop['zNum'];

               if($val_stop['code'] == 1){
                    break;
               } else {
                   continue;
               }
            }

            if($this->jump_buy == 1){
                var_dump(558585858585585282);
                $this->jump_buy = 0;
                continue;

            }else{
              //下单操作
              if($once_num > 0){

                  if($once_num > $zNum){
                      $once_num = $zNum;
                  }

                  $user_balance = $this->account_balance($sym2,$uid,$once_num,$order_price,$sym,'买单');
                  if($user_balance == 1){
                    break;
                  }

                  $orderId = $this->crontab($uid,$once_num,$sym,'buy-limit',$order_price,$uid);

                  //$crontab = new \Home\Controller\CrontabController($uid);
                  //$orderId = $crontab->getPlace($once_num, $sym, 'buy-limit', $order_price, 'api',$uid);

                  if($orderId['status'] == 'error'){
                      $str = $orderId['err-msg'];
                      $this->db_error('买单---下单接口报错',$sym);
                      echo $str;
                      break;
                  }else{
                      echo "ppppp".$zNum."ffffff\n";
                      echo "===opopop==".$once_num."==ooppopp==\n";

                      array_push($orderqueue,$orderId['data']);
                      $zNum = bcsub($zNum,$once_num,2);
                      echo "bbbbb".$zNum."\n\n";
                      $this->buy_add_my = 1;
                      //保存订单
                      $add['order_num'] = $once_num;          
                      $add['order_id']  = $orderId['data'];
                      $add['symbol']    = $sym;
                      $add['bt_type']   = 'buy-limit';
                      $add['status']    = $stop_less;
                      $add['order_price'] = $order_price;
                      $add['creat_time']  = time();
					            $add['buysell_id']  = $buysell_id;
                      M('buy_order','cash_')->add($add);
                      usleep(500000);
                  }
              }
            }
        }
        
        $result = $this->cash_buy_order($stop_less,$sym,'买单');
        
        if($result == 1){
          return ;
        }
		  
        //获取所有订单的id
    		if($stop_less == 1){  //需要止损
    			$str_id = $result['str_id'];
    			$add2['order_upmoney'] = $result['avg'];
    			$add2['order_stopmoney'] = $result['avg'];
    			$add2['order_stopless_price'] = $result['avg'] * (1 - $stop_ratio1);
    			$add2['sym1'] = $sym1;
    			$add2['sym2'] = $sym2;
    			$add2['data'] = time();
    			$add2['order_num'] = $result['order_num'];
    			$add2['ratio1'] = $stop_ratio1;
    			$add2['ratio2'] = $stop_ratio2;
    			$add2['status'] = $stop_less;    //1  止损  2 不止损
    			$add2['maxunitmoney'] = $maxUnitMoney;
    			$add2['minunitmoney'] = $minUnitMoney;
    			$add2['genre']        = $genre;
    			$add2['once_num']     = $this->once_num;
    			$add2['buy_order_id'] = $str_id;
          $add2['uid']          = $uid;
    			$val = M('order_buy','cash_')->add($add2);
        }

		    $stop_save['status'] = 3; 
			  $stop_where['status'] = $stop_less;
		    M('buy_order','cash_')->where($stop_where)->save($stop_save);
		    
        $this->db_error("买---下单成功----当前交易的平均价格为：{$result['avg']}----手续费:{$result['field_fees']}",$sym);

        if(!empty($buysell_id)){
          $save2['status'] = 0;
          M('sellbuy_order','cash_')->where("id = {$buysell_id}")->save($save2);
        }
    }

    //错误信息保存到数据库
    public function db_error($error_str,$symbol){
      $add['cause']  = $error_str;
      $add['symbol'] = $symbol;
      $add['data']   = time();
      M('buysell_cause','cash_')->add($add);
    }

   

    //查询订单状态 和 撤单操作
    public function buy_sell_order_status($orderqueue,$uid,$order_price,$symbol,$zNum,$stop_less,$buy_sell_type){

        $order_id = $orderqueue[count($orderqueue) - 1];

        $order_status = $this->getOrder($order_id,$uid);
        
        if($order_status['status'] != 'ok'){
          $this->db_error("{$buy_sell_type}--查询订单错误",$symbol);
          $arr['code'] = 0;
          $arr['zNum'] = $zNum;
          $arr['orderqueue'] = $orderqueue;
          return $arr;
        }

        if($order_status['data']['state'] == 'filled'){
          echo "{$buy_sell_type}--完成状态\n\n";
          //continue;
          $where['order_id'] = $order_id;
          $save2['field_fees'] = $order_status['data']['field-fees'];
          $this->field($save2,$where);
          //跳出循环
          $arr['code'] = 1;
          $arr['zNum'] = $zNum;
          $arr['orderqueue'] = $orderqueue;
          return $arr;
        }
        //卖单时   $order_status['data']['price'] <= $order_price
        //买单时   $order_status['data']['price'] >= $order_price
        switch($buy_sell_type){
          case '买单':
              $str_val = $order_status['data']['price'] >= $order_price;
              break;
          case '卖单':
              $str_val = $order_status['data']['price'] <= $order_price;
              break;
        }

        if($str_val){
        
          // if($this->buy_add_my == 1){
            $this->db_error("{$buy_sell_type}--当前为最大价格",$symbol);
          //}
          echo "{$buy_sell_type}--当前为最大价格\n\n";
          $this->buy_add_my = 0;
          $this->jump_buy   = 1;
          $arr['code'] = 1;
          $arr['zNum'] = $zNum;
          $arr['orderqueue'] = $orderqueue;
          return $arr;
        }

        $repol_order = $this->getSubmitcancel($order_id,$uid);

        for($i=0;$i<3;$i++){
          usleep(500000);

          $order_status = $this->getOrder($order_id,$uid);
         // $this->search_order_error($order_id,$sym);
          if($order_status['status'] != 'ok'){
            $this->db_error("{$buy_sell_type}--查询订单错误",$symbol);
            $code = 0;
            continue;
          }
          
          if($order_status['data']['state'] == 'filled'){
            echo "====撤销后循环查询后的订单状态=====\n\n";
            $where['order_id'] = $order_id;
            $save2['field_fees'] = $order_status['data']['field-fees'];
            $this->field($save2,$where);
            $code = 1;
            break;
          } elseif ($order_status['data']['state'] == 'canceled' || $order_status['data']['state'] == 'partial-canceled'){
            $orderqueue = array_diff($orderqueue,[$order_id]);
            $zNum = $zNum + ($order_status['data']['amount'] - $order_status['data']['field-amount']);
       
            //修改该订单的成交量
            $save['order_num'] = $order_status['data']['field-amount'];
            $where['order_id'] = $order_id;
            $where['status']   = $stop_less;
            //M('buy_order','cash_')->where($where)->save($save);
            $this->field($save,$where);
            $code = 1;
            echo "====撤销成功====\n\n";
            break;
          } else {
            echo "======={$buy_sell_type}--当前状态不进行任何操作\n\n";
            $code = 0;
          }
        }
        $arr['orderqueue'] = $orderqueue;
        $arr['zNum']       = $zNum;
        $arr['code']       = $code;
        return $arr;
    }

    //手续费
    public function field($save,$where){
      M('buy_order','cash_')->where($where)->save($save);
    }


    //调用接口下单
    public function crontab($uid,$once_num,$sym,$type,$order_price){
 
        $crontab = new \Home\Controller\CrontabController($uid);
        $orderId = $crontab->getPlace($once_num, $sym, $type, $order_price, 'api',$uid);
        
        return $orderId;
    }

    //获取当前执行的所有订单
    public function cash_buy_order($stop_less,$sym,$buy_sell_type){
        $where2['status'] = $stop_less;
        $where2['order_num'] = ['gt',0];
        $where2['symbol'] = $sym;
        $result = M('buy_order','cash_')->where($where2)->field("id,order_num,order_price,field_fees")->select();
        //return $result;

        if(empty($result)){
          echo "没有购买成功\n";
          $this->db_error("{$buy_sell_type}--没有购买成功",$sym);
          return 1; 
        }
    
        //获取当前交易的总金额
        foreach($result as $v){
          $all_money += $v['order_num'] * $v['order_price'];
          $field_fees += $v['field_fees']; 
        }

        $val_order_num = array_column($result,'order_num');
        $val_order_price = array_column($result,'order_price');

        $arr['avg'] = $all_money / array_sum($val_order_num); 
        $arr['str_id'] = implode(',',array_column($result,'id'));
        $arr['order_num'] = array_sum($val_order_num);
        $arr['field_fees'] = $field_fees;
        return $arr;
    }
    

    //查看账户余额
    public function account_balance($sym2,$uid,$once_num,$order_price,$symbol,$buy_sell_type){
        $code = 0;
        $sym_num = $this->getAccoundId($sym2,$uid);
        if($sym_num < $once_num * $order_price){
          echo "账户余额不足\n";
          $this->db_error("{$buy_sell_type}---账户余额不足",$symbol);
          $code = 1;
        }
        return $code;
    }


     /*
    *   卖单操作
    */
   public function place_sell_order($sym1,$sym2,$order_num,$once_num,$minunitmoney,$maxunitmoney,$id,$buy_order_id,$buysell_id="",$uid,$stopless,$stop_ratio1,$stop_ratio2,$genre){

        $this->commonSymbols($sym1,$sym2,$uid);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);

        while($order_num > 0){

            $buy_sell_status = M('sellbuy_order','cash_')->where("id = {$buysell_id}")->getField('status');

      			if($buy_sell_status == 0){
      				M('buysell_cause','cash_')->add(["cause"=>"卖单--强制关闭","symbol"=>$sym,"data"=>time()]);
      				break;
      			}

            $order_depth = $this->get_depth_data2($sym,$uid);
            $orderPrice  = $order_depth['first_askprice']-$minPrice;
            echo "====卖单价格===={$orderPrice}\n\n";
            if($orderPrice == $order_depth['first_bidprice'] && empty($buy_order_id)){
              $orderPrice = $order_depth['first_askprice'];
            }

            if(empty($buy_order_id)){

              if($orderPrice > $maxunitmoney || $orderPrice < $minunitmoney){
                  //价格超出规定的范围
                  echo "卖单---价格超出预定范围\n";
                  $this->db_error("卖单---价格超出预定范围",$sym);
                  continue;
              }

            }

            while(!empty($orderqueue)){  //撤单操作

               $val_stop = $this->buy_sell_order_status($orderqueue,$uid,$orderPrice,$sym,$order_num,5,'卖单');
               var_dump($val_stop);

               $orderqueue = $val_stop['orderqueue'];
               $order_num       = $val_stop['zNum'];
               if($val_stop['code'] == 1){
                    break;
               } else {
                   continue;
               }
            }

            if($this->jump_buy == 1){
                echo "====最大价格后跳出循环====\n\n";
                $this->jump_buy = 0;
                continue;
            }else{
              //下单操作
              if($once_num > 0 && $order_num > 0){

                  if($once_num > $order_num ){
                      $once_num = $order_num;
                  }

                  $user_balance = $this->account_balance($sym1,$uid,$once_num,$orderPrice,$sym,'卖单');

                  if($user_balance == 1){
                    break;
                  }
 
                  $orderId = $this->crontab($uid,$once_num,$sym,'sell-limit',$orderPrice,$uid);
                  
                  if($orderId['status'] == 'error'){
                    echo "ccc".$once_num."\n";
                    echo "---".$order_num."rr\n";
                    echo "卖单---下单失败";
                    $this->db_error("卖单---下单失败",$sym);
                    break;
                  }else{
                      echo "ppppp".$order_num."ffffff\n";
                      echo "===opopop==".$once_num."==ooppopp==\n";
                      array_push($orderqueue,$orderId['data']);
                      $order_num = bcsub($order_num,$once_num,2);
                      echo "bbbbb".$order_num."\n\n";
                      $this->add_my = 1;
					  
                      $add['order_num'] = $once_num;          
                      $add['order_id']  = $orderId['data'];
                      $add['symbol']    = $sym;
                      $add['bt_type']   = 'sell-limit';
                      $add['status']    = 5;
                      $add['order_price'] = $orderPrice;
                      $add['creat_time']  = time();
					            $add['buysell_id']  = $buysell_id;
                      M('buy_order','cash_')->add($add);
					  
                      usleep(500000);
                  }
              }
            }
        }

        $result = $this->cash_buy_order(5,$sym,"卖单");

        if($result == 1){
          return ;
        }

        if(!empty($id)){
            $sell_save['status'] = 0;
            $sell_where['id'] = $id;
            M('order_buy','cash_')->where($sell_where)->save($sell_save);
        }
        
        if(!empty($buy_order_id)){
          $arr_orderid = explode(',',$buy_order_id);
          $buy_order_save['status'] = 0;
          foreach($arr_orderid as $v){
            $buy_order_where['id'] = $v;
            M('buy_order','cash_')->where($buy_order_where)->save($buy_order_save);
          }
        }

        $stop_save['status'] = 6; 
        $stop_where['status'] = 5;
        M('buy_order','cash_')->where($stop_where)->save($stop_save);

        if(!empty($buysell_id)){
          $buy_sell_save['status'] = 0;
          M('sellbuy_order','cash_')->where("id = {$buysell_id}")->save($buy_sell_save);
        }

        if($stopless == 1){
          $add2['order_upmoney'] = $result['avg'];
          $add2['order_stopmoney'] = $result['avg'];
          $add2['order_stopless_price'] = $result['avg'] * (1 - $stop_ratio1);
          $add2['sym1'] = $sym1;
          $add2['sym2'] = $sym2;
          $add2['data'] = time();
          $add2['order_num'] = $result['order_num'];
          $add2['ratio1'] = $stop_ratio1;
          $add2['ratio2'] = $stop_ratio2;
          $add2['status'] = $stopless;    //1  止损  2 不止损
          $add2['maxunitmoney'] = $maxunitmoney;
          $add2['minunitmoney'] = $minunitmoney;
          $add2['genre']        = $genre;
          $add2['once_num']     = $once_num;
          $add2['buy_order_id'] = $result['str_id'];
          $add2['uid']          = $uid;
          $add2['stype']        = 'sell';
          $val = M('order_buy','cash_')->add($add2);
        }
        $this->db_error("卖单---下单成功---当前交易的平均价格为:{$result['avg']}----手续费:{$result['field_fees']}",$sym);
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
        $time  = time();
        $req   = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误';
            return;
        }

        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        echo "==闭盘价格===".$closeMoney."====\n\n";
        if($closeMoney < $result['order_upmoney']){
            echo "======闭盘价格小于最高止损价格=======\n";
            $this->upMoney = $closeMoney;
            //止损价格
            $proportion = ($result['order_stopmoney'] - $closeMoney) / $result['order_stopmoney'];
            echo "==止损比例==".$proportion."=====\n\n";
            if($proportion < $result['ratio1']){  //小于0.05
                echo "111111111\n\n";
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - $result['ratio2']);
                //$this->upMoney = $this->stopLessMoney;

            } elseif ($result['ratio1'] <= $proportion && $proportion < $result['ratio1'] + 0.1){
                echo "22222222\n\n";
                echo "=====闭盘价格=======\n\n";
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ration2'] - 0.25));
                //$this->upMoney = $this->stopLessMoney;

            } elseif ($result['ratio1'] + 0.1 <= $proportion && $proportion < $result['ratio1'] + 0.2){
                echo "33333333\n\n";
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ratio2'] - 0.5));
                //$this->upMoney = $this->stopLessMoney;

            } elseif ($proportion >= $result['ratio2'] + 0.2){
                echo "44444444444\n\n";
                $this->stopLessMoney = $closeMoney + $closeMoney * (1 - ($result['ration2'] - 0.75));
                //$this->upMoney = $this->stopLessMoney;

            }
            $up = M('order_buy','cash_');
            $up->order_upmoney = $this->upMoney;
            $up->order_stopless_price = $this->stopLessMoney;
            $up->where("id = {$result['id']}")->save();
        } elseif ($closeMoney > $result['order_stopless_price']){
            $str = "建议买入\n\n";  //下单之后将该订单取消
        }else{
        	  $str = 1;
            echo "======闭盘价格下大于最低价格  小于止损价格=======\n\n";
        }
        return $str;
    }



     /*
     *   获取精度
     */
    public function commonSymbols($sym,$sym2,$uid){
      $req = new req($uid);
        $result = $req->get_common_symbols();
        if($result['status']!='ok') {
            return null;
        }
        foreach($result['data'] as $k=>$v){
            if($sym == $v['base-currency'] && $sym2 == $v['quote-currency']){
                $this->price_precision[$sym] = $v['price-precision'];
                $this->amount_precision[$sym] = $v['amount-precision'];
            }
        }
    }

    //获取当前最新的depth
    public function get_depth_data2($sym,$depth="10",$uid){
         // 实时获取数据
        $req = new req($uid);
        $val_data = $req->get_market_depth($sym, 'step0');
        $arr_val['bids'] = array_slice($val_data['tick']['bids'], 0, $depth);
        $arr_val['asks'] = array_slice($val_data['tick']['asks'], 0, $depth);
        
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['bids']), SORT_DESC, $arr_val['bids']);
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['asks']), SORT_ASC, $arr_val['asks']);

        $arr['first_askprice'] = $arr_val['asks'][0][0];   //卖
        $arr['first_bidprice'] = $arr_val['bids'][0][0];    //买
        return $arr;
    }

    /*
     *   获取订单详情
     */
    public function getOrder($order_id,$uid){
        $req = new req($uid);
        $result = $req->get_order($order_id);
        return $result;
    }


    //撤销订单
    public function getSubmitcancel($order_id,$uid){
        $req = new req($uid);
        $result = $req->cancel_order($order_id);
        return $result;
    }


    //查看个人账户资产
    public function getAccoundId($sym,$uid){
        $req = new req($uid);
        $result = $req->get_account_balance();
        if($result['status'] != 'ok'){
            var_dump('数据请求错误');exit;
        }
        foreach($result['data']['list'] as $k=>$v){
            if($v['type'] == 'trade' && $v['balance'] > 0){
                $arr[] = $v;
                if($v['currency'] == $sym){
                  $str = $v['balance'];
                }
            }
        }
        return $str;
    }



    //策略操作
    public function auto_buy(){

      while(true){
        //获取币对相应的数据
        $result = M('sellbuy_order','cash_')->where("status = 1")->find();
        //获取最新的一条k线   5
        $req = new req();
        $kline_val = $req->get_history_kline('htusdt', '5min', 100);
        if($kline_val['status'] != 'ok'){
          var_dump('数据请求错误');exit;
        }
        /*
        *    1 当前最新的k线的成交量 > 上一k线成交量5倍
        *    成交量> 上5条成交量之和的5倍
        *    (最高-最低)/最低 > 1.5%
        *    (收盘-最低)/(最高-最低) > 0.65
        *    当前价 / 上  k线收盘 < 0.997
        */  
        $now_kline_ratio = $kline_val['data'][1]['amount'] / $kline_val['data'][2]['amount'];

        for($i=2;$i<7;$i++){
          $amount_num5 += $kline_val['data'][$i]['amount'];
        }
        $now_kline_ratio5 = $kline_val['data'][1]['amount'] / $amount_num5;
        
        //最高价 - 最低价
        $high_low = (($kline_val['data'][1]['high'] - $kline_val['data'][1]['low']) / $kline_val['data'][1]['low']) * 100;

        //收盘价 - 最低价 / 最高价 - 最低价
        $close_low = ($kline_val['data'][1]['close'] - $kline_val['data'][1]['low']) / ($kline_val['data'][1]['high'] - $kline_val['data'][1]['low']);
        
        $uid = session('uid');

        //获取当前价格   $sym,$depth="10",$uid
        $result = $this->get_depth_data2('htusdt',10,$uid);
        $now_price = $result['first_bidprice'];
        $now_price_close = $result['first_bidprice'] - $kline_val['data'][1]['close'];

        if($now_kline_ratio > 5 || $now_kline_ratio5 > 5){
          if($high_low > 1.5 && $close_low > 0.65 && $now_price_close < 0.997){
            //条件符合 买入
              echo "买入操作";

              //$this->buy_order('ht',$usdt,1,0.1,1,10,0.05,0.97,1,"",2,1);
              break;

          }else{
            echo "条件不符合===2===\n\n";
            sleep(5);
          }
        }else{
          echo "条件不符合==1===\n\n";
          sleep(5);
        }
    }

    }


    //策略卖单
    public function auto_sell(){
        var_dump(1454545);exit;
    }

}
