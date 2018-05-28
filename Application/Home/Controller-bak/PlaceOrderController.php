<?php
use Home\Controller\TradController;

class PlaceOrderController extends TradController
{

    public $costRatio = 0.001;

    public function __construct($system, $params)
    {
        parent::__construct($system, $params);
        if (isset($system['costRatio'])) {
            $this->costRatio = $system['costRatio'];
        }
    }

    public function changePos($from, $to)
    {
        if ($this->is_live) {} else {
            $this->changePosSim($from, $to);
        }
    }

    /*
     * 实际交易，从$from 到 $to
     * 返回交易之后的持仓
     */
    public function tradeLive($strName, $from, $to)
    {}

    /*
     * 实际交易，从$from 到 $to, 打印交易信息，并估算交易成本
     * 返回交易之后的持仓
     */
    public function changePosLive($straName, $from, $to)
    {
        $real_to = $this->tradeLive($straName, $from, $to);
        $lastprice = $this->kline_datas[count($this->kline_datas) - 1];
        for ($i = 1; $i <= $this->assetLen; $i += 1) {
            $change = $real_to[i] - $from[i];
            $cost = abs($change) * $lastprice[$i]['close'] * $this->costRatio;
            if ($change < 0) {
                print($straName . ' sell ' . $change . ' ' . $this->assetName[$i] . ' with estimate cost ' . $cost);
            } else 
                if ($change > 0) {
                    print($straName . ' buy ' . $change . ' ' . $this->assetName[$i] . ' with estimate cost ' . $cost);
                }
        }
        return $real_to;
    }

    /*
     * 模拟交易，从$from 到 $to，默认以close全部成交
     * 返回交易之后的持仓
     */
    public function tradeSim($straName, $from, $to)
    {
        return $to;
    }

    /*
     * 模拟交易，从$from 到 $to, 打印交易信息，并估算交易成本
     * 返回交易之后的持仓
     */
    public function changePosSim($straName, $from, $to)
    {
        $real_to = $this->tradeSim($straName, $from, $to);
        $lastprice = $this->kline_datas[count($this->kline_datas) - 1];
        for ($i = 1; $i <= $this->assetLen; $i += 1) {
            $change = $real_to[i] - $from[i];
            $cost = abs($change) * $lastprice[$i]['close'] * $this->costRatio;
            if ($change < 0) {
                print($straName . ' sell ' . $change . ' ' . $this->assetName[$i] . ' with estimate cost ' . $cost);
            } else 
                if ($change > 0) {
                    print($straName . ' buy ' . $change . ' ' . $this->assetName[$i] . ' with estimate cost ' . $cost);
                }
        }
        return $real_to;
    }
}