<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class GobController extends Controller
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


     //下单操作
   public function buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$buysell_id,$uid){

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
               
               $val_stop = $this->buy_sell_order_status($orderqueue,$uid,$order_price,$sym,$zNum,'买单');
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
                      $add['status']    = 1;
                      $add['order_price'] = $order_price;
                      $add['creat_time']  = time();
					            $add['buysell_id']  = $buysell_id;
                      M('auto_buy_order','cash_')->add($add);
                      usleep(500000);
                  }
              }
            }
        }
        
        $result = $this->cash_buy_order($sym,'买单');
        
        if($result == 1){
          return ;
        }
		  
      //   //获取所有订单的id
    		// if($stop_less == 1){  //需要止损
    		// 	$str_id = $result['str_id'];
    		 	$add2['order_upmoney'] = $result['avg'];
    		// 	$add2['order_stopmoney'] = $result['avg'];
    		 	$add2['order_stopless_price'] = $result['avg'] * (1 - 0.01);
    		 	$add2['sym1'] = $sym1;
    		 	$add2['sym2'] = $sym2;
    		 	$add2['data'] = time();
    		 	$add2['order_num'] = $result['order_num'];
    		// 	$add2['ratio1'] = $stop_ratio1;
    		// 	$add2['ratio2'] = $stop_ratio2;
    		 	$add2['status'] = 1;    //1  止损  2 不止损
    		 	$add2['maxunitmoney'] = $maxUnitMoney;
    		 	$add2['minunitmoney'] = $minUnitMoney;
    		// 	$add2['genre']        = $genre;
    		 	$add2['once_num']     = $this->once_num;
    		// 	$add2['buy_order_id'] = $str_id;
          $add2['uid']          = $uid;
    		 	$val = M('auto_order_buy','cash_')->add($add2);
      //   }

		    $stop_save['status'] = 3; 
			  $stop_where['status'] = 1;
		    M('auto_buy_order','cash_')->where($stop_where)->save($stop_save);
		    
        $this->db_error("自动---买---下单成功----当前交易的平均价格为：{$result['avg']}----手续费:{$result['field_fees']}",$sym);

        if(!empty($buysell_id)){
          $save2['status'] = 0;
          M('auto_sellbuy_order','cash_')->where("id = {$buysell_id}")->save($save2);
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
    public function buy_sell_order_status($orderqueue,$uid,$order_price,$symbol,$zNum,$buy_sell_type){

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
            $where['status']   = 1;
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
      M('auto_buy_order','cash_')->where($where)->save($save);
    }


    //调用接口下单
    public function crontab($uid,$once_num,$sym,$type,$order_price){
 
        $crontab = new \Home\Controller\CrontabController($uid);
        $orderId = $crontab->getPlace($once_num, $sym, $type, $order_price, 'api',$uid);
        
        return $orderId;
    }

    //获取当前执行的所有订单
    public function cash_buy_order($sym,$buy_sell_type){
        $where2['status'] = 1;
        $where2['order_num'] = ['gt',0];
        $where2['symbol'] = $sym;
        $result = M('auto_buy_order','cash_')->where($where2)->field("id,order_num,order_price,field_fees")->select();
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
   public function place_sell_order($sym1,$sym2,$order_num,$once_num,$minunitmoney,$maxunitmoney,$buysell_id="",$uid){

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
            if($orderPrice == $order_depth['first_bidprice']){
              $orderPrice = $order_depth['first_askprice'];
            }

              if($orderPrice > $maxunitmoney && $orderPrice < $minunitmoney){
                  //价格超出规定的范围
                  echo "卖单---价格超出预定范围\n";
                  $this->db_error("卖单---价格超出预定范围",$sym);
                  continue;
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
                      M('auto_buy_order','cash_')->add($add);
					  
                      usleep(500000);
                  }
              }
            }
        }

        $result = $this->cash_buy_order(5,$sym,"卖单");

        if($result == 1){
          return ;
        }

        // if(!empty($id)){
        //     $sell_save['status'] = 0;
        //     $sell_where['id'] = $id;
        //     M('order_buy','cash_')->where($sell_where)->save($sell_save);
        // }
        
        // if(!empty($buy_order_id)){
        //   $arr_orderid = explode(',',$buy_order_id);
        //   $buy_order_save['status'] = 0;
        //   foreach($arr_orderid as $v){
        //     $buy_order_where['id'] = $v;
        //     M('buy_order','cash_')->where($buy_order_where)->save($buy_order_save);
        //   }
        // }

        $stop_save['status'] = 6; 
        $stop_where['status'] = 5;
        M('auto_buy_order','cash_')->where($stop_where)->save($stop_save);

        if(!empty($buysell_id)){
          $buy_sell_save['status'] = 0;
          M('auto_order_buy','cash_')->where("id = {$buysell_id}")->save($buy_sell_save);
        }

        // if($stopless == 1){
        //   $add2['order_upmoney'] = $result['avg'];
        //   $add2['order_stopmoney'] = $result['avg'];
        //   $add2['order_stopless_price'] = $result['avg'] * (1 - $stop_ratio1);
        //   $add2['sym1'] = $sym1;
        //   $add2['sym2'] = $sym2;
        //   $add2['data'] = time();
        //   $add2['order_num'] = $result['order_num'];
        //   $add2['ratio1'] = $stop_ratio1;
        //   $add2['ratio2'] = $stop_ratio2;
        //   $add2['status'] = $stopless;    //1  止损  2 不止损
        //   $add2['maxunitmoney'] = $maxunitmoney;
        //   $add2['minunitmoney'] = $minunitmoney;
        //   $add2['genre']        = $genre;
        //   $add2['once_num']     = $once_num;
        //   $add2['buy_order_id'] = $result['str_id'];
        //   $add2['uid']          = $uid;
        //   $add2['stype']        = 'sell';
        //   $val = M('order_buy','cash_')->add($add2);
        // }
        $this->db_error("卖单---下单成功---当前交易的平均价格为:{$result['avg']}----手续费:{$result['field_fees']}",$sym);
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


    //模拟
    public function gob_kline(){

        $where['r_time'] = '5min';
        $where['chid']   = 194;
        $result = M('kline','cb_')->where($where)->order('kid ASC')->limit(10000)->select();  
        $tal = count($result); 
        echo "===总个数==={$tal}==\n\n";
        foreach($result as $k=>$v){
            if($k > 5){
            $now_kline_amount = $v['amount'] / $result[$k-1]['amount'];

            for($i=$k-1;$i>$k-6;$i--){
                $zong_amount5 += $result[$i]['amount']; 
            }

            $now_kline_amount5 = $v['amount'] / ($zong_amount5 / 5);

            if($now_kline_amount > 5 && $now_kline_amount5 > 5){
                //echo '满足第一步条件'."<br />";
                $a++;
                $high_low  = ($v['high'] - $v['low']) / $v['low'] * 100;
                $close_low = ($v['close'] - $v['low']) / ($v['high'] - $v['low']);

                //获取当前价格
                $depth_val = $this->get_depth_data2('btcusdt',10,1);
                $now_price_close = $depth_val['first_askprice'] / $result[$k]['close'];
                $aa = round($now_price_close,3);
                if($high_low > 1.5 && $close_low > 0.65){
                  $b++;
                  $kid = $k-1;
                  echo "id====={$k}=={$kid}=={$v['id']}===最高价:{$v['high']}===最低价:{$v['low']}====\n\n";
                  $ktime = $result[$k-1]['kid'];
                  echo "-----{$v['kid']}----{$ktime}----\n\n";

                  //$sum = 0.998 * $result[$k+1]['close'];
                  //echo "+++++".$sum."+++++\n\n";
                  $close = $result[$k-1]['close'];
                  echo "====上一次闭盘价格===={$close}====\n";
                  $high_sclose = $v['high'] / $result[$k-1]['close'];
                  echo "====最高价/闭盘价==={$high_sclose}====\n\n";

                  $up_price = $result[$k-1]['close'] * 0.99;
                  echo "====闭盘价 * 0.99====={$up_price}====\n\n";
                  if($up_price < $v['low']){
                    $c++;
                    $data = date('Y-m-d H:i:s',$v['kid']);
                    echo "===时间:{$data}===========\n\n";
                  } 
                   
                }
            }else{
                // echo "不满足条件1111111<br />";
            }
            $zong_amount5 = 0;
          }
        }
          echo $a."\n\n";
          echo $b."\n\n";
          echo $c;
    }



    //模拟策略  2
    /*
     *  连续两条k线的收盘价(除去最新的) > 前50条k线平均值(close) * 1.004  或者  当前k线的成交量 > 上一k线成交量的2.5倍  && 当前价 > 前50条k线平均值(close)
     */
    public function gob2(){
      //获取相应的k线
      $where['r_time'] = '5min';
      $where['chid']   = 199;
      $kline = M('kline','cb_')->where($where)->order('kid ASC')->limit(10000)->select();
      echo "++++".count($kline)."++++\n\n";
      $ad = 1;
      foreach($kline as $k=>$v){
        if($k>50){
          for($i=$k-1;$i>$k-51;$i--){
            $close_price_sum += $kline[$i]['close'];
          }
          $close_avg = $close_price_sum / 50;
         
          if(($kline[$k-1]['close'] > $close_avg * 1.004 && $kline[$k-2]['close'] > $close_avg * 1.004) || ($v['amount'] > $kline[$k-1]['amount'] * 2.5 && $v['close'] > $close_avg)){
                    
                    //第一次满足
                if($ad == 1){
                  $c++;
                  $data2 = date('Y-m-d H:i:s',$v['kid']);
                  echo "====买入时间:{$data2}====\n";
                  $one_close = $v['close'];
                }
                $ad = 0;
                $arr[] = $v['close'];
            $a++;
            $f = 1;
          }

          //(当前k线的close价格 - 上一k线close) / 上一k线close > 2.5
            if(($v['amount'] > $kline[$k-1]['amount'] * 2.5 && $v['close'] < $close_avg) || ($kline[$k-1]['close'] < $close_avg * 0.996 && $kline[$k-2]['close'] < $close_avg * 0.996)){
                $ad = 1;
                $b++;
                if($f == 1){
                  $data = date('Y-m-d H:i:s',$v['kid']);
                  echo "====k线时间:{$data}====\n";

                  $up_close = $kline[$k-1]['close'];
                  $avg = $close_avg * 0.996;
                  $up_close2 = $kline[$k-2]['close'];

                  echo "===上一条k线{$up_close}===平均价格*0.996:{$avg}===上2条k线{$up_close2}====平均价格:{$close_avg}====\n\n\n";
                }
                $f = 0;
              }
          $close_price_sum = 0;
        }
      }

      // foreach($kline as $k=>$v){
      //     if($k+51 > count($kline)){
      //       var_dump('终止');
      //       break;
      //     }
      //     for($i=$k+1;$i<$k+51;$i++){
      //       $close_price_sum += $kline[$i]['close']; 
      //     }
      //     $close_avg = $close_price_sum / 50;
          
      //     if(($kline[$k+1]['close'] > $close_avg * 1.004 && $kline[$k+2]['close'] > $close_avg * 1.004) || ($v['amount'] > $kline[$k+1]['amount'] * 2.5 && $v['close'] > $close_avg)){
      //         //符合条件
      //       $a++;
      //         //echo "==50条平均价格:{$close_avg}===\n\n";
      //         $up_close1 = $kline[$k+1]['close'];
      //         $up_close2 = $kline[$k+2]['close'];
      //         $avg_x     = $close_avg * 1.004;  
      //         //echo "====前一条k线close:{$up_close1}===前2条k线:{$up_close2}====平均价格 * 1.004=={$avg_x}===\n\n";
      //         $up_amount = $kline[$k+1]['amount'] * 2.5;
      //         //echo "====当前k数量:{$v['amount']}====上一k成交量:{$up_amount}====当前价:{$v['close']}====50平均价:{$close_avg}====\n\n";

      //         /*
      //         *  当前k线成交量 > 上一k线2.5倍  && 当前k线的收盘价 < M  或者
      //         *  连续2条k线收盘价 < M * 0.996
      //         */
      //         if(($v['amount'] > $up_amount && $v['close'] < $close_avg) || ($kline[$k+1]['close'] < $close_avg * 0.996 && $kline[$k+2]['close'] < $close_avg * 0.996)){
      //           $b++;
      //           $data = date('Y-m-d H:i:s',$v['kid']);
      //           echo "====k线时间:{$data}====\n\n";
      //         }
      //     }
      //     $close_price_sum = 0;
      // }
      echo $a."\n\n";
      echo $b."\n\n";
      var_dump($c);
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
        
        $req = new req();
        $kline = $req->get_history_kline($result['sym1'].$result['sym2'],'15min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误'; 
            return $str;
        }
        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        echo "闭盘价格===".$closeMoney."\n";
        
        if($closeMoney >= $result['order_upmoney']){
               

            $this->upMoney = $closeMoney;
            $proportion = ($closeMoney - $result['order_stopmoney']) / $result['order_stopmoney'];

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
            var_dump('卖单操作');exit;
            //执行卖单操作   成功后将数据库中状态  status=0
            //$this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['buy_order_id'],'',$result['uid']);   //没有价格区间

        }else{
          $str = "====闭盘价格小于最大价格  大于止损价格===";        
          echo $str."\n\n";
        }
       
    }





     //模拟
    /*
     *    1 当前最新的k线的成交量 > 上一k线成交量5倍
     *    成交量> 上5条成交量平均值的5倍
     *    (最高-最低)/最低 > 1.5%
     *    (收盘-最低)/(最高-最低) > 0.65
     *    上一k线的闭盘价格 * 0.99 < 当前k线的最低价
     */
    public function gob_klines(){
       
       $where2['symbol-partition'] = 'main';
       $where2['base-currency']    = 'ht';
       $where2['_logic']           = 'OR';
       $bt_val = M('channel','cb_')->where($where2)->field('chid,symbol')->select();
       foreach($bt_val as $key=>$val){

       $where['r_time'] = '5min';
      $where['chid']   = $val['chid'];
      $result = M('kline','cb_')->where($where)->order('kid DESC')->limit(10000)->select();  
        $b=0;
        $c=0;
        foreach($result as $k=>$v){

            $now_kline_amount = $v['amount'] / $result[$k+1]['amount'];

            for($i=$k+1;$i<$k+6;$i++){
                //echo $i."<br />";
                $zong_amount5 += $result[$i]['amount']; 
            }

            $now_kline_amount5 = $v['amount'] / ($zong_amount5 / 5);

            if($now_kline_amount > 5 && $now_kline_amount5 > 5){
                //echo '满足第一步条件'."<br />";
                $a++;
                $high_low  = ($v['high'] - $v['low']) / $v['low'] * 100;
                $close_low = ($v['close'] - $v['low']) / ($v['high'] - $v['low']);

                //获取当前价格
                $depth_val = $this->get_depth_data2('btcusdt',10,1);
                //echo '==当前价格==='.$depth_val['first_bidprice']."=====\n";
                //echo '==闭盘价格==='.$result[$k]['close']."====\n\n";
                $now_price_close = $depth_val['first_askprice'] / $result[$k]['close'];
                $aa = round($now_price_close,3);
                if($high_low > 1.5 && $close_low > 0.65){
                  $b++;
                  $kid = $k+1;
                  echo "id====={$k}=={$kid}=={$v['id']}===最高价:{$v['high']}===最低价:{$v['low']}====\n\n";
                  $ktime = $result[$k+1]['kid'];
                  echo "-----{$v['kid']}----{$ktime}----\n\n";

                  //$sum = 0.998 * $result[$k+1]['close'];
                  //echo "+++++".$sum."+++++\n\n";
                  $close = $result[$k+1]['close'];
                  echo "====上一次闭盘价格===={$close}====\n";
                  $high_sclose = $v['high'] / $result[$k+1]['close'];
                  echo "====最高价/闭盘价==={$high_sclose}====\n\n";

                  $up_price = $result[$k+1]['close'] * 0.99;
                  echo "====闭盘价 * 0.99====={$up_price}====\n\n";
                  if($up_price < $v['low']){
                    $c++;
                  } 
                   
                }
            }else{
                // echo "不满足条件1111111<br />";
            }
            $zong_amount5 = 0;
        }
        $arr[$val['symbol']] = $b."/".$c;
      }
      var_dump($arr);
          // echo $a."\n\n";
          // echo $b."\n\n";
          // echo $c;
      }
}
