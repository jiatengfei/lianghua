<?php
namespace Home\Controller;
use JYS\huobi\req;
class TradController extends HomeController
{
    public $sympair = ['htbtc' => 237];
    public $holdPos = [];
 // 当前持有量
    public $holdAsset = [];
 // 当前持有量
    protected $money;
 // 当前持有的资金
    public $objPos = [];
    
    public $objAsset = [];
 // getpos函数返回的值
                    // protected $short; //做空
    protected $config = [];

    public $assetLen;
 // 可交易资产的数量
    protected $base;

    public $assetName = [];

    protected $declist;

    protected $is_live;
 // 状态
    protected $rtime;

    protected $back_segs;

    public $hist_kline_ts = [];
 // 数组存放到模拟时间为止的所有kline的时间
    public $kline_datas = [];
    
    public $price_precision = [];
    public $amount_precision = [];
    
    public $crontab; //只有placeOrder才会用到
    
    public $order_money;
    public $upMoney;
    public $stopLessMoney;

    public $system;
    public $params;
    protected $m;

    public function __construct($system, $params = array())
    {
        parent::__construct();
        $this->base = $system['base'] ? strtolower($system['base']) : 'ht';
        $this->declist = C($this->base);
        $this->is_live = $system['is_live'];
        $this->money = $params['money'] ? $params['money'] : '10000';
        $this->rtime = $params['r_time'] ? $params['r_time'] : '15min';
        $this->back_segs = $system['back_segs'] ? $system['back_segs'] : 50;
        $this->assetLen = count($this->declist);
        $this->assetName[0] = $this->base;
        foreach ($this->declist as $k => $v) {
            $this->assetName[$k + 1] = strtolower($v);
        }
        // 持有量
        $this->holdPos[0] = $this->money;
        $this->holdAsset[0] = $this->money;
        for ($i = 0; $i < $this->assetLen; $i ++) {
            $this->holdPos[$i + 1] = 0;
            $this->holdAsset[$i + 1] = 0;
            $this->order_money[$i+1] = 0;
            $this->upMoney[$i+1] = 0;
            $this->stopLessMoney[$i+1] = 0;
        }
        $this->system = $system;
        $this->params = $params;
        // var_dump($this->holdPos);exit;
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
    
    // 获取kline数据
    /*
     * 获取kline数据 获取ti时间时最新的$num 条kline，$rtime 为 1min,5min..
     * 返回数组 key为open close hight low volume count
     */
    public function get_kline_data($ti = '1521986400', $asset = 'usdt', $num = 10, $rtime = '15min')
    {
        // 拼接币对 $this->base.$asset
        // 获取相应币对的chid
        //$chid = $this->bt_line($asset)['chid'];
        $chid = $this->bt_lines($this->assetName[0], $this->assetName[$asset]);
        if($this->is_live){
            //调用接口
                $kline_price = $this->crontab_kline($ti,$chid['symbol'],$this->rtime,$chid[3]);
            
        }else{
            $where_k['kid'] = [
                'lt',
                $ti
            ];
                $where_k['r_time'] = $rtime;
                $where_k['chid'] = $chid['chid'];
                $result = M('cb_kline')->where($where_k)
                    ->order('kid ASC')
                    ->limit($num)
                    ->select();
                
                $ch_time = $result[count($result) - 1]['kid'] - $ti;
                if (array_keys(C('timeType'), $rtime)[0] != $ch_time) {
                    var_dump('数据获取错误');
                    exit();
                }
                
                // 将价格转换
                foreach ($result as $v) {
                    if($chid[3] == 0){
                        $transform = $this->convert_kline_data($v);
                    }else{
                        $transform = $v;
                    }
                    $kline_price[] = $transform;
                }
        }
        return $kline_price;
    }

    /**
     *  kline实时接口调用
     */
    public function crontab_kline($ti,$symbol,$val_num,$type){
        $req = new req();
        $kline_realTime = $req->get_history_kline($symbol, $val_num, 100);
        if($kline_realTime['status'] != 'ok'){
            var_dump('接口显示错误');exit;
        }
        foreach($kline_realTime['data'] as $v){
            if($v['id'] < $ti){
                if($type == 0){
                   $transform = $this->convert_kline_data($v);
                }else{
                   $transform = $v;
                }
                $result[] = $v;
            }
        }
        return $result;
    }
    
    // kline价格的转换
    public function convert_kline_data($prst)
    {
        $rst = array();
        $rst['open'] = 1.0 / $prst['open'];
        $rst['close'] = 1.0 / $prst['close'];
        $rst['high'] = 1.0 / $prst['high'];
        $rst['low'] = 1.0 / $prst['low'];
        $rst['volume'] = $prst['count'];
        $rst['count'] = $prst['vol'];
        return $rst;
    }

    /*
     * 币对的拼接
     */
    public function bt_line($asset)
    {
        $where['symbol'] = $this->base . $asset;
        $arr = M('cb_channel')->field('chid,symbol')
            ->where($where)
            ->find();
        if (empty($arr)) {
            $where2['symbol'] = $asset . $this->base;
            $arr = M('cb_channel')->field('chid,symbol')
                ->where($where2)
                ->find();
        }
        return $arr;
    }
    
    /*
     * 获取t1时间之前$long(以秒为单位，600) 时间倒序
     * 返回数组
     */
    public function get_hist_order_by_time($ti, $asset, $long = 600)
    {
        // 拼接存在的币对
        $symbol = $this->bt_lines($this->assetName[0], $this->assetName[$asset]);
       // $symbol = $this->bt_line($asset)['symbol'];
        $startTime = $ti - 600 * 1000;
        $endTime = $ti;
        if($this->is_live){  //为真调用接口
           $result_arr = $this->crontab_hist($startTime,$endTime,$symbol['symbol'],$symbol[3]);
        }else{
            $where['symbol'] = $symbol['symbol'];
            $where['ts'] = [
                'BETWEEN',
                [
                    $startTime,
                    $endTime
                ]
            ];
            $result = M('cd_history')->where($where)->select();
            foreach ($result as $v) {
                if($symbol[3] == 0){
                    $transform = $this->convert_history_record($v);
                }else{
                    $transform = $v;
                }
                $result_arr[] = $transform;
            }
        }
        return $result_arr;
    }

    /**
     *  实时调用接口
     */
    public function crontab_hist($startTime,$endTime,$symbol,$type){
        $req = new req();
        $getHistOrder = $req->get_history_trade($symbol,100);
        if($getHistOrder['status'] == 'ok'){
            foreach ($getHistOrder['data'] as $v) {
                foreach ($v['data'] as $val) {
                    $arr['amount'] = $val['amount'];
                    $arr['ts'] = $val['ts'];
                    $arr['id'] = $v['id'];    
                    $arr['price'] = $val['price'];
                    $arr['symbol'] = $symbol;
                    $arr['direction'] = $val['direction'];
                    $arr_val[] = $arr;
                }
            }
            foreach($arr_val as $k=>$v){
                if($v['ts'] > $startTime && $v['ts'] <= $endTime){
                    if($type == 0){
                        $transformd = $this->convert_history_record($v);
                    }else{
                        $transformd = $v;
                    }
                    $result[] = $transformd;
                }
            }
            return $result;
        }
    }

    /*
     * 获取ti时间之前的$amount条order
     */
    public function get_history_order_by_amount($ti, $asset, $amount = 100)
    {
        $symbol = $this->bt_line($asset)['symbol'];
        $where['ts'] = [
            'lt',
            $ti
        ];
        $result = M('cd_history')->where($where)
            ->order('ts DESC')
            ->limit($amount)
            ->select();
        foreach ($result as $v) {
            $result_arr[] = $this->convert_history_record($v);
        }
        return $result_arr;
    }

    /*
     * 历史记录的price转换
     */
    private function convert_history_record($prst)
    {
        $prst['price'] = 1.0 / $prst['price'];
        // $rst = array();
        // $rst['price'] = 1.0/$prst['price'];
        // $rst['qty'] = $prst['qty']*$rst['price'];
        // $rst['ts'] = $prst['ts'];
        // if($prst['type']=='bids') {
        // $rst['type'] = 'asks';
        // } elseif ($prst['type']=='asks') {
        // $rst['type'] = 'bids';
        // } else {
        // return null;
        // }
        return $prst;
    }

    /*
     *  单独调用的重写
     *  获取t1时间的历史depth  根据价格排序  bid从大到小  ask从小到大   
     */
    public function get_depth_data2($symbol,$ti='',$asset='1',$depth='10'){
        $table_name = 'cb_depth_'.$symbol;
        // if($this->is_live){
        //     echo 1;die;
            return $this->now_data($symbol,$depth);
        //}
        $where['ts'] = $ti;
        var_dump($where);die;
        $result = M($table_name)->where($where)->select();
        var_dump($result);exit;
        foreach($result as $v){
            if($v['type'] == 'bids'){
                $result_bids[] = $v;
            }else{
                $result_asks[] = $v;
            }
        }
        array_multisort(array_map(create_function('$n','return $n["price"];'),$result_bids),SORT_DESC,$result_bids);
        array_multisort(array_map(create_function('$n','return $n["price"];'),$result_asks),SORT_DESC,$result_asks);
        $arr['bids'] = $result_bids;
        $arr['asks'] = $result_asks;
        return $arr;
    }

    /*
     * 获取ti时间的历史depth 根据价格排序 bid从大到下 ask 从小到大
     */
    public function get_depth_data($ti = '', $asset = '1', $depth = '10')
    {
            $symbol = $this->bt_lines($this->assetName[0], $this->assetName[$asset]);
            $table_name = 'cb_depth_' . $symbol['symbol'];
            if ($this->is_live) {
                return $this->now_data($symbol['symbol'], $depth);
            }
            $where['ts'] = $ti;
            $result = M($table_name)->where($where)->select();
            foreach ($result as $v) {
                if ($v['type'] == 'bids') {
                    $result_bids[] = $v;
                } else {
                    $result_asks[] = $v;
                }
            }
            array_multisort(array_map(create_function('$n', 'return $n["price"];'), $result_bids), SORT_DESC, $result_bids);
            array_multisort(array_map(create_function('$n', 'return $n["price"];'), $result_asks), SORT_DESC, $result_asks);
            $arr['bids'] = $result_bids;
            $arr['asks'] = $result_asks;
        return $arr;
    }

    /*
     * 实时获取数据接口
     */
    public function now_data($symbol, $depth)
    {
        // 实时获取数据
        $req = new req();
        $val_data = $req->get_market_depth($symbol, 'step0');
        // 分别中bids 和 asks中 获取$depth个
        $arr_val['bids'] = array_slice($val_data['tick']['bids'], 0, $depth);
        $arr_val['asks'] = array_slice($val_data['tick']['asks'], 0, $depth);
        
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['bids']), SORT_DESC, $arr_val['bids']);
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['asks']), SORT_ASC, $arr_val['asks']);
        return $arr_val;
    }
    
    // 模拟交易
    // public function sim($startTime,$endTime,$jumpTime){
    // for($i=$startTime;$i<$endTime;){
    // //将秒转换成毫秒
    // $sicTime = strtotime($i) * 1000;
    // //调用getpos函数
    // $this->objPos = $this->holdPos;
    // $this->getPos($sicTime);
    // //修改当前持有量
    
    // $i += $jumpTime;
    // }
    // }

    /**
     *  获取kline数据   2
     */
    // public function getKline2($t1){
    //     for($kl=1;$kl<count($this->assetName);$kl++){
    //         $symbol = $this->bt_lines($this->assetName[0], $this->assetName[$kl]);
    //         $req = new req();
    //         $kline_realTime = $req->get_history_kline($symbol['symbol'], $this->rtime,200);
    //         if($kline_realTime['status'] != 'ok'){
    //             var_dump('获取kline接口数据失败');exit;
    //         }
    //         foreach($kline_realTime['data'] as $k=>$v){
    //             if($v['id'] <= $t1){
    //                 $arr[] = $v;
    //             }
    //         }
    //         $slice_arr = array_slice($arr,$this->back_segs - 1);
    //         array_multisort(array_map(create_function('$n', 'return $n["kid"];'), $slice_arr), SORT_ASC, $slice_arr);
    //         $arr_val = array_map(create_function('$n', 'return $n["kid"];'), $slice_arr);
    //         foreach ($arr_val as $k => $v) {
    //             $this->hist_kline_ts[] = $v;
    //         }
    //         foreach($this->hist_kline_ts as $key=>$val){
    //             for($i=0;$i<count($arr);$i++){
    //                 if($arr[$i]['id'] == $val){
    //                     $this->kline_datas[$val][$kl] = $arr[$i];
    //                 }
    //             }
    //         }
    //     }
       
    //     if(count($this->hist_kline_ts) > 1){
    //         $start_time = $this->hist_kline_ts[count($this->hist_kline_ts) - 1];
    //         foreach($kline_realTime['data'] as $keys=>$vals){
    //             if($vals['id'] > $start_time && $vals['id'] <= $t1){
    //                 $arr_kline_id[] = $vals['id'];
    //                 $arr_kline_datas[] = $vals;
    //             }
    //         }
    //         $sort_arr = sort($arr_kline_id);
    //         for($q=1;$q<count($this->assetName);$q++){
    //             foreach($sort_arr as $keys=>$vals){
    //                 for($w=0;$w<count($arr_kline_datas);$w++){
    //                     if($vals == $arr_kline_datas[$w]['id']){
    //                         $this->kline_datas[$vals][$q] = $w;
    //                     }
    //                 }
    //             }
    //         }
    //         array_push($this->hist_kline_ts,$sort_arr);
    //         exit;
    //     }
    // }
    
    /*
     *
     */
    public function prepareData($time)
    { 
        $time = $time / 1000;
        $type_time_min = array_keys(C('timeType'), $this->rtime)[0];
        if (count($this->hist_kline_ts) > 1) {

            $this->live_kline($time,$type_time_min);

            $ch_time_num = ($time - $this->hist_kline_ts[count($this->hist_kline_ts) - 1]) / $type_time_min;

            for ($i = 1; $i < $ch_time_num; $i ++) {
                $ch_num = $i * $type_time_min + $this->hist_kline_ts[count($this->hist_kline_ts) - 1];
                array_push($this->hist_kline_ts, $ch_num);
                for ($v = 1; $v < count($this->assetName); $v ++) {
                    $chid = $this->bt_lines($this->assetName[0], $this->assetName[$v]);
                    if($chid[3] == 0){
                        $kline_datas_val = $this->convert_kline_data(M('cb_kline')->where("kid = {$ch_num} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")->order('kid DESC')->select()[0]);
                    }else{
                        $kline_datas_val = M('cb_kline')->where("kid = {$ch_num} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")->order('kid DESC')->select()[0];
                    }
                    $this->kline_datas[count($this->hist_kline_ts) - 1][$v] = $kline_datas_val;
                }
            }
            return;
        }
        $where['kid'] = [
            'lt',
            $time
        ];
        $where['r_time'] = $this->rtime;
        $result = M('cb_kline')->where($where)
            ->field('kid')
            ->order('kid DESC')
            ->Distinct(true)
            ->limit($this->back_segs)
            ->select();
        
        array_multisort(array_map(create_function('$n', 'return $n["kid"];'), $result), SORT_ASC, $result);
        $arr_val = array_map(create_function('$n', 'return $n["kid"];'), $result);
        foreach ($arr_val as $k => $v) {
            $this->hist_kline_ts[] = $v;
        }
        for ($i = 1; $i < count($this->assetName); $i ++) {
            $chid = $this->bt_lines($this->assetName[0], $this->assetName[$i]);
            
            foreach ($this->hist_kline_ts as $key => $val) {
                
                if($chid[3] == 0){
                    $val_kline_datas = $this->convert_kline_data(M('cb_kline')->where("kid = {$val} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")
                    ->order('kid DESC')
                    ->find());
                }else{
                    $val_kline_datas = M('cb_kline')->where("kid = {$val} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")
                    ->order('kid DESC')
                    ->find();
                }
                $this->kline_datas[$key][$i] = $val_kline_datas;
            }
        }
        $this->live_kline($time,$type_time_min);
    }

    /**
     *
     */
    public function live_kline($t1,$type_time_min){
        if($this->is_live == true && $t1 > $this->hist_kline_ts[count($this->hist_kline_ts) - 1]) {
            $req = new req();
            $ch_last_time = floor(($t1 - $this->hist_kline_ts[count($this->hist_kline_ts) - 1]) / $type_time_min);
            $overwrite = false;
            if($ch_last_time==0) {
                $ch_last_time = 1;
                $overwrite = true;
            }
            for ($tm=1; $tm<=$ch_last_time; $tm++) {
                if($overwrite) {
                    $now_time = $this->hist_kline_ts[count($this->hist_kline_ts) - 1];
                } else {
                    $now_time = $this->hist_kline_ts[count($this->hist_kline_ts) - 1] + 900 * $tm;
                    $this->hist_kline_ts[] = $now_time;
                }
                for($kl=1;$kl<count($this->assetName);$kl++){
                    $symbol_val = $this->bt_lines($this->assetName[0],$this->assetName[$kl]);
                    $kline_now_val = $req->get_history_kline($symbol_val['symbol'], $this->rtime,$ch_last_time+1);
                    if($kline_now_val['status'] != 'ok'){
                        var_dump('接口数据有误kline');exit;
                    }
                    foreach($kline_now_val['data'] as $k=>$v){
                        if($v['id'] == $now_time){
                            if($symbol_val[3] == 0){
                                $flip = $this->convert_kline_data($v);
                            }else{
                                $flip = $v;
                            }
                            $this->kline_datas[count($this->hist_kline_ts) - 1][$kl] = $flip;
                        }
                    }
                }
            }
            return;
        }
    }
    
    // 币对拼接 多条指定
    public function bt_lines($v, $val)
    { // 反向转换
        //$where['symbol'] = $v . $val;
        $sym = strtolower($val.$v);
        if(isset($this->system['sympair'][$sym])) {
            $arr['chid'] = $this->system['sympair'][$sym];
            $arr['symbol'] = $sym;
            $arr[3] = 1;
            return $arr;
        }
        $sym = strtolower($v.$val);
        if(isset($this->system['sympair'][$sym])) {
            $arr['chid'] = $this->system['sympair'][$sym];
            $arr['symbol'] = $sym;
            $arr[3] = 0;
            return $arr;
        }
        return null;
    }

    // 币对拼接 多条指定
    public function bt_lines_bak($v, $val)
    { // 反向转换
    //$where['symbol'] = $v . $val;
    $where['symbol'] = strtolower($val.$v);
    $arr = M('cb_channel')->field('chid,symbol')
    ->where($where)
    ->find();
    
    $stat = 1;
    if (empty($arr)) {
        //$where2['symbol'] = $val . $v;
        $where2['symbol'] = strtolower($v.$val);
        $arr = M('cb_channel')->field('chid,symbol')
        ->where($where2)
        ->find();
        $stat = 0;
    }
    $arr[3] = $stat;
    return $arr;
    }
    
    
    
    public function updateAssetMoney()
    {
        $this->holdPos[0] = $this->holdAsset[0];
        $curasset = $this->holdPos[0];
        $klineLast = count($this->kline_datas) - 1;
        for ($i = 1; $i <= $this->assetLen; $i ++) {
            $this->holdPos[$i] = $this->holdAsset[$i] * $this->kline_datas[$klineLast][$i]['close'];
            $curasset += $this->holdPos[$i];
        }
        $this->assetMoney = $curasset;
    }
    
    public function adjustObjPos()
    {
//        var_dump($this->objPos);
//        var_dump($this->holdPos);
        $obj_sum = array_sum($this->objPos);
        $hold_sum = array_sum($this->holdPos);
        foreach ($this->objPos as $k => $v) {
            $this->objPos[$k] = $this->objPos[$k]*$hold_sum/$obj_sum;
        }
//        var_dump($this->objPos);
    }
    
    public function updateObjAsset() {
        $klineLast = count($this->kline_datas) - 1;
        
        $this->objAsset[0] = $this->objPos[0];
        for ($i=1; $i<=$this->assetLen; $i++) {
            $this->objAsset[$i] = $this->objPos[$i]/$this->kline_datas[$klineLast][$i]['close'];
        }
    }
    
        
    
    //模拟止损价
    public function stopLoss($time){
        $toStopLoss = [];
        for($i=1;$i<count($this->assetName);$i++){
            $toStopLoss[$i] = false;
            if($this->order_money[$i]==0) continue;
            //计算后的价格
            $startMoney = $this->kline_datas[count($this->kline_datas) - 1][$i]['close'];
            
            //增涨或下降的比例
            if($startMoney > $this->upMoney[$i]){
                $this->upMoney[$i] = $startMoney;   
                
                //更新了upmoney才需要更新止损价格
                $proportion[$i] = ($this->upMoney[$i] - $this->order_money[$i]) / $this->order_money[$i];
                if ($proportion[$i] < 0.05 ){
                    $this->stopLessMoney[$i] = $this->upMoney[$i] * 0.97;
                } elseif ($proportion[$i] < 0.15){
                    //止损价格
                    $this->stopLessMoney[$i] = $this->upMoney[$i] * 0.95;
                } elseif ($proportion[$i] < 0.025){
                    $this->stopLessMoney[$i] = $this->upMoney[$i] * 0.925;
                } else{
                    $this->stopLessMoney[$i] = $this->upMoney[$i][$i] * 0.9;
                }
            } elseif($startMoney < $this->stopLessMoney[$i]){
                //var_dump('下跌超出止损价');exit;
                //如果当前价格更高，肯定不会出发止损
                 $toStopLoss[$i] = true;
            }
        }
        return $toStopLoss;
    }
    
    public function normalChangeUnit(&$changeUnit, $symbol) {
        foreach ($changeUnit as $k=>$v) {
            $pnm = $this->amount_precision[$symbol[$k]];
            $pos = strpos($v, '.');
            if ($pos>=0) {
                $changeUnit[$k] = substr($v, 0, $pos+$pnm);
            }
            //         $amount = substr($amount, 0, $pos+5);
        }
    }
    
    /*
     * 实际交易，从$holdPos调整到$objPos
     * 
     * 返回交易之后的持仓
     */
    public function tradeLive($strName, $ti)
    {
        $changeUnit = [];
        $symbol = [];
        $reverse = [];
        $estprice = [];
        $forceOrder = [];
        $lastprice = $this->kline_datas[count($this->kline_datas) - 1];
        $needBase = [];
        $needAsset = [];
        
        for ($i=1; $i<=$this->assetLen; $i++) {
            $needBase[$i] = 0;
            $needAsset[$i] = 0;
            $chid = $this->bt_lines($this->assetName[0], $this->assetName[$i]);
    
            if($chid[3]==0) {
                //reverse
                $symbol[$i] = $chid['symbol'];
                $cpos = $this->objPos[$i]-$this->holdPos[$i];
                if($cpos<0) {
                    $needAsset[$i] = $cpos/$lastprice[$i]['close'];
                } else {
                    $needBase[$i] = $cpos;
                }
                $reverse[$i] = true;
                $estprice[$i] = 1.0/$lastprice[$i]['close'];
                $forceOrder[$i] = false;
            } else {
                $symbol[$i] = $chid['symbol'];
                $cpos = $this->objPos[$i] - $this->holdPos[$i];
                if($cpos<0) {
                    $needAsset[$i] = $cpos/$lastprice[$i]['close'];
                } else {
                    $needBase[$i] = $cpos;
                }
                $reverse[$i] = false;
                $estprice[$i] = $lastprice[$i]['close'];
                $forceOrder[$i] = false;
            }
            $baseSum = array_sum($needBase);
            if($baseSum>$this->holdPos[0]) {
                for ($i=1; $i<=$this->assetLen; $i++) {
                    $needBase[$i] = $needBase[$i]*$this->holdPos[0]/$baseSum;
                }
                $holdPos[0] = 0;
            } elseif($baseSum==0) {
                for ($i=0; $i<=$this->assetLen; $i++) {
                    $needBase[$i] = 0;
                }    
            } else {
                $this->holdPos[0] -= $baseSum;
            }
            for ($i=1; $i<=$this->assetLen; $i++) {
                if($needAsset[$i]>$this->holdAsset[$i]) {
                    $needAsset[$i] = $this->holdAsset[$i];
                }
            }
            
        }
//        normalChangeUnit($changeUnit, $symbol);
        var_dump($needBase);
        var_dump($needAsset);
        $this->trade($strName, $ti, $needBase, $needAsset, $symbol, $reverse, $estprice, $forceOrder);
    }
    
    public function trade($strName, $ti, $changeUnit, $symbol, $reverse) {
        //implement this method in placeorder class
    }
    
    
    
    public function updateObjPosFromAsset() {
        $klineLast = count($this->kline_datas) - 1;
        $this->objPos[0] = $this->objAsset[0];
        for ($i=1; $i<=$this->assetLen; $i++) {
            $this->objPos[$i] = $this->objAsset[$i]*$this->kline_datas[$klineLast][$i]['close'];
//            var_dump($this->kline_datas[$klineLast][$i]['close']);
        }
    }
    
    public function updateHoldPosFromAsset() {
        $klineLast = count($this->kline_datas) - 1;
    
        $this->holdPos[0] = $this->holdAsset[0];
        for ($i=1; $i<=$this->assetLen; $i++) {
            $this->holdPos[$i] = $this->holdAsset[$i]*$this->kline_datas[$klineLast][$i]['close'];
        }
    }
    
    /*
     * 止损调仓
     * 各个策略单独执行调仓，最终portfolio汇总只交易，不调仓
     */
    public function stopLossSim($straName, $ti)
    {
        //是否要质损，如果只损，会调整objPos
        printf("%s tradesim\n",$straName);
        for($k=1;$k<count($this->assetName);$k++){
            $changePos = $this->objPos[$k]-$this->holdPos[$k]; 
            if($changePos==0) {
                continue;
            } elseif($changePos>0) {
                $this->order_money[$k] = ($this->kline_datas[count($this->kline_datas) - 1][$k]['close']*$changePos+$this->order_money[$k]*$this->holdPos[$k])/$this->objPos[$k];
                if($this->upMoney[$k]<$this->order_money[$k]) {
                    $this->upMoney[$k] = $this->order_money[$k];
                    $this->stopLessMoney[$k] = $this->order_money[$k]*0.97;
                }                
            } else {
            }
        }
//        printf("pre pos\n");
//        var_dump($this->objPos);
        $to_stop_loss = $this->stopLoss($ti);
        for ($k=1; $k<=$this->assetLen; $k++) {
            if($to_stop_loss[$k]) {
                printf("%s stop loss for %s in %s\n",$straName, $this->assetName[$k],gmdate('Ymd H:i:s', $ti / 1000+$this->system['timeZone']*3600));
                $this->objPos[0] += $this->objPos[$k];
                $this->objPos[$k] = 0;
                $this->upMoney[$k] = 0;
                $this->order_money[$k] = 0;
                $this->stopLessMoney[$k] = 0;
            }
            printf("adjust pos for %s\n",$this->assetName[$k]);
        }
//        printf("new pos\n");
//        var_dump($this->objPos);
        //根据目标pos调整Asset，模拟的时候直接根据close计算
        $this->updateObjAsset();
    }
    

    /*
     * 获取指定的数据
     * @$val 毫秒的时间戳
     * @$type 类型 kline depth history
     /**
     * 下单操作
     */
    public function orderBuy($account_id,$amount,$price='',$symbol,$type){
        if($this->is_live){  //为true 接口下单
            $crontab_orderBuy = $this->crontab->getPlace($account_id,$amount,$price,$symbol,$type);
            return $crontab_orderBuy;
        }else{
            var_dump('模拟操作');exit;
        }
    }
    
}
