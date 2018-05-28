<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class StoplessController extends Controller
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
                     //   if(empty($retrun)){
                     //   	   foreach($return as $k=>$v){
                     //   	   	    if($v != 1){
                     //                $add2['cause'] = $v;
			                  //       $add2['data']  = time();
			                  //       $add2['status'] = 1;
			                  //       $add2['symbol'] = $k;
			                  //       M('order_cause','cash_')->add($add2);
                     //   	   	    }
                     //   	   }
                     //   }
                    //echo $return;
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
            //$proportion = ($closeMoney - $result['order_stopmoney']) / $result['order_stopmoney'];
            $proportion = $closeMoney / $result['order_stopmoney'];
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
    public function buy_sell_montior($status){
            switch($status){
              case 1:
              $type = 'buy';
              break;
              case 2:
              $type = 'sell';
              break;
            }
            while (true) {
                $pid = pcntl_fork();
                //echo "pid====".$pid."进程pid\n\n";
                if ($pid == - 1) {
                    die('could not fork');
                } elseif ($pid) {
                    $pidArr[] = $pid;   //父进程
                } else {
                    M()->db(0,"",true);
                    $return = $this->buy_sell($type);  //监听 
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
    public function buy_sell($type){
        $where['status'] = 1;
        $where['buy_type']   = $type;
        $result = M('sellbuy_order','cash_')->where($where)->select();

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
        $once_sta = 1;
        while($zNum > 0){

    			$buy_sell_status = M('sellbuy_order','cash_')->where("id = {$buysell_id}")->getField('status');

    			if($buy_sell_status == 0){
            echo "====买单强制关闭===\n\n";
    				M('buysell_cause','cash_')->add(["cause"=>"买单--强制关闭","symbol"=>$sym,"data"=>time()]);
    				break;
    			}

            $order_depth = $this->get_depth_data2($sym,$uid);
            $first_askprice = $order_depth['asks'][0][0];   //卖
            $first_bidprice = $order_depth['bids'][0][0];    //买
            $order_price = $first_bidprice + $minPrice;
            if($order_price == $first_askprice){
              $order_price = $first_bidprice;
            }
            if(($order_price > $maxUnitMoney || $order_price < $minUnitMoney) && $once_sta = 1){
                echo "买单---价格超出规定的范围\n\n";
                M('buysell_cause','cash_')->add(["cause"=>"买单---价格超出规定的范围","symbol"=>$sym,"data"=>time()]);
                $once_sta = 0;
                continue ;
            }

            while(!empty($orderqueue)){  //撤单操作
                $order_id = $orderqueue[count($orderqueue) - 1];
                $val_order = $this->getOrder($order_id,$uid);
                if($val_order['status'] != 'ok'){
                    continue;
                }
                if($val_order['data']['state'] == 'filled'){ 
                   //保存手续费 field_fees
                   echo "买单====订单完成状态====\n\n";
                   $save2['field_fees'] = $val_order['data']['field-fees'];
                   $where['order_id'] = $order_id;
                   M("buy_order",'cash_')->where($where)->save($save2);
                    break;
                }

                 if($val_order['data']['price'] >= $order_price){
                     echo "买单====当前为最高价格====\n\n";
                     if($this->buy_add_my == 1){
                       M('buysell_cause','cash_')->add(["cause"=>"买单---当前为最高价格","symbol"=>$sym,"data"=>time()]);
                     }
                     $this->buy_add_my = 0;
                     $this->jump_buy = 1;
                     break;
                 }

                $repal_order = $this->getSubmitcancel($order_id,$uid);
                
                
                //查询订单状态  执行操作状态后   
                for($i=0;$i<3;$i++){

                    usleep(500000);
                    $val_order = $this->getOrder($order_id,$uid);
                    
                    if($val_order['status'] != 'ok'){
                        echo "买单====查询订单出错===\n\n";
                        M('buysell_cause','cash_')->add(["cause"=>"买单---查询订单状态错误2","symbol"=>$sym,"data"=>time()]);
                        continue;
                    };

                    if($val_order['data']['state'] == 'filled'){
                      var_dump($val_order);
                        echo "买单====订单完成状态222====\n\n";
                        $this->jump2 = 1;
                        $save3['field_fees'] = $val_order['data']['field-fees'];
                        $where['order_id'] = $order_id;
                        M("buy_order",'cash_')->where($where)->save($save3);
                        break;
                    } elseif ($val_order['data']['state'] == 'canceled' || $val_order['data']['state'] == 'partial-canceled'){
                        echo "买单====订单取消成功====\n\n";
                        $orderqueue = array_diff($orderqueue,[$order_id]);
                        $zNum = $zNum + ($val_order['data']['amount'] - $val_order['data']['field-amount']);

                        //修改该订单的成交量
                        $save['order_num'] = $val_order['data']['field-amount'];
                        $save['field_fees'] = $val_order['data']['field-fees'];
                        $where['order_id'] = $order_id;
                        $where['status']   = $stop_less;
                        M("buy_order",'cash_')->where($where)->save($save);
                        break;
                    }else{
                      echo "买单====当前状态不进行下单操作\n\n";
                    }
                }
            }

            if($this->jump_buy == 1){

                $this->jump_buy = 0;
                continue;

            }else{
              //下单操作
              if($once_num > 0){

                  if($once_num > $zNum){
                      $once_num = $zNum;
                  }
                 
                  $sym_num = $this->getAccoundId($sym2,$uid);

                  if($sym_num == 1){
                    echo "买单===接口请求出错===\n\n";
                    break;
                  }

                  if($sym_num < $once_num){
                    echo "买单====账户余额不足====\n\n";
                    M('buysell_cause','cash_')->add(["cause"=>"买单---账户余额不足","symbol"=>$sym,"data"=>time()]);
                    break;
                  }

                  $crontab = new \Home\Controller\CrontabController($uid);
                  $orderId = $crontab->getPlace($once_num, $sym, 'buy-limit', $order_price, 'api',$uid);

                  if($orderId['status'] == 'error'){
                      echo "买单===下单接口报错===\n\n";
                      M('buysell_cause','cash_')->add(["cause"=>"买单---下单接口报错","symbol"=>$sym,"data"=>time()]);
                      break;
                  }else{
                      array_push($orderqueue,$orderId['data']);
                      $zNum = bcsub($zNum,$once_num,2);
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
        //不包含市价单
        $where2['status'] = $stop_less;
        $where2['order_num'] = ['gt',0];
        $where2['symbol'] = $sym;
        $result = M('buy_order','cash_')->where($where2)->field("id,order_num,order_price,field_fees")->select();

        if(empty($result)){ echo "没有购买成功\n"; M('buysell_cause','cash_')->add(["cause"=>"买单--没有购买成功","symbol"=>$sym,"data"=>time()]); return ; }
		
    		//获取当前交易的总金额
    		foreach($result as $v){
    			$all_money  += $v['order_num'] * $v['order_price']; 
          $field_fees += $v['field_fees'];
          $zong_amount += $v['order_num'];
    		}

        $val_order_num = array_column($result,'order_num');
        $val_order_price = array_column($result,'order_price');
		    $avg = $all_money / array_sum($val_order_num); 

        //获取所有订单的id
		    if($stop_less == 1){  //需要止损
    			$val_order_id = array_column($result,'id');
    			$str_id = implode(',',$val_order_id);
    			$add2['order_upmoney'] = $avg;
    			$add2['order_stopmoney'] = $avg;
    			$add2['order_stopless_price'] = $avg * (1 - $stop_ratio1);
    			$add2['sym1'] = $sym1;
    			$add2['sym2'] = $sym2;
    			$add2['data'] = time();
    			$add2['order_num'] = array_sum($val_order_num);
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
		
        M('buysell_cause','cash_')->add(['cause'=>"买---下单成功----当前交易的平均价格为:{$avg}---手续费为:{$field_fees}----总个数:{$zong_amount}",'symbol'=>$sym,'data'=>time()]);
        if(!empty($buysell_id)){
          $save2['status'] = 0;
          M('sellbuy_order','cash_')->where("id = {$buysell_id}")->save($save2);
        }
    }


     /*
    *   卖单操作
    */
   public function place_sell_order($sym1,$sym2,$order_num,$once_num,$minunitmoney,$maxunitmoney,$id,$buy_order_id,$buysell_id="",$uid,$stopless,$stop_ratio1,$stop_ratio2,$genre){

        $this->commonSymbols($sym1,$sym2,$uid);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        $once_sta = 1;
        while($order_num > 0){

          $buy_sell_status = M('sellbuy_order','cash_')->where("id = {$buysell_id}")->getField('status');
    			if($buy_sell_status == 0){
            echo "====卖单强制关闭===\n\n";
    				M('buysell_cause','cash_')->add(["cause"=>"卖单--强制关闭","symbol"=>$sym,"data"=>time()]);
    				break;
    			}

            $order_depth = $this->get_depth_data2($sym,$uid);
            $first_bidprice = $order_depth['bids'][0][0];  //买
            $first_askprice = $order_depth['asks'][0][0];   //卖
            $orderPrice = $first_askprice-$minPrice;

            if($orderPrice == $first_bidprice && empty($buy_order_id)){
              $orderPrice = $first_askprice;
            }

            if(empty($buy_order_id)){
                if(($orderPrice > $maxunitmoney || $orderPrice < $minunitmoney) && $once_sta = 1){
                    echo "卖单====价格超出预定范围===\n\n";
                    M('buysell_cause','cash_')->add(["cause"=>"卖单---价格超出预定范围","symbol"=>$sym,"data"=>time()]);
                    $once_sta = 0;
                    continue;
                }
            }

            while(!empty($orderqueue)){  //撤单操作
                $order_id = $orderqueue[count($orderqueue) - 1];
                $val_order = $this->getOrder($order_id,$uid);

                if($val_order['status'] != 'ok'){
                  echo "卖单====请求订单详情报错====\n\n";
                  M('buysell_cause','cash_')->add(["cause"=>"卖单---请求订单详情错误","symbol"=>$sym,"data"=>time()]);
                  continue;
                }

                if($val_order['data']['state'] == 'filled'){
                  echo "卖单====订单完成状态====\n\n";
                  $save2['field_fees'] = $val_order['data']['field-fees'];
                  $where['order_id'] = $order_id;
                  M("buy_order",'cash_')->where($where)->save($save2);
                  break;
                }

                if($val_order['data']['price'] <= $orderPrice){
                  echo "卖单===当前属于最大价格===\n\n";
                  if($this->add_my == 1){
                      M('buysell_cause','cash_')->add(["cause"=>"卖单---当前价格属于最大价格","symbol"=>$sym,"data"=>time()]);
                  }
                  $this->jump = 1;
                  $this->add_my = 0;
                  break;
                }

                $repal_order = $this->getSubmitcancel($order_id,$uid);

                //查询订单状态  执行操作状态后
                for($i=0;$i<3;$i++){
                    usleep(500000);
                    $val_order = $this->getOrder($order_id,$uid);

                    if($val_order['status'] != 'ok'){
                      echo "卖单====查询订单状态错误22===\n\n";
                      M('buysell_cause','cash_')->add(["cause"=>"卖单---查询订单状态错误2","symbol"=>$sym,"data"=>time()]);
                      continue;
                    };

                    if($val_order['data']['state'] == 'filled'){
                        $this->jump = 1;
                        $save3['field_fees'] = $val_order['data']['field-fees'];
                        $where['order_id'] = $order_id;
                        M("buy_order",'cash_')->where($where)->save($save3);
                        break;
                    } elseif ($val_order['data']['state'] == 'canceled' || $val_order['data']['state'] == 'partial-canceled'){ 

                        $orderqueue = array_diff($orderqueue,[$order_id]);

                        $order_num = $order_num + ($val_order['data']['amount'] - $val_order['data']['field-amount']);
                        
						            $save['order_num'] = $val_order['data']['field-amount'];
                        $where['order_id'] = $order_id;
                        $where['status']   = 5;
                        M("buy_order",'cash_')->where($where)->save($save);
						
                        break;
                    }else{
                        echo "卖单====该状态不进行任何交易操作\n\n";
                    }
                }
            }
            if($this->jump == 1){
                $this->jump = 0;
                continue;
            }else{
              //下单操作
              if($once_num > 0 && $order_num > 0){

                  if($once_num > $order_num ){
                      $once_num = $order_num;
                  }
                  $sym_num = $this->getAccoundId($sym1,$uid);
                  
                  if($sym_num == 1){
                    echo "卖单===接口出====\n\n";
                    break;
                  }

                  if($sym_num < $once_num){
                    echo "卖单====余额不足===\n\n";
                    M('buysell_cause','cash_')->add(["cause"=>"卖单---账户余额不足","symbol"=>$sym,"data"=>time()]);
                    break;
                  }

                  $crontab = new \Home\Controller\CrontabController($uid);
                  $orderId = $crontab->getPlace($once_num, $sym, 'sell-limit', $orderPrice, 'api',$uid);

                  if($orderId['status'] == 'error'){
                    echo "卖单====下单失败===\n\n";
                    M('buysell_cause','cash_')->add(["cause"=>"卖单---下单失败","symbol"=>$sym,"data"=>time()]);
                     break;
                  }else{
                      array_push($orderqueue,$orderId['data']);
                      $order_num = bcsub($order_num,$once_num,2);
                      $this->add_my = 1;
					  
					            //保存订单
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
		
		    $where2['status'] = 5;
        $where2['order_num'] = ['gt',0];
        $where2['symbol'] = $sym;
        $result = M('buy_order','cash_')->where($where2)->field("id,order_num,order_price,field_fees")->select();

    		$val_order_num = array_column($result,'order_num');
		
    		foreach($result as $v){
    			$all_money += $v['order_num'] * $v['order_price']; 
          $field_fees += $v['field_fees'];
          $zong_amount += $v['order_num'];
    		}
    		$avg = $all_money / array_sum($val_order_num);

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
          $val_order_id = array_column($result,'id');
          $str_id = implode(',',$val_order_id);
          $add2['order_upmoney'] = $avg;
          $add2['order_stopmoney'] = $avg;
          $add2['order_stopless_price'] = $avg * (1 - $stop_ratio1);
          $add2['sym1'] = $sym1;
          $add2['sym2'] = $sym2;
          $add2['data'] = time();
          $add2['order_num'] = array_sum($val_order_num);
          $add2['ratio1'] = $stop_ratio1;
          $add2['ratio2'] = $stop_ratio2;
          $add2['status'] = $stopless;    //1  止损  2 不止损
          $add2['maxunitmoney'] = $maxunitmoney;
          $add2['minunitmoney'] = $minunitmoney;
          $add2['genre']        = $genre;
          $add2['once_num']     = $once_num;
          $add2['buy_order_id'] = $str_id;
          $add2['uid']          = $uid;
          $add2['stype']        = 'sell';
          $val = M('order_buy','cash_')->add($add2);
        }
        M('buysell_cause','cash_')->add(["cause"=>"卖单---下单成功---当前交易的平均价格为:{$avg}---手续费:{$field_fees}----总个数:{$zong_amount}","symbol"=>$sym,"data"=>time()]);
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
        return $arr_val;
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
            var_dump('数据请求错误');
            var_dump($result);
            M('buysell_cause','cash_')->add(["cause"=>"获取个人资产错误","symbol"=>$sym,"data"=>time()]);
            return 1;
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



    public function ceshi(){
      var_dump(5465446);
    }

}
