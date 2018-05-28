<?php
namespace Home\Controller;

error_reporting(11);

class MyTradeController extends TradController
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
        //var_dump($macd);
        //var_dump($this->dependence['macd']->largestMACD);
        for ($ilen = count($this->dependence['macd']->m_data)-1; $ilen >= 0; $ilen -= 1) {
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
                break;
            }
        }
//        var_dump($macd_seq);
//        var_dump(array_slice($this->kline_datas,-1));
        // if($toFall) return false;
        //while(count($macd_seq)>0 && abs($macd_seq[count($macd_seq)-1])<0.05*$this->dependence['macd']->largestMACD[$assetid]) {
        $cmacd = count($macd_seq);
        while($cmacd>0 && abs($macd_seq[$cmacd-1])<0.05*$this->dependence['macd']->largestMACD[$assetid]) {
            array_pop($macd_seq);
            $cmacd -= 1;
        }
        var_dump($macd_seq);
//        if($minus_cnt>=10) return false;
        if($cmacd<4) return false;
        if(abs(array_sum(array_slice($macd_seq,-3))/3)<0.08*$this->dependence['macd']->largestMACD[$assetid]) {
            print("buy: too small macd abs value ".array_sum($macd_seq)/count($macd_seq).' vs '.$this->dependence['macd']->largestMACD[$assetid]."\n");
            return false;
        }
        //if($worst_macd_ratio<0.5) return false;
        if ($macd_seq[0] < $macd_seq[1]) {
           // return false;
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
        for ($ilen = count($this->dependence['macd']->m_data)-1; $ilen >= 0; $ilen -= 1) {
            if ($this->dependence['macd']->m_data[$ilen][$assetid]['macd'] < 0) {
                break;
            }
            // if($this->dependence['macd']->m_data[$ilen][$assetid]['ema12']>0 || $this->dependence['macd']->m_data[$ilen][$assetid]['ema26']>0) {
            // $toFall = true;
            // }
            // if($this->dependence['macd']->m_data[$ilen][$assetid]['macd']<$worst_macd) {
            // $worst_macd = $this->dependence['macd']->m_data[$ilen][$assetid]['macd'];
            // $worst_macd_ratio = $worst_macd*2/($this->dependence['macd']->m_data[$ilen][$assetid]['ema12']+$this->dependence['macd']->m_data[$ilen][$assetid]['ema26']);
            // $worst_macd_ratio = $worst_macd/($this->dependence['macd']->m_data[$ilen][$assetid]['ema26']);
            // }
            $minus_cnt += 1;
            $macd_seq[] = $this->dependence['macd']->m_data[$ilen][$assetid]['macd'];
            if ($minus_cnt >= 15) {
                break;
            }
        }
        printf("to sell %s\n",$this->assetName[$assetid]);
        var_dump($macd_seq);
//        var_dump(array_slice($this->kline_datas,-10));
        // if($toFall) continue;
        // if($minus_cnt>=15) continue;
        if ($minus_cnt < 4)
            return false;
        //if ($worst_macd_ratio < 0.5)
        //    return false;
        if(abs(array_sum($macd_seq)/count($macd_seq))<0.05*$this->dependence['macd']->largestMACD[$assetid]) {
            return false;
        }
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
        print(gmdate('Ymd H:i:s', $ti / 1000+8*3600)." now macd\n");
        $macd = [];
        for ($i = count($this->dependence['macd']->m_data) - 10; $i < count($this->dependence['macd']->m_data); $i += 1) {
            $macd[] = ($this->dependence['macd']->m_data[$i][1]['macd']);
        }
        var_dump($macd);
        $allmoney = array_sum($this->holdPos);
        for ($i = 1; $i <= $this->assetLen; $i ++) {
            if($this->holdPos[$i]<0.001*$allmoney) {            
                $tobuy = $this->generateToBuy($ti, $i);
                if ($tobuy) {
                    $this->objPos[$i] = 1;
                }
            }
            if($this->holdPos[$i]>0.2*$allmoney) {
                $toSell = $this->generateToSell($ti, $i);
                if($toSell) {
                    $this->objPos[$i] = 0;
                }
            }
        }
        $other_money = array_sum(array_slice($this->objPos, 1));
        if($other_money==0) {
            $this->objPos[0] = 1;
        } else {
            $this->objPos[0] = 0*$other_money;
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
