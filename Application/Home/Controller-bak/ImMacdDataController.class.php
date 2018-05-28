<?php
namespace Home\Controller;

class ImMacdDataController extends TradController
{

    public $m_data;

    private $short = 26;

    private $long = 12;

    private $M = 9;

    public function __construct($system = '1', $params = '')
    {
        parent::__construct($system, $params);
        if (isset($params['short'])) {
            $this->short = $params['short'];
        }
        if (isset($params['long'])) {
            $this->long = $params['long'];
        }
        if (isset($params['M'])) {
            $this->M = $params['M'];
        }
    }

    /**
     * 计算macd
     * 
     * @param unknown $df
     *            大盘数据
     * @param number $short
     *            短线
     * @param number $long
     *            长线
     * @param number $M
     *            复合次数
     */
    private function cal_MACD()
    {
        var_dump('cal_macd');
        $macd_len = count($this->m_data);
        $kline_len = count($this->kline_datas);
        if ($macd_len == $kline_len) {
            $macd_len = $kline_len - 1;
        }
        
        // foreach ($df as $i=>$v) {
        for ($i = $macd_len; $i < $kline_len; $i += 1) {
            if ($i == 0) {
                var_dump('first macd');
                for ($assetid = 1; $assetid <= $this->assetLen; $assetid ++) {
                    $this->m_data[$i][$assetid]['ema' . $this->short] = $this->kline_datas[$i][$assetid]['close'];
                    $this->m_data[$i][$assetid]['ema' . $this->long] = $this->kline_datas[$i][$assetid]['close'];
                    $this->m_data[$i][$assetid]['diff'] = 0;
                    $this->m_data[$i][$assetid]['dea'] = 0;
                    $this->m_data[$i][$assetid]['macd'] = 0;
                    
                    $this->m_data[$i][$assetid]['ma5'] = $this->kline_datas[$i][$assetid]['close'];
                    $this->m_data[$i][$assetid]['ma10'] = $this->kline_datas[$i][$assetid]['close'];
                    
                    $this->m_data[$i][$assetid]['diffgo'] = 0;
                    $this->m_data[$i][$assetid]['ma5go'] = 0;
                    $this->m_data[$i][$assetid]['macdgo'] = 0;
                }
            } else {
                for ($assetid = 1; $assetid <= $this->assetLen; $assetid ++) {
                    
                    foreach ([
                        5,
                        10
                    ] as $n) {
                        $value = 0;
                        for ($j = 0; $j < $n; $j += 1) {
                            if ($j > $i)
                                break;
                            $value += $this->kline_datas[$i - $j][$assetid]['close'];
                        }
                        $value = $value / $j;
                        $this->m_data[$i][$assetid]['ma' . $n] = $value;
                    }
                    $this->m_data[$i][$assetid]['ema' . $this->short] = (2 * $this->kline_datas[$i][$assetid]['close'] + ($this->short - 1) * $this->m_data[$i - 1][$assetid]['ema' . $this->short]) / ($this->short + 1);
                    $this->m_data[$i][$assetid]['ema' . $this->long] = (2 * $this->kline_datas[$i][$assetid]['close'] + ($this->long - 1) * $this->m_data[$i - 1][$assetid]['ema' . $this->long]) / ($this->long + 1);
                    $this->m_data[$i][$assetid]['diff'] = $this->m_data[$i][$assetid]['ema' . $this->short] - $this->m_data[$i][$assetid]['ema' . $this->long];
                    $this->m_data[$i][$assetid]['dea'] = (2 * $this->m_data[$i][$assetid]['diff'] + ($this->M - 1) * $this->m_data[$i - 1][$assetid]['dea']) / ($this->M + 1);
                    $this->m_data[$i][$assetid]['macd'] = ($this->m_data[$i][$assetid]['diff'] - $this->m_data[$i][$assetid]['dea']);
                    
                    $this->m_data[$i][$assetid]['diffgo'] = $this->m_data[$i][$assetid]['diff'] - $this->m_data[$i - 1][$assetid]['diff'];
                    $this->m_data[$i][$assetid]['ma5go'] = $this->m_data[$i][$assetid]['ma5'] - $this->m_data[$i - 1][$assetid]['ma5'];
                    $this->m_data[$i][$assetid]['macdgo'] = $this->m_data[$i][$assetid]['macd'] - $this->m_data[$i - 1][$assetid]['macd'];
                }
            }
        }
        var_dump('cal macd over');
    }

    /*
     * 计算MACD
     */
    public function calculate()
    {
        $this->cal_MACD();
    }

    /*
     * 返回MACD
     */
    public function getData()
    {
        return $this->m_data;
    }
}
