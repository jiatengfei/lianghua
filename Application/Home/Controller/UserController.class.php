<?php
namespace Home\Controller;

use Think\Controller;

class UserController extends HomeController
{

    public function index()
    {}

    /**
     * [information 会员信息操作]
     * 
     * @return [type] [description]
     */
    public function information()
    {
        $ip = get_client_ip();
        $token_id = $_REQUEST['token_id'];
        $id = $this->get_login_uid();
        
        $data['user_name'] = I('user_name');
        if (! $data['user_name']) {
            $this->return_info(0, '请填写姓名');
        }
        $data['user_idcard'] = I('user_idcard');
        if (! $data['user_idcard']) {
            $this->return_info(0, '请填写身份证！');
        }
        $data['user_education'] = I('user_education');
        if (! $data['user_education']) {
            $this->return_info(0, '请填写学历');
        }
        $data['user_hunyin'] = I('user_hunyin');
        if (! $data['user_hunyin']) {
            $this->return_info(0, '请填写婚姻');
        }
        $data['user_length'] = I('user_length');
        if (! $data['user_length']) {
            $this->return_info(0, '请填写居住时长!!');
        }
        $data['user_province'] = I('user_province');
        if (! $data['user_province']) {
            $this->return_info(0, '请填写省份');
        }
        $data['user_address'] = I('user_address');
        if (! $data['user_address']) {
            $this->return_info(0, '请填写地址');
        }
        $data['user_qq'] = I('user_qq');
        if (! $data['user_qq']) {
            $this->return_info(0, '请填写QQ');
        }
        $data['user_email'] = I('user_email');
        if (! $data['user_email']) {
            $this->return_info(0, '请填写邮箱');
        }
        
        if (! preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,6}$/', $data['user_name'])) {
            $this->return_info(0, '请填写您的真实姓名！');
        }
        
        if (! filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
            $this->return_info(0, '邮箱格式不正确！');
        }
        
        if (! validation_Id_card($data['user_idcard'])) {
            $this->return_info(0, '身份证格式有误！');
        }
        // $mail_code = I('mail_code');
        // if($mail_code != session('emailcode')){
        // $this->return_info(0,'验证码不对');
        // }
        // if(!$this->verifiCode($data['user_email'],$mail_code)){
        // //$this->return_info(0,'验证码错误！！');
        // }
        $check = M('user_info')->where("user_idcard = '{$data['user_idcard']}' and id <> '{$id}'")->getField('user_idcard');
        if (! empty($check)) {
            $this->return_info(0, '该身份证已被注册使用！');
        }
        $mobile = M('user_info')->where("id = {$id}")
            ->field('mobile')
            ->find();
        
        $res = M('user_info')->where("id = '{$id}'")->save($data);
        
