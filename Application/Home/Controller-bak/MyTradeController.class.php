<?php
namespace Home\Controller;

error_reporting(11);

class MyTradeController extends TradController
{

    public $is_live;

    protected $min_trade_volume;

    private $dependence;

    private $short;

    private $long;

    private $M;

    private $lastPeak;
    
    // public function __construct($base='HT', $money=10000, $is_live=false, $r_time='15min') {
    public function __construct($system, $params)
    {
        parent::__construct($system, $params);
        // parent::__construct($system['base'],$system['money'],$system['is_live'],$params['r_time']);
        $this->dependence = [];
        // $str = C('config').'ImController';
        // $obj = new $str($system,$params);
        // var_dump($str);exit;
        $imd = new \Home\Controller\ImMacdDataController($system, $params);
        $this->dependence['macd'] = $imd;
    }

    public function prepareDepData($ti)
    {
        foreach ($this->dependence as $td) {
            $td->prepareData($ti);
            $td->calculate();
        }
    }

    public function generateToBuy($ti, $assetid)
    {
        $tobuy = false;
        $minus_cnt = 0;
        $macd_seq = [];
        $worst_macd = 0;
        $worst_macd_ratio = 0;
        $toFall = false;
        $premacd = 0;
        for ($ilen = count($this->dependence['macd']->m_data) - 1; $ilen >= 0; $ilen -= 1) {
            if ($this->dependence['macd']->m_data[$ilen][$assetid]['macd'] > 0) {
                break;
            }
            // if($this->dependence['macd']->m_data[$ilen][$assetid]['ema12']>0 || $this->dependence['macd']->m_data[$ilen][$assetid]['ema26']>0) {
            // $toFall = true;
            // }
            if ($this->dependence['macd']->m_data[$ilen][$assetid]['macd'] < $worst_macd) {
                $worst_macd = $this->dependence['macd']->m_data[$ilen][$assetid]['macd'];
                $worst_macd_ratio = $worst_macd * 2 / ($this->dependence['macd']->m_data[$ilen][$assetid]['ema12'] + $this->dependence['macd']->m_data[$ilen][$assetid]['ema26']);
            }
            $minus_cnt += 1;
            $macd_seq[] = $this->dependence['macd']->m_data[$ilen][$assetid]['macd'];
            if ($minus_cnt >= 15) {
                return false;
            }
        }
        if ($toFall)
            return false;
        if ($minus_cnt >= 15)
            return false;
        if ($minus_cnt <= 6)
            return false;
        if ($worst_macd_ratio < 0.5)
            return false;
        if ($macd_seq[0] < $macd_seq[1]) {
            return false;
        }
        return true;
    }

    public function generateToSell($ti, $assetid)
    {
        $minus_cnt = 0;
        $macd_seq = [];
        $worst_macd = 0;
        $worst_macd_ratio = 0;
        $toFall = false;
        $premacd = 0;
        for ($ilen = count($this->dependence['macd']->m_data) - 1; $ilen >= 0; $ilen -= 1) {
            if ($this->dependence['macd']->m_data[$ilen][$assetid]['macd'] < 0) {
                var_dump('break in ' . $ilen);
                break;
            }
            // if($this->dependence['macd']->m_data[$ilen][$assetid]['ema12']>0 || $this->dependence['macd']->m_data[$ilen][$assetid]['ema26']>0) {
            // $toFall = true;
            // }
            // if($this->dependence->m_data[$ilen][$assetid]['macd']<$worst_macd) {
            // $worst_macd = $this->dependence->m_data[$ilen][$assetid]['macd'];
            // $worst_macd_ratio = $worst_macd*2/($this->dependence->m_data[$ilen][$assetid]['ema12']+$this->dependence->m_data[$ilen][$assetid]['ema26']);
            // $worst_macd_ratio = $worst_macd/($this->dependence->m_data[$ilen][$assetid]['ema26']);
            // }
            $minus_cnt += 1;
            $macd_seq[] = $this->dependence['macd']->m_data[$ilen][$assetid]['macd'];
            if ($minus_cnt >= 15) {
                break;
            }
        }
        var_dump($macd_seq);
        // if($toFall) continue;
        // if($minus_cnt>=15) continue;
        if ($minus_cnt <= 6)
            return false;
        if ($worst_macd_ratio < 0.5)
            return false;
        if ($macd_seq[0] > $macd_seq[1]) {
            return false;
        }
        return true;
    }

    /*
     * 生成当前的目标position
     */
    public function getPos($ti)
    {
        $tobuy = [];
        for ($i = 1; $i <= $this->assetLen; $i ++) {
            $tobuy[$i] = $this->generateToBuy($ti, $i);
            if ($tobuy[$i]) {
                $this->objPos[$i] = 1;
            }
            $toSell[$i] = $this->generateToSell($ti, $i);
            if ($this->objPos[$i] > 0 && $toSell[$i]) {
                $this->objPos[$i] = 0;
            }
        }
    }

    /*
     * 增加一个变量 $is_live表示当前是模拟还是live
     * false表示模拟 true表示live
     */
    public function trade()
    {
        if ($this->is_live) {
            // live
        } else {
            // sim
            $price = $this->kline_datas[0]['close'];
        }
    }
}
