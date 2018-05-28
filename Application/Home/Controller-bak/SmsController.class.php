<?php
/**
 * 阿里大鱼API接口（短信接口)
 * @author
 */
namespace Home\Controller;

use Think\Controller;
use Alidayu\AlidayuClient as Client;
use Alidayu\Request\SmsNumSend;
use Alidayu\Log;

class SmsController extends Controller
{

    /**
     * 获取随机位数数字
     * 
     * @param integer $len
     *            长度
     * @return string
     */
    protected static function randString($len = 6)
    {
        $chars = str_repeat('0123456789', $len);
        $chars = str_shuffle($chars);
        $str = substr($chars, 0, $len);
        return $str;
    }

    /**
     * [sendSms 发送短信的方法] //后台系统可将配置数据加入数据库，动态配置
     * 
     * @param [type] $template
     *            [发送短信的模版编号] 参照 阿里大鱼 配置
     * @param [type] $phone
     *            [手机号]
     * @param [type] $smsParams
     *            [短信内容参数] 参照 短信模版 设置
     * @param [type] $title
     *            [短信抬头]
     * @return [type] [boollen true/false]
     */
    public static function sendSms($template, $phone, $smsParams, $title)
    {
        $client = new Client();
        $request = new SmsNumSend();
        
        // 设置请求参数
        $req = $request->setSmsTemplateCode($template)
            ->setRecNum($phone)
            ->setSmsParam(json_encode($smsParams))
            -> // 短信内容参数
setSmsFreeSignName($title)
            ->setSmsType('normal')
            ->setExtend();
        $sms = $client->execute($req);
        self::setError($sms, $template, $phone, $smsParams, $title);
        return $sms;
    }

    /**
     * [setError 记录短信历史消息]
     * 
     * @param [type] $req
     *            [description]
     * @param [type] $template
     *            [description]
     * @param [type] $phone
     *            [description]
     * @param [type] $smsParams
     *            [description]
     * @param [type] $title
     *            [description]
     */
    protected static function setError($req, $template, $phone, $smsParams, $title)
    {
        $msg = '手机号  -->' . $phone . "\r\n";
        $msg .= '模板编号-->' . $template . "\r\n";
        $msg .= '短信签名-->' . $title . "\r\n";
        $msg .= '消息主体-->' . json_encode($smsParams) . "\r\n";
        if (isset($req['error_response'])) {
            $msg .= '<---------错误信息---------->' . "\r\n";
            foreach ($req['error_response'] as $key => $val) {
                $msg .= $key . '--->' . $val . "\r\n";
            }
            $type = $template . '/Error';
        }
        if (isset($req['alibaba_aliqin_fc_sms_num_send_response'])) {
            $msg . 'request_id------>' . $req['alibaba_aliqin_fc_sms_num_send_response']['request_id'] . "\r\n";
            $msg . 'model --->' . $req['alibaba_aliqin_fc_sms_num_send_response']['result']['model'] . "\r\n";
            $type = $template . '/Nomal';
        }
        Log::logWirte($msg, $type);
    }
    
    // 微信获取token操作
    public function token()
    {
        $result = file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wxef165ed7272bd44e&secret=3cf94e7bb2d3e02bf869552838bbead2');
        // var_dump(json_decode($result,true));exit;
        return $result;
    }

    /**
     * 发送模板消息操作
     * $uid 要查询人的id
     * $type 要发送的消息类型
     * $jinbi 增长的金币数 默认为空
     * $status 审核后的状态 1为通过 否则为拒绝
     */
    public function send($uid, $type, $type2 = '', $jinbi = '', $status = '')
    {
        $type = $this->type($uid, $type, $jinbi, $status);
        if ($type['status'] == - 1) {
            $str = '该用户没有关注微信公众号';
            return $str;
        }
        $wenxin = A('Weixin');
        $access_token = $wenxin->get_token();
        // 要发送的数据
        $data = array(
            'touser' => $type['openid'],
            'template_id' => $type['moban'],
            'data' => $type['array']
        );
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $vall = curl_exec($ch);
        curl_close($ch);
        var_dump($vall);
        exit();
    }
}