<?php
namespace Home\Controller;

use Think\Controller;
use Alidayu\AlidayuClient as Client;
use Alidayu\Request\SmsNumSend;
use Baofu\Daikou\Baofu;
// 验证信息
class VerificationController extends HomeController
{

    public function mobile_phone()
    {
        $uid = $this->get_login_uid();
        $date = M('user_credit')->where("uid=$uid")->find();
        if ($date['phone']) {
            $this->update_credit('mobile_phone');
            $this->return_info(1, '成功');
        } else {
            $this->return_info(0, '失败:请先进行通讯录和运营商授权');
        }
    }
    
    // 银行预留手机号验证码
    public function send_Code()
    {
        $mobile = I('mobile'); // 接收手机号
        $this->get_login_uid(); // 防止
                                
        // 验证
        if (! is_mobile($mobile)) {
            $this->return_info(0, '手机号格式错误！！');
        }
        // 验证手机唯一性
        $phone = M('user_bank')->where("yu_tel='%s'", array(
            $mobile
        ))->find();
        if (! empty($phone)) {
            $this->return_info(0, '该手机号已经注册！！');
        }
        
        $client = new Client();
        $request = new SmsNumSend();
        // 短信内容参数
        $smsParams = [
            'code' => $this->randString(),
            'product' => '微耳钱包贷款'
        ];
        // 设置请求参数
        $req = $request->setSmsTemplateCode('SMS_16145159')
            ->setRecNum($mobile)
            ->setSmsParam(json_encode($smsParams))
            ->setSmsFreeSignName('身份验证')
            ->setSmsType('normal')
            ->setExtend('demo');
        $sms = $client->execute($req);
        if ($sms['alibaba_aliqin_fc_sms_num_send_response']['result']['success']) {
            // **********原来的存储方案************
            // session_start();
            // $_SESSION['user_'.$mobile] = $smsParams['code'];
            // ************************************
            if ($this->verifiCation($mobile, $smsParams['code'], C('Be_overdue')['Phone'])) {
                $this->return_info(1, '发送成功！');
            } else {
                $this->return_info(0, '发送失败,请重试！');
            }
        } else {
            $this->return_info(0, '发送失败,请重试！！');
        }
    }

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
     * check_card 验证银行卡是否正确
     * 
     * @param string $[card]
     *            [<银行卡号>]
     * @return boolen [<true/false>]
     */
    protected function check_Card($no)
    {
        $no = str_replace('-', '', $no);
        $arr_no = str_split($no);
        $last_n = $arr_no[count($arr_no) - 1];
        krsort($arr_no);
        $i = 1;
        $total = 0;
        foreach ($arr_no as $n) {
            if ($i % 2 == 0) {
                $ix = $n * 2;
                if ($ix >= 10) {
                    $nx = 1 + ($ix % 10);
                    $total += $nx;
                } else {
                    $total += $ix;
                }
            } else {
                $total += $n;
            }
            $i ++;
        }
        $total -= $last_n;
        $total *= 9;
        if ($last_n == ($total % 10)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * bank_Mes 银行卡信息
     * 
     * @param string $[uniqidcode]
     *            [<身份认证ID>]
     * @return [<银行卡详细信息>]
     */
    public function bank_Mes()
    {
        $uid = $this->get_login_uid();
        $bank = M('user_bank')->where('uid=%d', array(
            $uid
        ))
            ->field('bank_pro,bank_name,yu_tel,bank_num')
            ->find();
        $info = M('user_info')->where('id=%d', array(
            $uid
        ))
            ->field('user_idcard,user_name')
            ->find();
        
        if (empty($bank)) {
            if (! empty($info))
                $mes = $info;
            
            $this->return_info(0, '还未填写', $mes);
        } else {
            if (! empty($info))
                $mes = array_merge($bank, $info);
            $this->return_info(1, '填写完成!!!', $mes);
        }
    }

    /**
     * [bankList 支持的银行列表]
     * 
     * @return json [银行信息]
     */
    public function bank_List()
    {
        $uid = $this->get_login_uid(); // 检测登录
        $bank = M('bank_list')->where('b_status = 1')
            ->order('b_sort desc')
            ->field('b_id,b_name,b_code')
            ->select();
        $this->return_info(1, 'OK', $bank);
    }

    /**
     * [bankCode 添加银行卡图形验证码]
     * 
     * @return [type] [description]
     */
    public function bankCode()
    {
        pic_code('ad_bank');
    }

    /**
     * 银行卡认证点击获取验证码 宝付认证
     * 
     * @param string $[pay_code]
     *            [银行卡编码]
     * @param string $[acc_no]
     *            [银行卡卡号]
     * @param string $[id_card]
     *            [身份证号码]
     * @param string $[id_holder]
     *            [姓名]
     * @param string $[mobile]
     *            [银行预留手机号]
     */
    public function pretreatMent()
    {
        $con = $this->getPostData();
        // 调试信息 生产环境删除
        // $con = array(
        // 'acc_no'=>"6212260200093818157",
        // 'id_card'=>"131121199312233814",
        // 'id_holder'=>"武雪乐",
        // 'mobile' =>'15311759319',
        // 'pay_code'=>"ICBC",
        // 'uid'=>218
        // );
        
        // //验证码调用限制 55 S 一次
        // $pic_time = M('user_info')->where("id = '{$con['uid']}'")->getField('pic_time');
        
        // if(time() < $pic_time+55){
        // $this->return_info(0,'请勿重复刷新！');
        // }else{
        // M('user_info')->where("id = '{$con['uid']}'")->save(['pic_time'=>time()]);
        // }
        if (! checkCode($con['pic_code'], 'ad_bank')) {
            $this->return_info(0, '图形验证码输入错误！');
        }
        
        $con['acc_no'] = str_replace('-', '', $con['acc_no']);
        
        $this->checkInput($con);
        
        unset($con['uid']);
        $baofu = new Baofu();
        $request = $baofu->preBound($con);
        
        if ($request['resp_code'] == '0000') {
            $con['trans_id'] = $request['trans_id'];
            $this->return_info(1, 'ok', $con);
        } else 
            if (empty($request)) {
                $this->return_info(0, '网络连接失败，请重试！！！');
            } else {
                $this->return_info(0, $request['resp_msg']);
            }
    }

    /**
     * 银行确认绑卡
     * 
     * @param $[trans_id] [预绑定交易的订单号]            
     * @param $[sms_code] [短信验证码]            
     *
     */
    public function comfireCard()
    {
        $con = $this->getPostData(); // post 数组
        $uid = $con['uid'];
        // 测试数据
        // $con['id_card'] = '';
        // $con['id_holder'] = '';
        // $con['trans_id'] = 'WR1502258005823';
        // $con['sms_code'] = '978724';
        // $con['pic_code'] = '13245'; //图像验证
        // $con['bankname'] = 1; //银行卡id
        // $con['bankpro'] = 'dsdfsfsd';//开户行
        // sms_code //短信验证码
        // trans_id //预绑定交易的订单号
        if (! isset($con['sms_code']) || empty($con['sms_code']))
            $this->return_info(0, '短信验证码不能为空！');
        if (! isset($con['trans_id']) || empty($con['trans_id']))
            $this->return_info(0, '必填项不能为空！');
        if (! $con['pay_code'])
            $this->return_info(0, '请选择开户行');
        if (! $con['bankpro'])
            $this->return_info(0, '请选择开户省');
        if (! $con['mobile'])
            $this->return_info(0, '请填写预留手机号');
        
        $this->checkInput($con);
        
        $data['trans_id'] = $con['trans_id']; // 预绑定交易的订单号
        $data['sms_code'] = $con['sms_code']; // 短信验证码
        $baofu = new Baofu();
        $request = $baofu->confireBind($data);
        
        if ($request['resp_code'] == '0000') {
            // 响应成功，银行卡信息入库
            $con['bind_id'] = $request['bind_id'];
            
            if ($this->do_bank($con)) {
                $this->return_info(1, 'ok');
            } else {
                $this->return_info(0, 'no');
            }
        } else {
            $this->return_info(0, $request['resp_msg']);
        }
    }

    /**
     * [do_bank 银行卡信息]
     * 
     * @return [type] [description]
     */
    private function do_bank($con)
    {
        $userInfo = M('user_info');
        $info = $userInfo->where("id = {$con['uid']}")
            ->field('user_name,user_idcard')
            ->find();
        if (empty($info)) {
            $userInfo->where("id = {$con['uid']}")
                ->data([
                'user_idcard' => $con['id_card'],
                'user_name' => $con['id_holder']
            ])
                ->save();
        } else {
            if ($info['user_idcard'] != $con['id_card'] || $info['user_name'] != $con['id_holder']) {
                $this->return_info(0, '所填身份证不一致！');
            }
        }
        $data['bind_id'] = $con['bind_id'];
        $data['uid'] = $con['uid'];
        $data['bank_name'] = $con['bankid'];
        $data['bank_pro'] = $con['bankpro'];
        $data['yu_tel'] = $con['mobile'];
        $data['bank_num'] = str_replace('-', '', $con['acc_no']);
        
        $bankId = M('user_bank')->data($data)->add();
        
        if ($bankId) {
            $this->update_credit('bankcard'); // 修改状态
            return true;
        } else {
            return false;
        }
    }

    /**
     * [checkInput 检测输入信息有效性]
     * 
     * @return [type] [description]
     */
    private function checkInput($con)
    {
        if (! validation_Id_card($con['id_card'])) {
            $this->return_info(0, '请输入正确的身份证号！！');
        }
        
        $con['acc_no'] = str_replace('-', '', $con['acc_no']);
        
        // 验证银行卡是否合法
        if (! $this->check_Card($con['acc_no'])) {
            $this->return_info(0, '请输入正确的银行卡号！！');
        }
        if (! is_mobile($con['mobile'])) {
            $this->return_info(0, '手机号格式错误！！');
        }
        
        // //验证手机唯一性
        // $phone = M('user_bank')->where("yu_tel='%s' and uid <> '%d'",array($con['mobile'],$con['uid']))->find();
        // if(!empty($phone)){
        // $this->return_info(0,'该手机号已经被使用！！');
        // }
        
        // 银行卡唯一验证
        $card = M('user_bank')->where("bank_num = '%s'", array(
            $con['acc_no']
        ))->find();
        if (! empty($card)) {
            $this->return_info(0, '该银行卡已经被使用！！');
        }
    }

    /**
     * [userInfo 获取用户身份证信息]
     * 
     * @return [type] [description]
     */
    public function userInfo()
    {
        $uid = $this->get_login_uid();
        
        $info = M('user_info')->where("id = {$uid}")
            ->field('user_name,user_idcard')
            ->find();
        if (empty($info)) {
            $this->return_info(2, '200');
        } else {
            $this->return_info(1, '200', $info);
        }
    }
}