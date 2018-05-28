<?php
namespace Home\Controller;
use Think\Exception;
use JYS\huobi\req;

class TradSimController extends TradController
{

    public $startTime;

    public $endTime;

    public $jumpTime;

    public $obj_arr = [];

    public $placeOrder;
    
    public $fileHandler = [];
    
    public $all_cost = [];
    
    public $crontab;
    
    public $system;
    

//    public function __construct() {
//        date_default_timezone_set('Asia/Shanghai');
//    }
    
    public function updatePrecision() {
        $precision = $this->placeOrder->crontab->getCommonSymbols();
        if ($precision == null) {
            //error
        } else {
            foreach ($precision as $v) {
                $symbol = $v['base-currency'].$v['quote-currency'];
                $this->placeOrder->price_precision[$symbol] = $v['price-precision'];
                $this->placeOrder->amount_precision[$symbol] = $v['amount-precision'];
            }
        }
    }
    
    public function index()
    {        
        $name = $_REQUEST['name'];
        $str = C('config') . $name;
        // var_dump($str);exit;
        // \Home\Controller\TradSimdController()
        // $trad = new \Home\Controller\TradSimController(new $str);
        $obj = new $str();

        
                
        // 声明新的对象
        $this->system = $obj->system;
        $strategv = $obj->strategy;
        $orderStra = $obj->orderStra;
        $config_str = C('config');

        $this->system['sympair'] = [];
        $arr = M('cb_channel')->field('symbol,chid')->select();
        foreach ($arr as $v) {
            $this->system['sympair'][$v['symbol']] = $v['chid'];
        }
        
        
        if(!isset($this->system['timeZone'])) {
            $this->system['timeZone'] = 0;
        }
        //init order strategy
        $nobj = $config_str . $orderStra['class'];
        $this->placeOrder = new $nobj($this->system, $orderStra['params']);
        $this->fileHandler['portfolio'] = fopen('portfolio','w');
        $this->all_cost['portfolio'] = 0;
        $str = "time              type   ALL     ";
        foreach($this->placeOrder->assetName as $v) {
            $str = $str." ".str_pad($v."_a",8)." ".str_pad($v."_m",8);
        }
        $str = $str." est_cost";
        fprintf($this->fileHandler['portfolio'], "%s\n", $str);
        
        //init the crontab in placeOrder
        $crontab_str = C($this->system['platform'].'Crontab');
        $cr_obj = $config_str.$crontab_str;
        $this->placeOrder->crontab = new $cr_obj();
        
//        $res = $this->placeOrder->crontab->getCommonSymbols();
        $this->updatePrecision();
//        var_dump($this->placeOrder->price_precision);
//        var_dump($this->placeOrder->amount_precision);
        $sum_asset = [0];
        for ($i=0; $i<=$this->placeOrder->assetLen; $i++) {
            $this->placeOrder->holdAsset[$i] = 0;
        }
        foreach ($strategv as $k => $v) {
            $obj_mytrade = $config_str . $v['class'];
            $this->obj_arr[$k] = new $obj_mytrade($this->system, $v['params']);
            $this->fileHandler[$k] = fopen($k.'_pnl','w');
            $str = "time              type   ALL     ";
            foreach($this->obj_arr[$k]->assetName as $v) {
                $str = $str." ".str_pad($v."_a",8)." ".str_pad($v."_m",8);
            }
            $str = $str." est_cost";
            fprintf($this->fileHandler[$k], "%s\n", $str);
            $this->all_cost[$k] = 0;
            for ($i=0; $i<=$this->obj_arr[$k]->assetLen; $i++) {
                $this->placeOrder->holdAsset[$i] += $this->obj_arr[$k]->holdAsset[$i];
            }
        }
        if($this->system['is_live']) {
            printf("accountId:%d\n", $this->placeOrder->crontab->accountId);
            $res = $this->placeOrder->crontab->getAccountIdBalance();
            $all_money = $res[strtolower($this->system['base'])]['trade'];
            $frozen_money = $res[strtolower($this->system['base'])]['frozen'];
            printf("available money:%f\n", $all_money);
            printf("frozen money:%f\n", $frozen_money);
            printf("ht:%f\n", $res['ht']['trade']);
            $strasize = count($this->obj_arr);
            foreach ($this->obj_arr as $k=>$v) {
                for ($i=0; $i<=$this->placeOrder->assetLen; $i++) {
                    $theassetnum = $res[$this->placeOrder->assetName[$i]]['trade'];
                    if($this->placeOrder->holdAsset[$i]==0) {
                        if($theassetnum>0) {
                            $v->holdAsset[$i] = $theassetnum/$strasize;
                        }
                    } else {
                        $v->holdAsset[$i] = $v->holdAsset[$i]*$theassetnum/$this->placeOrder->holdAsset[$i];
                    }
                }
                $v->updateHoldPosFromAsset();
            }
            for ($i=0; $i<=$this->placeOrder->assetlen; $i++) {
                $this->placeOrder->holdAsset[$i] = $res[$this->placeOrder->assetName[$i]]['trade'];
            }    
            $this->placeOrder->updateHoldPosFromAsset();
        }
        
//         $amount = $all_money;
//         $pos = strpos($amount, '.');
//         $amount = substr($amount, 0, $pos+5);
//         var_dump($amount);
//         $orderId = $this->placeOrder->crontab->getPlace($amount,'btcusdt','sell-limit',10000.1);
//         var_dump($orderId);
//         $res = $this->placeOrder->crontab->getOrder($orderId);
//         var_dump($res);
//         $res = $this->placeOrder->crontab->getOrders();
//         var_dump($res);
//        printf("sleep\n");
//        sleep(3);
//        $res = $this->placeOrder->crontab->getSubmitcancel($orderId);
//        if($res<0) {
//            printf("cancel %s fail\n", $orderId);
//        } else {
//            printf("cancel %s successful", $orderId);
//        }
//        exit;
        // var_dump($this->obj_arr);exit;
        
        try {
            if($this->system['is_live']) {
                $this->runLive();
            } else {
//                $this->startTime = gmdate('Y-m-d H:i:s',time() + 8*3600) * 1000;
                $this->startTime = (strtotime($obj->system['startTime'])-$this->system['timeZone']*3600)*1000;
                var_dump($this->startTime);
                $this->endTime = (strtotime($obj->system['endTime'])-$this->system['timeZone']*3600) * 1000;
                $this->jumpTime = $obj->system['jumpTime'] * 1000;
                $this->sim();
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
        // var_dump($trad);exit;
    }

    public function print_rets_market($ti, $k, $v) {
        $est_cost = 0;
        fprintf($this->fileHandler[$k], "%s market %s", gmdate('Ymd H:i:s', $ti / 1000+$this->system['timeZone']*3600),str_pad(substr(array_sum($v->holdPos),0,8),8));
        for ($i = 0; $i <= $v->assetLen; $i ++) {
            fprintf($this->fileHandler[$k], " %s %s", str_pad(substr($v->holdAsset[$i],0,8),8), str_pad(substr($v->holdPos[$i],0,8),8));
        }
        fprintf($this->fileHandler[$k], " %s", str_pad(substr($est_cost,0,8),8));
        fprintf($this->fileHandler[$k],"\n");
    
    }
    
    public function print_rets_order($ti, $k, $v) {
        $est_cost = 0;
        fprintf($this->fileHandler[$k], "%s order  %s", gmdate('Ymd H:i:s', $ti / 1000+$this->system['timeZone']*3600),str_pad(substr(array_sum($v->objPos),0,8),8));
        for ($i = 0; $i <= $v->assetLen; $i ++) {
            fprintf($this->fileHandler[$k], " %s %s", str_pad(substr($v->objAsset[$i],0,8),8), str_pad(substr($v->objPos[$i],0,8),8));
            if($i>0) {
                $est_cost += abs($v->objPos[$i]-$v->holdPos[$i])*0.0015;
            }
        }
        $this->all_cost[$k] += $est_cost;
        fprintf($this->fileHandler[$k], " %s", str_pad(substr($est_cost,0,8),8));
        fprintf($this->fileHandler[$k],"\n");
        
    }
    
    /*
     * 获取所有策略的总的hold仓位
     */
    public function get_all_hold() {
        for ($i=0; $i<=$this->assetLen; $i++) {
            $this->placeOrder->holdAsset[$i] = 0;
        }
        foreach ($this->obj_arr as $k=>$v) {
            for ($i=0; $i<=$this->assetLen; $i++) {
                $this->placeOrder->holdAsset[$i] += $v->holdAsset[$i];
            }
        }
        
    }
    /*
     * 获取所有策略的总的obj仓位
     */
    public function get_all_obj() {
        for ($i=0; $i<=$this->assetLen; $i++) {
            $this->placeOrder->objPos[$i] = 0;
        }
        foreach ($this->obj_arr as $k=>$v) {
            for ($i=0; $i<=$this->assetLen; $i++) {
                $this->placeOrder->objPos[$i] += $v->objPos[$i];
            }
        }    
    }
    

    
    public function runTrade($ti) {
        $this->placeOrder->prepareData($ti);
        $this->placeOrder->updateHoldPosFromAsset();
        $this->print_rets_market($ti, 'portfolio', $this->placeOrder);
//        var_dump(array_slice($this->placeOrder->kline_datas, -2));
        print('last kline ts '.$this->placeOrder->hist_kline_ts[count($this->placeOrder->hist_kline_ts)-1].' in '.($ti/1000)."\n");
        $preObjAsset = [];  //直接将所有策略目标仓位相加，存储理想的目标仓位
        for ($i=0; $i<=$this->placeOrder->assetLen; $i++) {
            $preObjAsset[$i] = 0;
            printf("current close:%s\n", $this->placeOrder->kline_datas[count($this->placeOrder->kline_datas)-1][$i]['close']);
        }
        foreach ($this->obj_arr as $k => $v) {
            $v->prepareData($ti);
            $v->prepareDepData($ti);
            $v->updateHoldPosFromAsset();
//            $v->updateAssetMoney();
            print(' changeto ');
            for ($i = 0; $i <= $v->assetLen; $i ++) {
                print(' '.$v->assetName[$i].':'.$v->holdPos[$i].'/'.$v->holdAsset[$i]);
            }
            print("\n");
            $this->print_rets_market($ti, $k, $v);
            $v->objPos = $v->holdPos;
            $v->getPos($ti);
            //                var_dump($v->holdPos);
            //                var_dump($v->objPos);
            $v->adjustObjPos();
            $v->updateObjAsset();
            $v->stopLossSim($k, $ti);
            for ($i=0; $i<$v->assetLen; $i++) {
                $preObjAsset[$i] += $v->objAsset[$i];
            }
            printf('%s updateto ',$k);
            for ($i = 0; $i <= $v->assetLen; $i ++) {
                print(' '.$v->assetName[$i].':'.$v->objPos[$i].'/'.$v->objAsset[$i]);
            }
            print("\n");
            // var_dump($newPos);
             
        }
        $this->get_all_obj();
        if($this->placeOrder->is_live) {
            $this->placeOrder->tradeLive('portfolio', $ti);
        } else {
            $this->placeOrder->updateObjAsset();
        }
//        var_dump($this->placeOrder->objPos);
        //打印交易明细
        $this->print_rets_order($ti, 'portfolio', $this->placeOrder);
        //更改当前持有量，准备下一轮
        if($this->placeOrder->objPos[0]!=$this->placeOrder->holdPos[0]) {
            var_dump($this->placeOrder->holdPos);
            var_dump($this->placeOrder->objPos);
            exit;
        }
        $this->placeOrder->holdPos = $this->placeOrder->objPos;
        $this->placeOrder->holdAsset = $this->placeOrder->objAsset;
        
        //如果live，需要调整各个策略的仓位，根据实际交易结果
        if($this->system['is_live']) {
            $strasize = count($this->obj_arr);
            foreach ($this->obj_arr as $k => $v) {
                for ($i=0; $i<=$v->assetLen; $i++) {
                    if($preObjAsset[$i]==0) {
                        $v->objAsset[$i] = $this->placeOrder->objAsset[$i]/$strasize;
                    } else {
                        $v->objAsset[$i] = $v->objAsset[$i]*$this->placeOrder->objAsset[$i]/$preObjAsset[$i];
                    }
                }
                $v->updateObjPosFromAsset();

            }
        } 
        //输出各个策略的实际收益
        foreach ($this->obj_arr as $k => $v) {
            $this->print_rets_order($ti, $k, $v);
            for ($i=1; $i<=$v->assetLen; $i++) {
                if($v->objAsset[$i]==0) {
                    $v->upMoney[$i] = 0;
                    $v->order_money[$i] = 0;
                    $v->stopLessMoney[$i] = 0;
                }
            }
            $v->holdPos = $v->objPos;
            $v->holdAsset = $v->objAsset;
        }
        
        
    }
    
    public function runLive() {
        while(true) {
            printf("begin trade\n");
            $ti = time();
            $ti *= 1000;
//            print("trade before ".date('Ymd H:i:s', $ti / 1000)."\n");
//            $this->placeOrder->prepareData($ti);
            print("trade in ".gmdate('Ymd H:i:s', $ti / 1000+$this->system['timeZone']*3600)."\n");
            $this->runTrade($ti);
            sleep(20);
            continue;
            $stra = 'my1';
            $changeUnit = [0,1];
            $symbol = ['','htbtc'];
            $reverse = [false, false];
            $this->placeOrder->trade($stra, $ti, $changeUnit, $symbol, $reverse);
//            $this->runTrade($ti);
        }
    }
    
    // 模拟交易
    public function sim()
    {
        for ($ti = $this->startTime; $ti < $this->endTime;) {
            // 调用getpos函数
            $this->runTrade($ti);
            $ti += $this->jumpTime;
        }
        printf("est cost:%f", $this->all_cost[portfolio]);
    }
}
