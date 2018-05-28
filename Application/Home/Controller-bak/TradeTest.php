<?php
namespace Home\Controller;

class TradeTest extends TradController
{

    protected $assetLen;
 // �ɽ����ʲ�������
    protected $base;

    protected $assetName;

    public function __construct($base, $money)
    {
        /*
         * $base: ��׼�ҵ����� $money: ��ʼ�ʽ���
         * HT: {USDT,BTC,ETH)
         * USDT: {HT,BTC,ETH,XRP,DASH,EOS}
         * BTC: {USDT,HT,ETH,XRP,DASH,EOS}
         * ETH: {USDT,HT,BTC,EOS}
         * XRP: {USDT,BTC}
         * DASH: {USDT,BTC}
         * EOS: {USDT,BTC}
         *
         * ÿ�ֻ�׼�Ҷ�Ӧ�Ŀɽ��ױҡ�����������ݿ��������Ϊϵͳ�����ļ���һ�㲻���
         *
         * �����ʲ�����dataNum�� ��һ��Ԫ�ر�ʾ�ֻ�׼�ҵ���������ʼ=$money,������Ϊ0
         *
         *
         */
        // ��ֵ�ɽ����ʲ����������Լ�ÿ���ʲ�������
        $this->assetLen = 3;
        $this->assetName = [];
        $this->base = $base;
        $this->asset[0] = 'HT';
        $declist = [
            USDT,
            BTC,
            ETH
        ]; // get from database or conf
        for ($i = 0; $i < $this->assetLen; $i += 1) {
            $this->assetName[$i + 1] = $declist[$i];
        }
        
        // ��ʼ��������
        $this->holdPos[0] = $money;
        for ($i = 0; $i < $this->assetLen; $i += 1) {
            $this->holdPos[$i + 1] = 0;
        }
        $this->objPos = $this->holdPos;
    }

    public function convert_kline_data($prst)
    {
        $rst = array();
        $rst['open'] = 1.0 / $prst['open'];
        $rst['close'] = 1.0 / $prst['close'];
        $rst['high'] = 1.0 / $prst['high'];
        $rst['low'] = 1.0 / $prst['low'];
        $rst['volume'] = $prst['count'];
        $rst['count'] = $prst['volume'];
        return $rst;
    }

    /*
     * ��ȡkline data�� ��ȡtiʱ��ʱ���µ�$num��kline�� $rtime��ʾ1min 5min ����15min
     * ����һ�����飬����ÿ��Ԫ���Ǹ�map��map��key�ֱ�Ϊopen close high low volume count
     */
    public function get_kline_data($ti, $asset, $num, $rtime)
    {
        $objstr = $asset . $this->base;
        // ���objstr������ ��USDTHTû�У�ֻ��HTUSDT����ô��HTUSDTȥ��ȡkline��Ȼ��ÿ��kline���ε���convert_kline_dataȥת��
        // ������ڣ������ݿ���������ؽ������һ��map�key�ֱ�Ϊopen close high low volume count
    }

    public function convert_hist_order($prst)
    {
        $rst = array();
        $rst['ts'] = $prst['ts'];
        $rst['price'] = 1.0 / $prst['price'];
        $rst['amount'] = $prst['price'] * $prst['amount'];
        if ($prst['direction'] == 'sell') {
            $rst['direction'] = 'buy';
        } else {
            $rst['direction'] = 'sell';
        }
        return $rst;
    }

    /*
     * ��ȡtiʱ��֮ǰ$long������Ϊ��λ��Ĭ��600�룩ʱ���ڵ�����order������ʱ��Ӻ�ǰ����
     * ����һ�����飬ÿ������Ԫ����һ��map��keyΪ ts���ɽ�ʱ�䣩��amount���ɽ�������price���ɽ��ۣ���direction���ɽ�����
     */
    public function get_hist_order_by_time($ti, $asset, $long = 600)
    {
        $objstr = $asset . $this->base;
        // ���objstr������ ��USDTHTû�У�ֻ��HTUSDT����ô��HTUSDTȥ��ȡ��Ȼ�����convert_hist_orderȥת��
        // ������ڣ������ݿ���������ؽ������һ��map�key�ֱ�Ϊ ts,amount,price,direction
    }

    /*
     * ��ȡtiʱ��֮ǰ��$amount��order������ʱ��Ӻ�ǰ����Ĭ��100��
     */
    public function get_hist_order_by_amount($ti, $asset, $amount = 100)
    {
        $objstr = $asset . $this->base;
        // ���objstr������ ��USDTHTû�У�ֻ��HTUSDT����ô��HTUSDTȥ��ȡ��Ȼ�����convert_hist_orderȥת��
        // ������ڣ������ݿ���������ؽ������һ��map�key�ֱ�Ϊ ts,amount,price,direction
    }

    private function convert_depth_record($prst)
    {
        $rst = array();
        $rst['price'] = 1.0 / $prst['price'];
        $rst['qty'] = $prst['qty'] * $rst['price'];
        $rst['ts'] = $prst['ts'];
        if ($prst['type'] == 'bids') {
            $rst['type'] = 'asks';
        } elseif ($prst['type'] == 'asks') {
            $rst['type'] = 'bids';
        } else {
            return null;
        }
        return $rst;
    }

    /*
     * ��ȡtiʱ��ʱ���depth . ���ݼ۸�����bid�Ӵ�С�� ask��С����
     */
    public function get_depth_data($ti, $asset, $depth, $type)
    {
        $objstr = $asset . $this->base;
        // ���objstr������ ��USDTHTû�У�ֻ��HTUSDT����ô��HTUSDTȥ��ȡ��Ȼ�����convert_hist_orderȥת��
        // ������ڣ������ݿ���������ؽ������һ��map�key�ֱ�Ϊ ts,amount,price,direction
    }

    public function check_valid()
    {
        assert(count($this->holdpos) == $this->assetLen + 1);
        assert(count($this->objPos) == $this->assetLen + 1);
        assert(count($this->assetName) == $this->assetLen + 1);
    }

    /*
     * ����tiʱ���µ�Ŀ��postionֵ
     */
    public function getpos($t)
    {
        for ($i = 1; $i <= $this->assertlen; $i += 1) {}
    }
}