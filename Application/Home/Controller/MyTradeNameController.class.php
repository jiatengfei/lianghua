<?php
namespace Home\Controller;

error_reporting(11);

class MyTradeNameController extends TradController
{

    protected $min_trade_volume;

    private $dependence;

    private $short;

    private $long;

    private $M;

    private $lastPeak;

    public $assetMoney;
    
    // public function __construct($base='HT', $money=10000, $is_live=false, $r_time='15min') {
    public function __construct($system, $params)
    {
        parent::__construct($system, $params);
        // parent::__construct($system['base'],$system['money'],$system['is_live'],$params['r_time']);
        $this->dependence = [];
        $imd = new ImMacdDataController($system, $params);
        $this->dependence['macd'] = $imd;
        $this->assetMoney = $this->money;
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
        for ($ilen = count($this->dependence->m_data); $ilen >= 0; $ilen -= 1) {
            if ($this->dependence->m_data[$ilen][$assetid]['macd'] > 0) {
                break;
            }
            // if($this->dependence->m_data[$ilen][$assetid]['ema12']>0 || $this->dependence->m_data[$ilen][$assetid]['ema26']>0) {
            // $toFall = true;
            // }
            if ($this->dependence->m_data[$ilen][$assetid]['macd'] < $worst_macd) {
                $worst_macd = $this->dependence->m_data[$ilen][$assetid]['macd'];
                $worst_macd_ratio = $worst_macd * 2 / ($this->dependence->m_data[$ilen][$assetid]['ema12'] + $this->dependence->m_data[$ilen][$assetid]['ema26']);
            }
            $minus_cnt += 1;
            $macd_seq[] = $this->dependence->m_data[$ilen][$assetid]['macd'];
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
        for ($ilen = count($this->dependence->m_data); $ilen >= 0; $ilen -= 1) {
            if ($this->dependence->m_data[$ilen][$assetid]['macd'] < 0) {
                break;
            }
            // if($this->dependence->m_data[$ilen][$assetid]['ema12']>0 || $this->dependence->m_data[$ilen][$assetid]['ema26']>0) {
            // $toFall = true;
            // }
            // if($this->dependence->m_data[$ilen][$assetid]['macd']<$worst_macd) {
            // $worst_macd = $this->dependence->m_data[$ilen][$assetid]['macd'];
            // $worst_macd_ratio = $worst_macd*2/($this->dependence->m_data[$ilen][$assetid]['ema12']+$this->dependence->m_data[$ilen][$assetid]['ema26']);
            // $worst_macd_ratio = $worst_macd/($this->dependence->m_data[$ilen][$assetid]['ema26']);
            // }
            $minus_cnt += 1;
            $macd_seq[] = $this->dependence->m_data[$ilen][$assetid]['macd'];
            if ($minus_cnt >= 15) {
                break;
            }
        }
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

    public function updateAssetMoney()
    {
        $curasset = $this->holdPos[0];
        $klineLast = count($this->kline_datas) - 1;
        for ($i = 1; $i <= $this->assetLen; $i ++) {
            $curasset += $this->holdPos[$i] * $this->kline_datas[klineLast][$i]['close'];
        }
        $this->assetMoney = $curasset;
    }

    /*
     * 生成当前的目标position
     */
    public function getPos($ti)
    {
        $tobuy = [];
        $toSell = [];
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
