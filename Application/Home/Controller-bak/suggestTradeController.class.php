<?php
namespace Home\Controller;

class IMDataMACDController extends TradController
{

    protected static $kline_datas;

    protected static $is_live;

    protected $min_trade_volume;

    protected $hist_kline_ts;
 // 数组存放到模拟时间为止的所有kline的时间
    protected $back_segs;
 // 往前回看的段的数量（1min为一段或者15min为一段，根据实际配置文件)
    protected $tradeprice;

    static function prepareData($ti)
    {
        // 更新hist_kline_ts到最新
        // 同时更新kline_datas到最新 (kline_datas和ts一一对应）
    }
}