        $path = C('LM')['TD'];
        $str = str_pad($id, 9, '0', STR_PAD_LEFT);
        $dir1 = substr($str, 0, 3);
        $dir2 = substr($str, 3, 3);
        $folder = $path . $dir1 . '/' . $dir2 . '/';
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        $file_path = $folder . $id . '.txt';
        // 读取保存的文件
        $file = file_get_contents($file_path);
        if ($file) {} else {
            $code = new CodeController();
            $code->tongdun($id, $data['user_idcard'], $data['user_name'], $mobile['mobile'], $ip, $token_id, $file_path);
        }
        if (false !== $res || 0 !== $res) {
            $this->return_info(1, '添加完成', $res);
        } else {
            $this->return_info(0, '请求失败，请重试!');
        }
    }

    /**
     * [sendEmail 发送邮件验证码]
     * 
     * @return [type] [description]
     */
    public function sendEmail()
    {
        $user_email = I('email');
        $this->get_login_uid();
        
        // php邮箱验证
        if (empty($user_email) || ! filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $this->return_info(0, '非法的邮箱地址！');
        }
        $rand = mt_rand(0, 999999); // 随机数
        $content = $user_email . '  用户您好！<br>感谢您使用微耳钱包贷款平台，您的邮箱验证码是:' . $rand . '请勿将信息告知他人。如有疑问请致电010~84845085进行咨询。';
        
        // session($uid,$rand); //保存验证存在问题，过期时间如何保证
        if (! $this->verifiCation($user_email, $rand, C('Be_overdue')['Email'])) {
            $this->return_info(0, '验证码已发送，请请勿重复提交！！！');
        }
        
        $res2 = think_send_mail($user_email, $user_email, C('THINK_EMAIL')['Email_title'], $content, 2, 2);
        if ($res2) {
            $this->return_info(1, '发送成功');
        } else {
            $this->return_info(0, '发送失败');
        }
    }

    /**
     * [unit_info 职业信息]
     * 
     * @return [type] [description]
     */
    public function unit_info()
    {
        $id = $this->get_login_uid();
        
        $data['career'] = I('career/d');
        if (! $data['career']) {
            $this->return_info(0, '请填写职业');
        }
        // $data['income'] = I('income');
        // if(!$data['income']){
        // $this->return_info(0,'请填写收入');
        // }
        $data['unit_name'] = I('unit_name');
        if (! $data['unit_name']) {
            $this->return_info(0, '请填写单位名称');
        }
        $data['unit_province'] = I('unit_province');
        if (! $data['unit_province']) {
            $this->return_info(0, '请填写单位所在省份');
        }
        
        $data['unit_address'] = I('unit_address');
        if (! $data['unit_address']) {
            $this->return_info(0, '请填写单位地址');
        }
        $data['unit_tel'] = I('unit_tel');
        if (! $data['unit_tel']) {
            $this->return_info(0, '请填写单位电话！');
        }
        $res = M('user_info')->where("id=$id")->save($data);
        if (false !== $res || 0 !== $res) {
            $this->return_info(1, '添加完成');
        } else {
            $this->return_info(0, '添加失败');
        }
    }

    /**
     * [kin_info 紧急联系人信息]
     * 
     * @return [type] [description]
     */
    public function kin_Info()
    {
        $id = $this->get_login_uid();
        $data['kin_name'] = I('kin_name');
        if (! $data['kin_name']) {
            $this->return_info(0, '请填写亲属姓名');
        }
        $data['kin'] = I('kin');
        if (! $data['kin']) {
            $this->return_info(0, '请填写亲属关系');
        }
        $data['kin_tel'] = I('kin_tel');
        if (! $data['kin_tel']) {
            $this->return_info(0, '请填写亲属电话');
        }
        
        $data['society'] = I('society');
        if (! $data['society']) {
            $this->return_info(0, '请填写社会关系');
        }
        $data['society_name'] = I('society_name');
        if (! $data['society_name']) {
            $this->return_info(0, "请填写{$data['society']}姓名");
        }
        $data['society_tel'] = I('society_tel');
        if (! $data['society_tel']) {
            $this->return_info(0, '请填写社会关系电话');
        }
        
        if (! is_mobile($data['kin_tel']) || ! is_mobile($data['society_tel'])) {
            $this->return_info(0, '手机号格式不正确！！');
        }
        
        if ($data['kin_tel'] == $data['society_tel']) {
            $this->return_info(0, '手机号码不可重复！！');
        }
        
        // //重复验证
        // $where['uid'] = $id;
        // $check = M('user_mobile')->where("mobile_phone = '".$data['kin_tel']."' or mobile_phone = '".$data['society_tel']."'")->where($where)->getField('uid');
        // if(!empty($check)){
        // $this->return_info(0,'手机号码不可与自己相同！！');
        // }
        
        $res = M('user_info')->where("id=$id")
            ->data($data)
            ->save();
        
        if (false !== $res || 0 !== $res) {
            $this->return_info(1, '添加完成');
        } else {
            $this->return_info(0, '添加失败');
        }
    }

    /**
     * userInfo_Confirm 个人信息是否添加完善
     * return_info 返回ajax
     */
    public function userInfo_Confirm()
    {
        $uid = $this->get_login_uid();
        
        $field = 'zm_num,zm_openid,user_name,user_idcard,user_education,user_hunyin,user_province,user_address,user_length,user_qq,user_email,unit_name,unit_province,unit_address,unit_tel,kin,society,kin_tel,society_tel,zm_credit,career,society_name,kin_name';
        
        $result = M('user_info')->where("id = {$uid}")
            ->field($field)
            ->find();
        if (! empty($result['career'])) {
            $result['position'] = M('front_position')->where("id = {$result['career']}")->getField('position');
            // 学历必认证的条件
            if ($result['career'] == 25) {
                $result['xueli'] = 1;
            }
        }
        
        foreach ($result as $key => $value) {
            
            if (empty($value)) {
                $data['type'] = 1; // 含有未填写项
            }
        }
        
        if ($result['xueli'] == 1) { // 学历必填
            $education = M('user_credit')->where("uid = {$uid}")->getField('education');
            if ($education == 0 || $education == 3) {
                $data['type'] = 1;
            }
        }
        
        $data['value'] = $result;
        if ($data['type'] == 1) { // 不完整！
            $this->return_info(1, '信息不完整！！', $data);
        } else {
            $data['type'] = 0; // 完整
            $this->update_credit('user_info'); // 修改个人信息状态
            $this->return_info(1, '个人信息已完善！！', $data);
        }
    }
    
    // 记录用户手机通讯录
    public function contacts()
    {
        $uid = $this->get_login_uid();
        $contacts = file_get_contents("php://input", true);
        if (empty($contacts)) {
            $this->return_info(0, '授权内容失败');
        }
        
        $con = M('user_contacts')->where("uid=$uid")->find();
        // 内容存放文件
        if (empty($id)) {
            $id = substr(time(), 1);
            $dir1 = substr($id, 0, 3);
            $dir2 = substr($id, 3, 3);
            $dir3 = substr($id, 6);
            $folder = 'Public/contacts/' . $dir1 . '/' . $dir2 . '/' . $dir3;
            $folder_arr = explode('/', $folder);
            $mkfolder = '';
            for ($i = 0; isset($folder_arr[$i]); $i ++) {
                $mkfolder .= $folder_arr[$i];
                if (! is_dir($mkfolder))
                    mkdir("$mkfolder", 0777);
                $mkfolder .= '/';
            }
            $filename = uniqid($id) . '.jpg';
            $save_to = $folder . '/' . $filename;
        } else {
            $save_to = $con['contacts'];
        }
        file_put_contents($save_to, $contacts);
        
        $data = array(
            'uid' => $uid,
            'contacts' => $save_to,
            'info' => serialize($_REQUEST)
        );
        
        // 解析device
        if (! empty($_GET['device'])) {
            $result = json_decode($_GET['device'], 1);
            $data['pserial'] = $result['serial'];
            $data['puuid'] = $result['uuid'];
        }
        
        // 解析position
        if (! empty($_GET['position'])) {
            $result = json_decode($_GET['position'], 1);
            $data['position'] = $result['latitude'] . ',' . $result['longitude'];
        }
        
        if (empty($con)) {
            $res = M('user_contacts')->add($data);
        } else {
            $res = M('user_contacts')->where("uid=$uid")->save($data);
        }
        if ($res) {
            $this->update_credit('contacts');
            $this->return_info(1, '授权成功');
        } else {
            $this->return_info(0, '授权失败');
        }
    }

    /**
     * [stat 用户填写信息状态]
     * 
     * @return [type] [description]
     */
    public function stat()
    {
        $where['uid'] = $this->get_login_uid();
        $field = 'mobile_phone,user_info,identity,bankcard,phone,contacts';
        $credit = M('user_credit')->where($where)
            ->field($field)
            ->find();
        
        if (! empty($credit)) {
            $this->return_info(1, 'ok:200', $credit);
        } else {
            $this->return_info(0, 'Error');
        }
    }

    /**
     * [get_userinfo 获取用户基本信息]
     * 
     * @return [type] [description]
     */
    public function get_userinfo()
    {
        $uid = $this->get_login_uid();
        $where['wx_uid'] = $uid;
        $info = M('user_wx')->where($where)
            ->field('nickname,headimgurl,wx_uid')
            ->find();
        if (empty($info)) {
            $field = 'user_name,mobile,id';
            $data = M('user_info')->where(array(
                'id' => $uid
            ))
                ->field($field)
                ->find();
            $info['wx_uid'] = $data['id'];
            $info['nickname'] = $data['user_name'] ? $data['user_name'] : $data['mobile'];
            $info['headimgurl'] = M('user_img')->where(array(
                'uid' => $uid
            ))->getField('user_head');
        } else {
            $info['nickname'] = base64_decode($info['nickname']);
        }
        
        $info['wx_uid'] = invite_number($info['wx_uid']);
        if ($info) {
            $this->return_info(1, 'ok:200', $info);
        } else {
            $this->return_info(2001);
        }
    }

    /**
     * 反馈前台职业数据
     * 
     * @return json $jobs 职业列表
     */
    public function posiTion()
    {
        // $this->get_login_uid();//防止故意调取
        $position = M('front_position')->order('sort asc')
            ->field('id,position')
            ->select();
        $this->return_info(1, 'OK', $position);
    }

    /**
     * [intvalChild 获取邀请的下级列表]
     * 
     * @return [type] [description]
     */
    public function intvalChild()
    {
        $uid = $this->get_login_uid();
        
        $list = M('user_info')->where("pid = {$uid} and jz = 1")
            ->field('user_name,creat_time,mobile,id')
            ->select();
        
        foreach ($list as $key => $val) {
            
            $wx = M('user_wx')->where("wx_uid = {$val['id']} ")
                ->field('nickname,headimgurl,headimg')
                ->find();
            
            if (! empty($wx)) {
                
                if (empty($list[$key]['user_name']) && ! empty($wx['nickname']))
                    $list[$key]['user_name'] = base64_decode($wx['nickname']);
                
                if (! empty($wx['headimg'])) {
                    $list[$key]['headimg'] = C('USER_CLIENT_DOMAIN') . $wx['headimg'];
                } else {
                    $list[$key]['headimg'] = $wx['headimgurl'];
                }
            }
            // 获取第一级的收益 20%
            $userProfti = M('task_list')->where("uid = {$val['id']} and type = 2")->sum('reward_num');
            $list[$key]['onePro'] = round($userProfti * 0.2, 2);
            
            // 第二级的收益 10%
            $subordinate = M('user_info')->where("pid = {$val['id']}")
                ->field('id')
                ->select(false);
            $childNum = M('task_list')->where(array(
                'type' => 2
            ))
                ->where('uid IN (' . $subordinate . ')')
                ->sum('reward_num');
            $list[$key]['childPro'] = round($childNum * 0.1, 2);
            
            unset($list[$key]['id']);
        }
        
        $this->return_info(0, 'OK', $list);
    }
}//end