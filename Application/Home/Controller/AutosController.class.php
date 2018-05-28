<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
class AutosController extends Controller
{
    public $symbol;
    public $price_precision;
    public $amount_precision;
    public $jump=0;
    public $jump_buy=0;
    public $once_num;
    public $add_my=1;
    public $buy_add_my=1;
    public $close_money;
    public $stop = 0;
    public $stop2 = 0;
    public $arr = array();

     //下单操作
   public function buy_order($sym1,$sym2,$zNum,$once_num,$minUnitMoney,$maxUnitMoney,$buysell_id,$uid,$ratio,$bat_type="A",$handle=0){

        $this->once_num = $once_num;
        $this->commonSymbols($sym1,$sym2,$uid);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        $bug = 0;
        while($zNum > 0){
              //手动终止程序
             $buy_sell_status = M('auto_sellbuy_order','cash_')->where("id = {$buysell_id}")->getField('status');
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
                      if($bug < 3){
                        $bug++;
                        continue;
                      }else{
                        break;
                      }
          
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
                      $add['ratio']       = $ratio;
                      M('auto_buy_order','cash_')->add($add);
                      usleep(500000);
                  }
              }
            }
        }
        
        $result = $this->cash_buy_order(1,$sym,'买单');
        
        if($result == 1){
          return 2;
        }



         //获取所有订单的id
          $where_save['sym1'] = $sym1;
          $where_save['sym2'] = $sym2;
          $where_save['ratio_type'] = $ratio;
          $val_search = M('auto_order_buy','cash_')->where($where_save)->find();

          if($bat_type == 'A'){   //全部订单的操作
            $add_avg['order_num'] = $result['order_num'];
            $add_avg['buysell_id']  = 0;
          }else{     //  B   分批操作
            $add_avg['order_num'] = $result['order_num'];
            $add_avg['buysell_id'] = 1;
          }

          $add_avg['symbol']      = $sym;
          $add_avg['order_price'] = $result['avg'];
          $add_avg['creat_time']  = time();
          $add_avg['order_id']    = 0;
          $add_avg['bt_type']     = 'buy-limit';
          $add_avg['status']      = 3;
          $add_avg['ratio']       = $ratio;
          M('auto_buy_order','cash_')->add($add_avg);
          $add_avg = array();
          if($val_search){
            //修改操作
            if($bat_type == 'B'){
              $save_sellorders['batches'] = $result['order_num'] + $val_search['batches'];
              M('auto_order_buy','cash_')->where($where_save)->save($save_sellorders);
            }else{
              $save_sellorder['batches'] = 0;
              $save_sellorder['order_num'] = $result['order_num'] + $val_search['batches'] + $handle;
              $save_sellorder['minunitmoney'] = $minUnitMoney;
              $save_sellorder['maxunitmoney'] = $maxUnitMoney;
              $save_sellorder['once_num']     = $this->once_num;
              $save_sellorder['status']       = 1;
              $save_sellorder['order_upmoney'] = $result['avg'];
              $save_sellorder['order_stopless_price'] = $result['avg'] * (1 - 0.25);
              $save_sellorder['avg_money']    = $result['avg'];
              M('auto_order_buy','cash_')->where($where_save)->save($save_sellorder);
            }
           
          }else{
            $add2['avg_money']     = $result['avg'];
            $add2['order_upmoney'] = $result['avg'];
            $add2['order_stopless_price'] = $result['avg'] * (1 - 0.05);
            $add2['sym1'] = $sym1;
            $add2['sym2'] = $sym2;
            $add2['data'] = time();
            $add2['order_num'] = $result['order_num'];
            $add2['status'] = 1;
            $add2['maxunitmoney'] = $maxUnitMoney;
            $add2['minunitmoney'] = $minUnitMoney;
            $add2['once_num']     = $this->once_num;
            $add2['uid']          = $uid;
            $add2['order_id']     = $buysell_id;
            $add2['ratio_type']   = $ratio;
            $val = M('auto_order_buy','cash_')->add($add2);
          }

        $stop_save['status'] = 3; 
        $stop_where['status'] = 1;
        M('auto_buy_order','cash_')->where($stop_where)->save($stop_save);
        
        $this->db_error("自动---买---下单成功----当前交易的平均价格为：{$result['avg']}----手续费:{$result['field_fees']}",$sym);

        if(!empty($buysell_id)){
          
          if($bat_type == 'A'){
           // $save2['status']   = 1;
            $save2['order_num'] = $result['order_num'] + $val_search['batches'] + $handle;
            $save2['buy_sell'] = 0;
            $save2['batches']  = 0;
            $save2['handle_num'] = 0;
            M('auto_sellbuy_order','cash_')->where("id = {$buysell_id}")->save($save2);
          }else{
            
            M('auto_sellbuy_order','cash_')->where("id = {$buysell_id}")->setInc('batches',$result['order_num']);
          }
        }
        return $result['order_num'];
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
    public function cash_buy_order($sta,$sym,$buy_sell_type){
        $where2['status'] = $sta;
        $where2['order_num'] = ['gt',0];
        $where2['symbol'] = $sym;
        $result = M('auto_buy_order','cash_')->where($where2)->field("id,order_num,order_price,field_fees")->select();
        //return $result;
//var_dump($result);exit;
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
        if($sym_num < $once_num){
          echo "账户余额不足\n";
          $this->db_error("{$buy_sell_type}---账户余额不足",$symbol);
          $code = 1;
        }
        return $code;
    }


     /*
    *   卖单操作
    */
   public function place_sell_order($sym1,$sym2,$order_num,$once_num,$minunitmoney,$maxunitmoney,$buysell_id="",$uid,$order_id,$ratio_type,$bat_type="A",$handle=0){

        $status_val = 1;
        $this->commonSymbols($sym1,$sym2,$uid);
        $orderqueue = [];
        $sym = $sym1.$sym2;
        $minPrice = pow(10,-$this->price_precision[$sym1]);
        $num = $order_num;
        $once = $once_num;
        $bug = 0;
        while($order_num > 0){

         //    $buy_sell_status = M('sellbuy_order','cash_')->where("id = {$buysell_id}")->getField('status');

            // if($buy_sell_status == 0){
            //  M('buysell_cause','cash_')->add(["cause"=>"卖单--强制关闭","symbol"=>$sym,"data"=>time()]);
            //  break;
            // }

            $order_depth = $this->get_depth_data2($sym,$uid);
            $orderPrice  = $order_depth['first_askprice']-$minPrice;
            echo "====卖单价格===={$orderPrice}\n\n";
            if($orderPrice == $order_depth['first_bidprice']){
              $orderPrice = $order_depth['first_askprice'];
            }

              if($orderPrice > $maxunitmoney || $orderPrice < $minunitmoney){
                  //价格超出规定的范围
                  echo "卖单---价格超出预定范围\n";
                  $this->db_error("卖单---价格超出预定范围",$sym);
                  continue;
              }

            while(!empty($orderqueue)){  //撤单操作

               $val_stop = $this->buy_sell_order_status($orderqueue,$uid,$orderPrice,$sym,$order_num,'卖单');
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
                    if($bug < 3){
                      $bug++;
                      continue;
                    }else{
                      break;  
                    }
                    
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
                      $add['status']    = 1;
                      $add['order_price'] = $orderPrice;
                      $add['creat_time']  = time();
                      $add['buysell_id']  = $buysell_id;
                      $add['ratio']       = $ratio_type;
                      M('auto_buy_order','cash_')->add($add);
            
                      usleep(500000);
                  }
              }
            }
        }

        $result = $this->cash_buy_order(1,$sym,"卖单");

        if($result == 1){
          return ;
        }

        $stop_save['status'] = 6; 
        $stop_where['status'] = 1;
        M('auto_buy_order','cash_')->where($stop_where)->save($stop_save);
  
        
          $auto_where['sym1'] = $sym1;
          $auto_where['sym2'] = $sym2;
          $auto_where['ratio_type'] = $ratio_type;
          $val_auto = M('auto_order_buy','cash_')->where($auto_where)->find();

          if($bat_type == "B"){
            M('auto_sellbuy_order','cash_')->where("id = {$order_id}")->setInc('batches',$result['order_num']);
          }else{
            //$buy_sellorder_save['status']   = 1;
            $buy_sellorder_save['buy_sell'] = 0;
            $buy_sellorder_save['batches']  = 0;
            $buy_sellorder_save['order_num'] = $result['order_num'] + $val_auto['batches'] + $handle;
            M('auto_sellbuy_order','cash_')->where("id = {$order_id}")->save($buy_sellorder_save);
          }

          if(empty($val_auto)){
            //添加操作
            $add2['order_upmoney'] = $result['avg'] * 1.025;
            $add2['avg_money']     = $result['avg'];
            $add2['order_stopless_price'] = $result['avg'] * 1.025; 
            $add2['sym1']          = $sym1;
            $add2['sym2']          = $sym2;
            $add2['data']          = time();
            $add2['order_num']     = $result['order_num'];
            $add2['once_num']      = $once;
            $add2['maxunitmoney']  = $maxunitmoney;
            $add2['minunitmoney']  = $minunitmoney;
            $add2['uid']           = $uid;
            $add2['status']        = 0;
            $add2['order_id']      = $order_id;
            $add2['ratio_type']    = $ratio_type;
            M('auto_order_buy','cash_')->add($add2);
          }else{
            //修改操作
            $where4['id'] = $val_auto['id'];

            if($bat_type == "B"){
              var_dump($bat_type);
              M('auto_order_buy','cash_')->where($where4)->setInc('batches',$result['order_num']);
              var_dump(9999999999999999999999);
            }else{
              //$save2['status']  = 0;
              $save2['order_num'] = $result['order_num'] + $val_auto['batches'] + $handle;  //传参
              $save2['order_upmoney'] = $result['avg'] * 1.025;
              $save2['avg_money']     = $result['avg'];
              $save2['order_stopless_price'] = $result['avg'] * 1.025;
              $save2['batches']       = 0;
              $save2['handle_num']    = 0;
              M('auto_order_buy','cash_')->where($where4)->save($save2);
            }
            
          }

          if($bat_type == 'A'){
            $add_avg['order_num'] = $result['order_num'];
            $add_avg['buysell_id']  = 0;
          }else{
            $add_avg['order_num']   = $result['order_num'];
            $add_avg['buysell_id']  = 1;
          }
          $add_avg['symbol']    = $sym;
          $add_avg['order_price'] = $result['avg'];
          $add_avg['creat_time']  = time();
          $add_avg['order_id']    = 0;
          $add_avg['bt_type']     = 'sell-limit';
          $add_avg['status']      = 6;
          $add_avg['ratio']       = $ratio_type;
          M('auto_buy_order','cash_')->add($add_avg);
          $add_avg = array();
        $this->db_error("卖单---下单成功---当前交易的平均价格为:{$result['avg']}----手续费:{$result['field_fees']}",$sym);
        //return $status_val;
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
    /*
      *    1 当前最新的k线的成交量 > 上一k线成交量5倍
      *    成交量> 上5条成交量平均值的5倍
      *    (最高-最低)/最低 > 1.5%
      *    (收盘-最低)/(最高-最低) > 0.65
      *    上一k线的闭盘价格 * 0.99 < 当前k线的最低价
      */
    public function auto_buy($sym){
      $symbol = explode('-',$sym);
      while(true){
        //获取币对相应的数据
        $where['sym1'] = $symbol[0];
        $where['sym2'] = $symbol[1];
        $where['status'] = 1;
        $where['ratio'] = 1;
        $result = M('auto_sellbuy_order','cash_')->where($where)->order('id DESC')->find();

        if(empty($result)){
          echo "====暂无数据要操作=====\n\n";
          sleep(5);
          continue;
        }
        //获取最新的一条k线   5
        $req = new req();
        $kline_val = $req->get_history_kline($result['sym1'].$result['sym2'], '5min', 10);    
        if($kline_val['status'] != 'ok'){
          var_dump('数据请求错误');//exit;
          sleep(1);
          continue;
        }
          
        $now_kline_ratio = $kline_val['data'][0]['amount'] / $kline_val['data'][1]['amount'];
        $amount1 = $kline_val['data'][0]['amount'];
        $amount2 = $kline_val['data'][1]['amount'];
        echo "====当前k线成交量:{$amount1}====上一k线成交量:{$amount2}====相除:{$now_kline_ratio}=====\n";
        for($i=1;$i<6;$i++){
          $amount_num5 += $kline_val['data'][$i]['amount'];
        }
        $now_kline_ratio5 = $kline_val['data'][0]['amount'] / ($amount_num5 / 5);
        $avg = $amount_num5 / 5;
         echo "====总价格5:{$amount_num5}====平均价格:{$avg}====比例5:{$now_kline_ratio5}====\n";
        $uid = session('uid');

        if($now_kline_ratio > 5 && $now_kline_ratio5 > 5){

        $high_low = (($kline_val['data'][0]['high'] - $kline_val['data'][0]['low']) / $kline_val['data'][0]['low']) * 100;

        $close_low = ($kline_val['data'][0]['close'] - $kline_val['data'][0]['low']) / ($kline_val['data'][0]['high'] - $kline_val['data'][0]['low']);

           if($high_low > 1.5 && $close_low > 0.65){
              
               $high = $kline_val['data'][0]['high'];
               $low  = $klilne_val['data'][0]['low'];
               echo "====当前k线的最高价:{$high}====最低价:{$low}===\n\n";
              
               $up_price = $kline_val['data'][1]['close'] * 0.99;
               echo "====上  闭盘价格 * 0.99==={$up_price}==\n\n";

               if($up_price < $kline_val['data'][0]['low']){
                echo "条件全部符合===买入===\n\n";
                $data = date('Y-m-d H:i:s',time());
echo "====符合条件买入{$result['sym1']}==={$data}====\n\n";exit;
                $buy_order_val = $this->buy_order($result['sym1'],$result['sym2'],$result['order_num'],$result["once_num"],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['uid'],$result['ratio']);

                if($buy_order_val == 2){    //手动终止  不进行卖单操作
                     break;
                } elseif ($buy_order_val == 1){    //买单成功  进行卖单操作
                  echo "====卖出操作====\n\n";
                    $val = $this->auto_sell($result['sym1'],$result['sym2'],$result['id']);
                    if($val == 2){
                      continue;
                    }
                } 
                
               }

           }else{
             echo "====条件2不符合====\n\n";
             sleep(5);
           }
         }else{
           echo "====条件一不符合==now_kline:{$now_kline_ratio}===now_kline5:{$now_kline_ratio5}==\n\n\n";
           sleep(5);
         }
        $amount_num5 = 0;
    }

    }


    //策略卖单
    public function auto_sell($sym1,$sym2,$order_id){
        /*
        *    上一k线收盘价 * 1.01   或止损 1%（上  k线  close * 0.99）
        *    先卖出(上一k线收盘价格 * 1.01)  查询该订单的状态(60s)  如果没卖出 撤销订单  上  k线的close * 0.99  < 当前k线的close  卖出xd   
        */
        $status_val = 0;
        $where['sym1'] = $sym1;
        $where['sym2'] = $sym2;
        $where['status'] = 1;
        $where['ratio_type'] = 1;
        $result = M('auto_order_buy','cash_')->where($where)->find();
        $req = new req();
        $kline_val = $req->get_history_kline($result['sym1'].$result['sym2'], '5min', 10);

        $order_price = number_format($kline_val['data'][1]['close'] * 1.01,4);
        //卖出订单   限价
        
        $crontab = new \Home\Controller\CrontabController($result['uid']);
        $orderId = $crontab->getPlace($result['order_num'],$result['sym1'].$result['sym2'],'sell-limit',$order_price,'api',$result['uid']);
        
        if($orderId['status'] == 'error'){
          var_dump('下单接口报错');exit;
        }
            sleep(3);
        
        for($i=0;$i<60;$i++){
           echo "++++++dddddd\n\n";
          //查询订单的状态
          $order_status = $this->getOrder($orderId['data'],$result['uid']);
          var_dump($order_status);
          if($order_status['status'] == 'error'){
            var_dump('获取订单详情失败');exit;
          }

          if($order_status['data']['state'] == "filled"){
            echo "订单交易完成\n\n";
            $status = 1;
            break;
          } else {
            $status = 2;
          }
          sleep(1);

        }

        if($status != 1){
          //取消订单
          $repol_order = $this->getSubmitcancel($orderId['data'],$result['uid']);
          echo "----撤销订单操作-----\n\n";
          var_dump($repol_order);
          if($repol_order['status'] != 'ok'){
            var_dump('订单撤销失败');exit;
          }
          // //总数量
          // $result['order_num'] = $order_status['data']['amount'] - $order_status['data']['field-amount'];
          //查询订单状态
          $order_status = $this->getOrder($orderId['data'],$result['uid']);
          for($k=0;$k<3;$k++){
            if($order_status['data']['state'] == 'canceled' || $order_status['data']['state'] == 'partial-canceled'){
              $result['order_num'] = $order_status['data']['amount'] - $order_status['data']['field-amount'];
              break;
            }
          }
        }else{
          return 2;
        }

        //上一k线的close * 0.99 < 当前k线的low
        while(true){

          //获取最新的一条k线   5
          $req = new req();
          $kline_val = $req->get_history_kline($result['sym1'].$result['sym2'], '5min', 10);
          if($kline_val['data'][1]['close'] * 0.99 < $kline_val['data'][0]['low']){
            echo "======正常卖出====\n\n";
            //正常卖出
            $val = $this->place_sell_order($result['sym1'],$result['sym2'],$result['order_num'],$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['uid'],$order_id);
            if($val == 2){
              $status_val = 2;
              break;
            }
          }

        }
        return $status_val;
        
    }



    //策略2
    /*
    *  连续两条k线的收盘价(除去最新的) > 前50k线平均值(close) * 1.005  或者当前k线的成交量 >  上一k线成交量的2.5倍 && 当前价 > 前50条k线平均值(close)
    *
    */

    public function auto_buy3($sym){
      $symbol = explode('-',$sym);

      $where['sym1'] = $symbol[0];
      $where['sym2'] = $symbol[1];
      $where['ratio'] = 2;

      $arr_inital['status']  = 1;
      $arr_inital['status2'] = 1;
      $arr_inital['type']    = 0;
      $arr_inital['type2']   = 0;
      $this->arr = array();
      $remainder = 0;
      $this->close_money = 0;

      while(true){

        $remainder2 = $remainder % 2;
         var_dump($remainder);
        if($remainder2 == 0){

          if($this->stop == 1){
            $arr_inital['status'] = 0;
            $this->stop = 0;
          }else{
            $arr_inital['status'] = 1;
          }

          if($this->stop2 == 1){
            $arr_inital['status2'] = 0; 
            $arr_inital['type'] = 1;
            $this->stop2 = 0;
          }else{
            $arr_inital['status2'] = 1;
            $arr_inital['type'] = 0;
          }
          $this->arr = array();
        }

        //获取要执行的币对数
        $result = M('auto_sellbuy_order','cash_')->where($where)->find();

        if(empty($result)){
          echo "======暂时没有要执行的数据=======\n\n";
          continue;
        }

        //获取k线数据
        $kline = $this->get_kline($result['sym1'],$result['sym2'],60);

        if($kline == 1){
          continue;
        }

        $close_price = 0;
        for($i=1;$i<51;$i++){
          $close_price += $kline[$i]['close'];
        }

        $close_avg = $close_price / 50;

        //买单操作
        if($this->buy_inital($result) == 1){
          $arr_inital['status'] = 0;
        }

        $buy = $this->ratio2_buy($kline[1]['close'],$close_avg,$kline[2]['close'],$result,$arr_inital['status'],$kline[0]['close']);

        if($buy == 1){
          $arr_inital['status']  = 0;
          $arr_inital['status2'] = 1;
          $arr_inital['type']    = 1;
          $arr_inital['type2']   = 1;
          $this->arr = array();
          $remainder++;
          $this->close_money = 0;
        }

        if($result['buy_sell'] == 1){
          echo "====只进行买操作==========\n\n";
          sleep(3);
          continue;
        }
        
        //买入后止损操作
        if($arr_inital['status2'] == 1 && $arr_inital['type'] == 1){
          var_dump(11111111111111111111111);
          /*
          *    分批买入操作
          *    如果当前的close价格大于  平均价格 * 1.03   买入25%
          *    如果当前的close价格大于  平均价格 * 1.06   买入25%
          *    如果当前的close价格大于  平均价格 * 1.09   买入25%
          *    剩余的数量按正常流程操作
          */

          //获取当前要分批操作的数据
          $stopless_price = $this->buy_loss($kline,$result);
        }

        //卖出后止损
        if($arr_inital['status2'] == 0 && $arr_inital['type'] == 0){

          $sell_stop = $this->sell_loss($kline,$result);

          if($sell_stop['val_status'] == 1){
            $num2 = $result['order_num'] - $result['batches'] - $sell_stop['rallback_num'] - $result['handle_num'];

            $this->buy_order($result['sym1'],$result['sym2'],$num2,$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['uid'],$result['ratio']);

            $arr_inital['status']  = 0;
            $arr_inital['status2'] = 1;
            $arr_inital['type']    = 1;
            $this->arr = array();
            $remainder++;
            $this->close_money = 0;
            $this->stop2 = 1;
          }
          continue;
        }

         //卖出操作
        $sell = $this->ratio2_sell($stopless_price,$kline[1]['close'],$close_avg,$kline[2]['close'],$arr_inital['status2'],$arr_inital['type2'],$result,$kline[0]['close']);

        if($sell == 1){
          $arr_inital['status']  = 1;
          $arr_inital['status2'] = 0;
          $arr_inital['type']    = 0;
          $this->arr = array();
          $remainder++;
          $this->close_money = 0;
        }
      }
    }


    //获取k线数据
    public function get_kline($sym1,$sym2,$num){
      $req = new req();
      $kline = $req->get_history_kline($sym1.$sym2,'5min',$num);
      if($kline['status'] != 'ok'){
        echo "=======获取历史数据有误========\n\n";
        return 1;
      }else{
        return $kline['data'];
      }
    }


    //策略2买单操作
    public function buy_inital($val){

        if($val['status'] == 1){
          if($val['buy_sell'] == 2){
            echo "=====不允许买入操作===={$val['buy_sell']}=====\n\n";
            return 1;
          }
        }else{
          $aa = $val['status'];
          echo "===买单处于关闭状态===={$aa}===\n\n";
          var_dump(333333);exit;
        }

    }

    //买入后的止损操作
    public function buy_loss($kline,$result){
        //获取当前要分批操作的数据
      $bat_where['sym1'] = $result['sym1'];
      $bat_where['sym2'] = $result['sym2'];
      $bat_where['ratio_type'] = 2;
      $batches_val = M('auto_order_buy','cash_')->where($bat_where)->find();

      $batches = $this->buy_batches($kline,$batches_val);

      if($batches == 1){  //分批操作
        $result = $batches_val;
        $num_order = $batches_val['order_num'] * 0.1;
        $this->place_sell_order($result['sym1'],$result['sym2'],$num_order,$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['uid'],$result['id'],$result['ratio'],'B',$result['handle_num']);
      }

      //止损操作
      $stopless_price = $this->stopPrice($result['sym1'],$result['sym2'],$result['ratio']);
      echo "=====stopless_price等于1卖出========={$stopless_price}=================\n\n";
var_dump(9999999999999999999999);
      return $stopless_price;
    }


    //买入后分批操作
    public function buy_batches($kline,$val){
      $sta = 0;
      if($kline[0]['close'] > $val['avg_money'] * 1.01 && $kline[0]['close'] < $val['avg_money'] * 1.02 && !in_array(1,$this->arr)){
        array_push($this->arr,1);
        echo "=====卖出比例1==========\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] > $val['avg_money'] * 1.02 && $kline[0]['close'] < $val['avg_money'] * 1.03 && !in_array(2,$this->arr)){
        array_push($this->arr,2);
        echo "=====卖出比列2==========\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] > $val['avg_money'] * 1.03 && $kline[0]['close'] < $val['avg_money'] * 1.04 && !in_array(3,$this->arr)){
        array_push($this->arr,3);
        echo "======卖出比例3===========\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] > $val['avg_money'] * 1.04 && $kline[0]['close'] < $val['avg_money'] * 1.05 && !in_array(4,$this->arr)){
        array_push($this->arr,4);
        echo "=======卖出比例4==============\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] > $val['avg_money'] * 1.05 && !in_array(5,$this->arr)){
        array_push($this->arr,5);
        echo "====卖出比例5========\n\n";
        $sta = 1;
      }
      return $sta;
    }

    public function sell_loss($kline,$result){
      //获取平均价格
      $avg_where['sym1'] = $result['sym1'];
      $avg_where['sym2'] = $result['sym2'];
      $avg_where['ratio_type'] = 2;
      $avg = M('auto_order_buy','cash_')->where($avg_where)->getField('avg_money');

      $batches = $this->sell_batches($kline,$avg);

      $rallback_num = 0;
      if($batches == 1){
        $num = $result['order_num'] * 0.1;
        $rallback_num = $this->buy_order($result['sym1'],$result['sym2'],$num,$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['uid'],$result['ratio'],'B');
      }

      $val_status = $this->sell_stop_price($result['sym1'],$result['sym2'],$result['ratio']);

      $arr['rallback_num'] = $rallback_num;
      $arr['val_status']   = $val_status;
      return $arr;
    }

    public function sell_batches($kline,$avg){
      $sta = 0;
      if($kline[0]['close'] < $avg * 0.99 && $kline[0]['close'] > $avg * 0.98 && !in_array(1,$this->arr)){
        array_push($this->arr,1);
        echo "====买单比列1=========\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] < $avg * 0.98 && $kline[0]['close'] > $avg * 0.97 && !in_array(2,$this->arr)){
        array_push($this->arr,2);
        echo "=====买单比例2===============\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] < $avg * 0.97 && $kline[0]['close'] > $avg * 0.96 && !in_array(3,$this->arr)){
        array_push($this->arr,3);
        echo "=====买单比例3=============\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] < $avg * 0.96 && $kline[0]['close'] > $avg * 0.95 && !in_array(4,$this->arr)){
        array_push($this->arr,4);
        echo "=======买单比例4===================\n\n";
        $sta = 1;
      } elseif ($kline[0]['close'] < $avg * 0.95 && !in_array(5,$this->arr)){
        array_push($this->arr,5);
        echo "=======买单比列5=================\n\n";
        $sta = 1;
      }
      return $sta;
    }


    //策略2 买单操作
    public function ratio2_buy($up1_close,$close_avg,$up2_close,$result,$status,$now_close){
        if($status == 0){
          echo "====status为0==不允许执行买入操作===\n\n";
          return 2;
        }
        //上  k_close   > avg_50   &&   当前价  >   avg_50 * 1.01
        if(($up1_close > $close_avg * 1.004 && $up2_close > $close_avg * 1.004) || ($up1_close > $close_avg && $now_close > $close_avg * 1.01)){
            echo "====符合买入条件===\n\n";
            //手动买入
            if($result['handle_num'] > 0){
              $num = $result['order_num'] - $result['batches'] - $result['handle_num'];
            }else{
              $num = $result['order_num'] - $result['batches'];
            }
            
            $this->buy_order($result['sym1'],$result['sym2'],$num,$result['once_num'],$result['minunitmoney'],$result['maxunitmoney'],$result['id'],$result['uid'],$result['ratio']);

            echo "====买入结束===\n\n";
            sleep(2);
            return 1;
        }else{
            //return 2;
            echo "====买入条件不符合====\n\n";
            sleep(2);
        }
    }

    //策略2  卖单操作
    public function ratio2_sell($stopless_price,$up1_close,$close_avg,$up2_close,$status2,$type2,$result,$now_close){
      
      if(($stopless_price == 1) || ($up1_close < $close_avg * 0.996 && $up2_close < $close_avg * 0.996) || ($up1_close < $close_avg && $now_close < $close_avg * 0.99)){
          
          if($status2 == 1){
              if($type2 == 1){
                //已经执行过买单   
                $where2['ratio_type'] = 2;
                //$where2['status']     = 1;
                $where2['sym1']       = $result['sym1'];
                $where2['sym2']       = $result['sym2'];
                $sell_val   = M('auto_order_buy','cash_')->where($where2)->find();
                $order_id   = $sell_val['order_id'];
                $ratio_type = $sell_val['ratio_type'];
                $handle     = $sell_val['handle_num'];
              }else{
                $sell_val   = $result;
                $order_id   = $sell_val['id'];
                $ratio_type = $sell_val['ratio'];
                $handle     = $sell_val['handle_num'];
              }

              if($sell_val['status'] == 0){
                echo "====卖单强制关闭===status:0====\n\n";
              }else{
                //卖单操作
                if($sell_val['handle_num'] > 0){
                  $sell_order_num = $sell_val['order_num'] - $sell_val['batches'] - $sell_val['handle_num'];
                }else{
                  $sell_order_num = $sell_val['order_num'] - $sell_val['batches'];
                }
                $val = $this->place_sell_order($sell_val['sym1'],$sell_val['sym2'],$sell_order_num,$sell_val['once_num'],$sell_val['minunitmoney'],$sell_val['maxunitmoney'],$sell_val['id'],$sell_val['uid'],$order_id,$ratio_type,'A',$handle);
                echo "====卖单执行完毕====\n\n";


                switch($stopless_price){
                  case 1:
                      $this->stop = 1;
                      break;
                  case 2:
                      $this->stop = 2;
                      break;
                  default :
                      break;
                }
                return 1;
              }
          }else{
            echo "====卖单条件不允许===status2:0===\n\n";
          }
      }else{
        echo "===卖单条件不符合======\n\n";
      }
    }



     /*
     *  止损操作    买入
     */
    public function stopPrice($sym1,$sym2,$ratio_type){
     
        $req = new req();
        $kline = $req->get_history_kline($sym1.$sym2,'5min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误'; 
            return $str;
        }

        //获取相应的数据
        $where['sym1'] = $sym1;
        $where['sym2'] = $sym2;
        $where['ratio_type'] = $ratio_type;
        $result = M('auto_order_buy','cash_')->where($where)->find();

        $closeMoney = $kline['data'][0]['close'];  //下单后请求的闭盘价格
        echo "闭盘价格===".$closeMoney."\n";
        
        if($closeMoney > $result['avg_money']){

            if($closeMoney < $result['order_stopless_price']){
              echo "====当前闭盘价 < 止损价=======\n\n";
              return 1;
            }
            //比例
            $proportion = ($closeMoney / $result['avg_money']) - 1;
            //var_dump($proportion);var_dump($closeMoney);var_dump($result['avg_money']);exit;
                echo "==计算增长的比例===={$proportion}==\n\n";
            //最高价
            if($closeMoney > $result['up_money']){
                $up_money = $closeMoney;
            }

            if($proportion <= 0.05){

               echo "11111\n\n";
               $stop_money = $up_money * 0.975;

             } elseif (0.05 < $proportion && $proportion <= 0.15){

                echo "222222\n\n";
               $stop_money = $up_money * 0.95;

             } elseif (0.15 < $proportion && $proportion <= 0.25){

                echo "3333333\n\n";
                $stop_money = $up_money * 0.925;
             } elseif ($proportion > 0.25){
                 echo "444444\n\n";
                $stop_money = $up_money * 0.9;
             }
             var_dump($this->close_money);
             if($this->close_money == 0){
                $this->close_money = $closeMoney;
                //修改数据
               $save['order_upmoney'] = $up_money;
               $save['order_stopless_price'] = $stop_money;
               $where['id'] = $result['id'];
               M('auto_order_buy','cash_')->where($where)->save($save);
             }

             if($closeMoney > $this->close_money){
               //修改数据
               $save['order_upmoney'] = $up_money;
               $save['order_stopless_price'] = $stop_money;
               $where['id'] = $result['id'];
               M('auto_order_buy','cash_')->where($where)->save($save);
               $this->close_money = $closeMoney;
             }
            $status = 2;
        } else {
              echo "====闭盘价格小于最大价格  大于止损价格==========\n\n";
              $status = 2;
        }

         return $status;

    }


    //卖出止损
    public function sell_stop_price($sym1,$sym2,$ratio_type){
        $req = new req();
        $kline = $req->get_history_kline($sym1.$sym2,'5min',2);
        if($kline['status'] != 'ok'){
            $str = '接口数据请求有误'; 
            return $str;
        }

         //获取相应的数据
        $where['sym1'] = $sym1;
        $where['sym2'] = $sym2;
        $where['ratio'] = $ratio_type;
        $result = M('auto_order_buy','cash_')->where($where)->find();

        //获取闭盘价格
        $closeMoney = $kline['data'][0]['close'];

        if($closeMoney < $result['order_upmoney'] && $closeMoney < $result['avg_money']){   //开始止损
          if($closeMoney > $result['order_stopless_price']){
               //卖出
              return 1;
          }
           //止损比例
          
          $proportion = ($result['avg_money'] - $closeMoney) / $result['avg_money'];
        
          //最低价
          if($closeMoney < $result['order_upmoney']){
            $up_money = $closeMoney;
          }

          if($proportion < 0.05){
            $stopLessMoney = $closeMoney + $closeMoney * (1 - 0.975);
          } elseif (0.05 < $proportion  && $proportion< 0.15){
            $stopLessMoney = $closeMoney + $closeMoney * (1 - 0.95);
          } elseif (0.15 < $proportion && $proportion < 0.25){
            $stopLessMoney = $closeMoney + $closeMoney * (1 - 0.925);
          } elseif ($proportion > 0.25){
            $stopLessMoney = $closeMoney + $closeMoney * (1 - 0.9);
          }
          if($this->close_money == 0){
            $this->close_money = $closeMoney;
            $up = M('auto_order_buy','cash_');
            $up->order_stopless_price = $stopLessMoney;
            $up->order_upmoney = $up_money;
            $up->where("id = {$result['id']}")->save(); 
          }
          if($closeMoney < $this->close_money){
            $up = M('auto_order_buy','cash_');
            $up->order_stopless_price = $stopLessMoney;
            $up->order_upmoney = $up_money;
            $up->where("id = {$result['id']}")->save(); 
            $this->close_money = $closeMoney;
          }
  
        }elseif($closeMoney  > $result['order_stopless_price']){
          //建议卖出
          return 1;
        }
        return 2;
    }



   /*
   *   策略3
   *   当前价(close) > 前50条k_close平均值  && MACD(买入)     买入操作
        MACD(卖出)   卖出操作 

   *   当前价(close) < 前50条k_close平均值  && MACD(卖出)    卖出操作
        MACD(买入)   买入操作
   *  开始时执行时判断当前符合那个条件  如果符合第一个  那么第二个将不在执行  要等到第一个卖出操作结束后  重新判断当前符合那个操作
   */
   public function auto_buy4($sym){
      $symbol = explode('-',$sym);
     // var_dump($symbol);exit;
      $where['sym1']  = $symbol[0];
      $where['sym2']  = $symbol[1];
      $where['ratio'] = 3;
      $status  = 0;
      $status2 = 0;
      // $status3 = 1;
      // $status4 = 1;
      $type    = 0;
      while(true){
        //获取要操作的币对
        $result = M('auto_sellbuy_order','cash_')->where($where)->find();

         if(empty($result)){
           echo "=====没有操作的数据====\n\n";
           sleep(3);
           continue;
         }

        //获取k线
       // $req = new req();
       // $kline = $req->get_history_kline($symbol[0].$symbol[1],'5min',10);


         //数据库中 获取k线
         $wheres['r_time'] = "5min";
         $wheres['chid']   = 208;
         $kline = M('kline','cb_')->where($wheres)->order('kid DESC')->limit(1000)->select();

         $kline = array_reverse($kline);

        $close_price = 0;
       
        foreach($kline as $k=>$v){
          if($k < 266){
            continue;
          }

          $stop_num = $k-50;
          $stop_num2 = $k-266;
          for($i=$k;$i>$stop_num;$i--){
            $close_price += $kline[$i]['close'];
          }

          for($ii=$k;$ii>$stop_num2;$ii--){
            $arr_num[] = $kline[$ii];
          }
          
          $avg_price = $close_price / 50;
          $close_price = 0;

          $MACD = A('MACD');
          $macd_status = $MACD->macd2($arr_num);
          $arr_num = array();
          if($status2 == 0 && $status == 0){
            if($kline[$k]['close'] > $avg_price && $macd_status == 1){
              //买入操作  1   
              $data = date('Y-m-d H:i:s',$kline[$k]['kid']);
              $close_price1 = $kline[$k]['close'];
              $buy1_arr[] = $data.'=========='.$close_price1;
              $status = 1;
            }else{
              echo "===========1==买入条件不符合================={$macd_status}==============\n\n";
            }
          }else{
            echo "==============1====不执行买入条件=================================\n\n";
          }

          if($status == 1){
            if($macd_status == 2){
              $data2 = date('Y-m-d H:i:s',$kline[$k]['kid']);
              $close_price2 = $kline[$k]['close'];
              $sell1_arr[] = $data2.'=========='.$close_price2;
              $status  = 0;
            }else{
              echo "==========1====卖出条件不符合====================={$macd_status}=============\n\n";
            }
          }else{
            echo "==============1===========不执行卖出条件===========================================\n\n";
          }


          if($status == 0 && $status2 == 0){
            if($kline[$k]['close'] < $avg_price && $macd_status == 2){
              //卖出  2
              $sell2_arr[] = date('Y-m-d H:i:s',$kline[$k]['kid']).'=========='.$kline[$k]['close'];;

              $status2 = 1;
            }else{
              echo "+++++++++++2+++卖出条件不符合++++++++++++++++{$macd_status}++++++++++++++++++++\n\n";
            }
          }else{
            echo "+++++++++2+++++不执行卖出条件++++++++++++++++++++++++++++++++++++++\n\n";
          }


          if($status2 == 1){
            if($macd_status == 1){
              $buy2_arr[] = date('Y-m-d H:i:s',$kline[$k]['kid']).'======='.$kline[$k]['close'];;
              $status2 = 0;
            }else{
              echo "+++++++2++++++++买入条件不符合+++++++++++++++++{$macd_status}++++++++++++++\n\n";
            }
          }else{
            echo "+++++2++++++++++不执行买入条件+++++++++++++++++++\n\n";
          }
        }
var_dump($buy1_arr);var_dump($sell1_arr);var_dump($sell2_arr);var_dump($buy2_arr);exit;
      }
   }

  public function long2(){
    $map = "symbol = 'eosusdt' and id >= 11704 and ratio = 2 and order_id = 0 and buysell_id <> 1";
    $result = M('auto_buy_order','cash_')->where($map)->select();
    return $result;
  }

   //计算增长
   public function long(){
    $map = "symbol = 'eosusdt' and id >= 11704 and ratio = 2 and order_id = 0 and buysell_id = 1";
    $result = M('auto_buy_order','cash_')->where($map)->select();
    $num = count($result);
    $val = $this->long2();
    var_dump(count($val));
    for($i=0;$i<count($val);){
      for($j=0;$j<count($result);$j++){
        if($result[$j]['id'] > $val[$i]['id'] && $result[$j]['id'] < $val[$i+1]['id']){
          $arr[$i+1]['num_price1'] += $result[$j]['order_num'] * $result[$j]['order_price'];
          $arr[$i+1]['num_price2'] = $val[$i+1]['order_num'] * $val[$i+1]['order_price'];
          $arr[$i+1]['num1']   += $result[$j]['order_num'];
          $arr[$i+1]['num2']    = $val[$i+1]['order_num'];
        }
      }
      $i += 2;
    }
    foreach($arr as $k=>$v){
      $val[$k]['order_price'] = ($v['num_price2'] + $v['num_price1']) / ($v['num1'] + $v['num2']);
    }
    var_dump($arr);
    var_dump($val);
    
   }






    //获取要发送的内容
  public function codeMessage($phone,$msg){
    //$verify = "贾腾飞";
    //手机号
    $mobile = $phone;
    $argv = array(
            'account' => 'ms-weier',
            'pswd' => 'MSweier0929',
            'msg' => $msg,
            'mobile' => $mobile,
            'product' => '',
            'needstatus' => 'false',
    );
    return $argv;
  }

  //发送操作
  public function msgSend(){
    $phone = '17710470581';
    $msg = '[微耳钱包]尊敬的用户您好！请到微信公众号里下载APP（苹果与安卓系统都可下载），并在手机认证信息中授权一下“手机通讯录”后再提交！谢谢您的配合～';
    $message = $this->codeMessage($phone,$msg);
    $curl = "http://114.55.176.84/msg/HttpSendSM";
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$curl);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_POST,1);
    curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($message));
    $output = curl_exec($ch);
    curl_close($ch);
    $arr = explode(',',$output);
    var_dump($arr);
    return $arr['1'];

  }

}
