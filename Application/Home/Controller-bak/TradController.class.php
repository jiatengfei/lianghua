<?php
namespace Home\Controller;

use Think\Controller;

class TradController extends HomeController
{

    public $holdPos = [];
 // 当前持有量
    protected $money;
 // 当前持有的资金
    public $objPos;
 // getpos函数返回的值
                    // protected $short; //做空
    protected $config = [];

    protected $assetLen;
 // 可交易资产的数量
    protected $base;

    protected $assetName = [];

    protected $declist;

    protected $is_live;
 // 状态
    protected $rtime;

    protected $back_segs = 50;

    protected $hist_kline_ts = [];
 // 数组存放到模拟时间为止的所有kline的时间
    protected $kline_datas = [];

    protected $m;

    public function __construct($system, $params)
    {
        parent::__construct();
        $this->base = $system['base'] ? $system['base'] : 'HT';
        $this->declist = C($this->base);
        $this->is_live = $system['is_live'];
        $this->money = $system['money'];
        $this->rtime = $params['r_time'];
        $this->assetLen = count($this->declist);
        $this->assetName[0] = $this->base;
        foreach ($this->declist as $k => $v) {
            $this->assetName[$k + 1] = $v;
        }
        // 持有量
        $this->holdPos[0] = $this->money;
        for ($i = 0; $i < $this->assetLen; $i ++) {
            $this->holdPos[$i + 1] = 0;
        }
        // var_dump($this->holdPos);exit;
    }
    
    // 获取kline数据
    /*
     * 获取kline数据 获取ti时间时最新的$num条kline，$rtime 为 1min,5min..
     * 返回数组 key为open close hight low volume count
     */
    public function get_kline_data($ti = '1521986400', $asset = 'usdt', $num = 10, $rtime = '15min')
    {
        // 拼接币对 $this->base.$asset
        // 获取相应币对的chid
        $chid = $this->bt_line($asset)['chid'];
        $where_k['kid'] = [
            'lt',
            $ti
        ];
        $where_k['r_time'] = $rtime;
        $where_k['chid'] = $chid;
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
            $kline_price[] = $this->convert_kline_data($v);
        }
        return $kline_price;
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
        $symbol = $this->bt_line($asset)['symbol'];
        $startTime = $ti - 600 * 1000;
        $endTime = $ti;
        $where['symbol'] = $symbol;
        $where['ts'] = [
            'BETWEEN',
            [
                $startTime,
                $endTime
            ]
        ];
        $result = M('cd_history')->where($where)->select();
        foreach ($result as $v) {
            $result_arr[] = $this->convert_history_record($v);
        }
        return $result_arr;
    }

    /*
     * 获取ti时间之前的$amount条order
     */
    public function get_history_order_by_amount($ti, $aeest, $amount = 100)
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
     * 获取ti时间的历史depth 根据价格排序 bid从大到下 ask 从小到大
     */
    public function get_depth_data($ti = '', $asset = 'usdt', $depth = '10', $type = '')
    {
        $symbol = $this->bt_line($asset)['symbol'];
        $table_name = 'cb_depth_' . $symbol;
        if ($this->is_live) {
            return $this->now_data($symbol, $depth);
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
        array_multisort(array_map(create_function('$n', 'return $n[0];'), $arr_val['asks']), SORT_DESC, $arr_val['asks']);
        var_dump($arr_val);
        exit();
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
    
    /*
     *
     */
    public function prepareData($time)
    {
        var_dump($time);
        $time = $time / 1000;
        $type_time_min = array_keys(C('timeType'), $this->rtime);
        if (! isset($this->hist_kline_ts)) {
            $ch_time_num = ($time - $this->hist_kline_ts[0]) / $type_time_min;
            for ($i = 1; $i < $ch_time_num; $i ++) {
                $ch_num = $i * $type_time_min + $this->hist_kline_ts[0];
                array_push($this->hist_kline_ts, $ch_num);
                for ($v = 1; $v < count($this->assetName); $v ++) {
                    $chid = $this->bt_lines($this->assetName[0], $this->assetName[$v]);
                    $this->kline_datas[count($this->hist_kline_ts) - 1][$v] = $this->convert_kline_data(M('cb_kline')->where("kid = {$ch_num} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")
                        ->order('kid DESC')
                        ->select()[0]);
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
        // $this->hist_kline_ts[0] = $time;
        foreach ($arr_val as $k => $v) {
            $this->hist_kline_ts[] = $v;
        }
        for ($i = 1; $i < count($this->assetName); $i ++) {
            $chid = $this->bt_lines($this->assetName[0], $this->assetName[$i]);
            
            foreach ($this->hist_kline_ts as $key => $val) {
                
                // $this->kline_datas[$key][$i][] = M('cb_kline')->where("kid = {$val} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")->order('kid ASC')->select();
                $this->kline_datas[$key][$i] = $this->convert_kline_data(M('cb_kline')->where("kid = {$val} AND r_time = '{$this->rtime}' AND chid = {$chid['chid']}")
                    ->order('kid DESC')
                    ->find());
            }
        }
    }
    
    // 币对拼接 多条指定
    public function bt_lines($v, $val)
    {
        $where['symbol'] = $v . $val;
        $arr = M('cb_channel')->field('chid,symbol')
            ->where($where)
            ->find();
        if (empty($arr)) {
            $where2['symbol'] = $val . $v;
            $arr = M('cb_channel')->field('chid,symbol')
                ->where($where2)
                ->find();
        }
        return $arr;
    }

    /*
     * 获取指定的数据
     * @$val 毫秒的时间戳
     * @$type 类型 kline depth history
     */
    public function db($val, $type)
    {
        $where['ts'] = $val;
        switch ($type) {
            case 'history':
                $result = M('cb_history')->where($where)->select();
                break;
            case 'depth':
                $result = M('cd_depth')->where($where)->select();
                break;
            case 'kline':
                break;
        }
        return $result;
    }

    /*
     * 生成日志
     */
    public function log($val, $data = [])
    {}
}
