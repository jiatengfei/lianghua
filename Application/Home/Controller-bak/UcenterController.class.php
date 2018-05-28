<?php
namespace Home\Controller;

use Think\Controller;

class UcenterController extends HomeController
{

    public function login()
    {
        // 判断该用户是否登陆
        if (session('uid')) {
            $this->redirect('User/index');
        }
        
        $this->assign("mate_title", "登录");
        $this->display();
    }

    /*
     * 执行注册
     * param mobile 手机号
     * param yzm 手机验证码
     * param pwd1 密码
     * param is_check 用户协议 0：未同意 ；1：同意
     */
    public function do_reg()
    {
        if (IS_GET) {
            $mobile = trim(I('mobile'));
            $pwd = trim(I('pass_w'));
            $ms_code = trim(I('ms_code'));
            $type = trim(I('type'));
            $intval = trim(I('intival'));
            // 获取指定的标识号
            $type = empty($type) ? $type : 0;
            // 获取邀请码
            if (empty($intval)) {
                $pid = 0;
            } else {
                $pid = invite_decode($intval);
            }
            
            if (! is_mobile($mobile))
                $this->return_info(0, '手机号格式不正确！！');
            if (empty($ms_code) || ! isset($ms_code))
                $this->return_info(0, '短信验证码不能为空！');
            $pwd_yz = is_pwd($pwd);
            if ($pwd_yz['status'] == - 1) {
                $this->return_info(20001, $pwd_yz['str']);
            }
            
            if (! verificationCode('reg_doc', $mobile, $ms_code, 120)) {
                $this->return_info($pc_code, '短信验证码不正确');
            }
            
            // 判断手机号是否注册
            $map['mobile'] = $mobile;
            $mobile_info = M('user_info')->where($map)->getField('status');
            
            if (! empty($mobile_info)) {
                $this->return_info(0, '该手机号已被注册！');
            }
            
            $type_id = M('link', 'xy_')->where("type = '{$type}'")->getField('id');
            
            // 插入数据到user表
            $data['password'] = md5(md5($pwd) . $pwd); // md5(md5($pwd));
            $data['mobile'] = $mobile; // $mobile;
            $data['status'] = 1;
            $data['creat_time'] = time();
            $data['type'] = $type_id ? $type_id : 0;
            $data['pid'] = $pid ? $pid : 0;
            $data['jz'] = 1; // 注册来源微耳兼职
            
            if ($uid = $this->userDo($data)) {
                $this->return_info(1, '注册成功', [
                    'uniqidcode' => $this->create_code($uid)
                ]);
            } else {
                $this->return_info(20002);
            }
        }
    }

    /**
     * 用户信息入库，用于第一次生成各个数据表的关系
     * 
     * @param
     *            array data 用户信息
     *            
     */
    public function userDo($data)
    {
        // 开启事物
        M()->startTrans();
        
        $data['eg'] = 1; // 微耳E工
        $memberId = M('user_info')->add($data); // 返回用户自增id
                                                   
        // 用户验证表建立数据
        $credit['uid'] = $memberId;
        $creditId = M('user_credit')->add($credit);
        
        // 用户身份证表建立数据
        $Idcard['uid'] = $memberId;
        $cardId = M('user_img')->add($Idcard);
        
        // 用户学信网账号表建立数据
        $Idchsi['uid'] = $memberId;
        $chsiId = M('user_chsi')->add($Idchsi);
        
        // 关联数据是否添加成功
        if (! empty($memberId) && ! empty($creditId) && ! empty($cardId) && ! empty($chsiId)) {
            M()->commit();
            // 保存用户id
            session('uid', $memberId);
            return $memberId; // 返回用户自增ID
        } else {
            M()->rollback();
            return false;
        }
    }

    /**
     * do_login 执行登陆
     * 
     * @param
     *            mobile 手机号
     * @param
     *            password 密码
     *            
     */
    public function do_login()
    {
        if (IS_GET) {
            $mobile = I('mobile');
            $password = I('password');
            
            if (! $mobile || ! is_mobile($mobile)) {
                $this->return_info(20001, '手机号不正确');
            }
            
            // 判断手机号是否注册
            $map['mobile'] = $mobile;
            $mobile_info = M('user_info')->where($map)->getField('status');
            switch ($mobile_info) {
                case "0":
                    $this->return_info(0, '您的账号已被停用，请联系管理员启用或重新注册！');
                    break;
                case "1":
                    break;
                default:
                    $this->return_info(0, '该手机号还未注册，请注册！');
                    break;
            }
            // 验证账号密码
            $map['password'] = md5(md5($password) . $password);
            $map['status'] = 1;
            $login_info = M('user_info')->where($map)->getField('id');
            
            if ($login_info == null) {
                $this->return_info(0, '密码错误！');
            }
            // 保存用户id
            session('uid', $login_info);
            
            $this->return_info(1, "登录成功！", array(
                'uniqidcode' => $this->create_code($login_info),
                'uid' => invite_number($login_info)
            ));
        }
    }

    /*
     * 退出登陆
     */
    public function login_out()
    {
        session('uid', null);
        $this->redirect('Ucenter/login');
    }

    /*
     * 验证填写的手机验证码是否和发送的验证码一致
     * param mobile 手机号
     * param yzm 手机验证码
     */
    public function check_send_num()
    {
        if (IS_POST) {
            $mobile = trim(I('mobile'));
            $yzm = trim(I('yzm'));
            
            if (! $mobile || ! is_mobile($mobile)) {
                $this->return_info(0, '手机验证码不正确，请重新输入！');
            }
            if (! $yzm) {
                $this->return_info(0, '手机验证码不能为空！');
            }
            
            $mob = 'user_' . $mobile;
            $i = 0;
            $n = count($_SESSION);
            foreach ($_SESSION as $key => $value) {
                $i ++;
                if ($mob == $key && $yzm == $value) {
                    $this->return_info(1);
                } elseif ($n == $i) {
                    $this->return_info(0, '手机验证码错误，请输入正确的手机验证码！');
                }
                continue;
            }
        }
    }

    /*
     * 注册时发送短信验证码
     * parameter mobile
     */
    public function sendCode()
    {
        $mobile = I('mobile');
        $imgCode = I('img_auth');
        
        if (! $mobile) {
            $this->return_info(0, '手机号不能为空！');
        }
        
        if (! is_mobile($mobile)) {
            $this->return_info(0, '手机号请输入11位有效数字！');
        }
        
        if (! checkCode($imgCode)) {
            $this->return_info(2, '图形验证码不正确');
        }
        
        // 判断手机号是否注册
        $map['mobile'] = $mobile;
        $mobile_info = M('user_info')->where($map)->getField('status');
        // $map2['mobile_phone'] = $mobile;
        // $mobile_phone = M('user_mobile')->where($map2)->find();
        if (! empty($mobile_info)) {
            $this->return_info(0, '该手机号已被注册！');
        }
        
        $yanzhengma = mt_rand(100000, 999999);
        storageCode('reg_doc', $mobile, $yanzhengma, 2);
        
        $content = '验证码:' . $yanzhengma . ',请于10分钟内正确输入验证码';
        
        $return = $this->sendMs($mobile, $content);
        if ($return == 0) {
            $this->return_info(1, '发送成功', $session_id);
        } else {
            $this->return_info(0, "'发送失败，错误代码'.$return");
        }
    }

    /**
     * [pc_code 注册界面图形验证码]
     * 
     * @return [type] [description]
     */
    public function pc_code()
    {
        pic_Code();
    }

    /**
     * [checkMscode 注册时验证短信验证码验证第一次]
     * 
     * @return [type] [description]
     */
    public function checkMscode()
    {
        $mobile = trim(I('mobile'));
        $ms_code = trim(I('ms_code'));
        
        if (! is_mobile($mobile))
            $this->return_info(0, '手机号格式不正确！！');
        if (empty($ms_code) || ! isset($ms_code))
            $this->return_info(0, '验证码不能为空！');
        
        if (verificationCode('reg_doc', $mobile, $ms_code, 120, false)) {
            $this->return_info(1, 'OK');
        } else {
            $this->return_info(0, '短信验证码输入错误！');
        }
    }

    /**
     * 查询用户的认证手机号
     * 
     * @param string $[uniqidcode]
     *            [用户前端标识]
     * @return string $[mobile] [用户的认证手机号]
     */
    public function getMobile()
    {
        $uid = $this->get_login_uid();
        $mobile = M('user_mobile as mobile')->join('cash_user_info as info ON mobile.uid = info.id', 'LEFT')
            ->where("mobile.uid=$uid")
            ->field('mobile.mobile_phone as phone,info.password as pass')
            ->find();
        
        if (empty($mobile)) {
            $this->return_info(0, '请先认证手机！');
        } else {
            if ($mobile['pass'] == '') {
                $mobile['pass'] = 0; // 未绑定
            } else {
                $mobile['pass'] = 1; // 已绑定
            }
            $this->return_info(1, 'ok', $mobile);
        }
    }

    /**
     * 设定/修改绑定手机的密码
     * 
     * @param string $[uniqidcode]
     *            [用户前端标识]
     * @param string $[password1]
     *            [密码1]
     * @param string $[password2]
     *            [密码2]
     * @return string $[msg] [绑定状态]
     */
    public function bindPasswd()
    {
        $uid = $this->get_login_uid();
        $pass1 = trim(I('pass1'));
        $pass2 = trim(I('pass2'));
        
        if ($pass1 == $pass2) {
            $reg = '/^[a-zA-Z]\w{6,10}$/';
            if (preg_match($reg, $pass1)) {
                $data = array(
                    'mobile' => M('user_mobile')->where("uid=$uid")->getField('mobile_phone'),
                    'password' => md5(md5($pass1) . $pass1)
                );
                $check = M('user_info')->where("id=$uid")->save($data);
                if ($check) {
                    $this->return_info(1, '绑定成功！！');
                } else {
                    $this->return_info(0, '绑定失败，请重试！');
                }
            } else {
                $this->return_info(0, '密码格式不正确！');
            }
        } else {
            $this->return_info(0, '两次密码输入不一致！');
        }
    }

    /**
     * ************* 短信验证码登录 *********************
     */
    
    /**
     * [mesLoginPic 验证码登录需要的图形验证码]
     * 
     * @return [type] [description]
     */
    public function mesLoginPic()
    {
        pic_Code('meslogin'); // 多业务模块注意命名重复问题
    }

    /**
     * [getLoginCode 获得短信登录验证码]
     * 
     * @return [type] [description]
     */
    public function getLoginCode()
    {
        $data = I('get.');
        
        if (! is_mobile($data['mobile']))
            $this->return_info(0, '手机号格式不正确！！');
        if (empty($data['code']) || ! isset($data['code']))
            $this->return_info(0, '验证码不能为空！');
        if (! checkCode($data['code'], 'meslogin', false))
            $this->return_info(2, '验证码输入错误！');
        $uid = M('user_info')->where("mobile = '%s'", [
            $data['mobile']
        ])->getField('id');
        if (empty($uid))
            $this->return_info(0, '该手机号还未注册，请注册！');
        
        $code = mt_rand(1000, 999999);
        $string = '您的验证码是：' . $code . ',请在5分钟内输入，请不要告知他人！';
        storageCode('meslogin', $data['mobile'], $code);
        $sendType = $this->sendMs($data['mobile'], $string);
        
        if ($sendType == 0) {
            $this->return_info(1, '发送成功，请注意查收！');
        } else {
            $this->return_info(0, '发送成功，请注意查收！');
        }
    }

    /**
     * [mesLogin 短信验证码登录]
     * 
     * @return [type] [description]
     */
    public function mesLogin()
    {
        $data = I('GET.');
        if (! is_mobile($data['mobile']))
            $this->return_info(0, '手机号格式不正确！！');
        if (empty($data['mes_code']) || ! isset($data['mes_code']))
            $this->return_info(0, '验证码不能为空！');
        if (! verificationCode('meslogin', $data['mobile'], $data['mes_code'])) {
            $this->return_info(0, '短信验证码输入错误！');
        }
        
        $uid = M('user_info')->where("mobile = '%s'", [
            $data['mobile']
        ])->getField('id');
        
        if (empty($uid)) {
            
            $this->return_info(0, '该手机号还未注册，请注册！');
        } else {
            
            $this->return_info(1, '登录成功！', $this->create_code($uid));
        }
    }

    /**
     * ********************* 短信重设密码 *************************
     */
    
    /**
     * [resetPwdPic 重设密码获取短信需要的图形验证码]
     * 
     * @return [type] [description]
     */
    public function resetPwdPic()
    {
        pic_Code('resetPwd');
    }

    /**
     * [getPwdCode 获取修改密码的短信验证码]
     * 
     * @return [type] [description]
     */
    public function getPwdCode()
    {
        
        // $data = $this->getPostData( false );
        $data = I('GET.');
        if (! is_mobile($data['mobile']))
            $this->return_info(0, '手机号格式不正确！！');
        if (empty($data['code']) || ! isset($data['code']))
            $this->return_info(0, '验证码不能为空！');
        if (! checkCode($data['code'], 'resetPwd', true))
            $this->return_info(2, '验证码输入错误！');
        $uid = M('user_info')->where("mobile = '%s'", [
            $data['mobile']
        ])->getField('id');
        if (empty($uid))
            $this->return_info(0, '该手机号还未注册，请注册！');
        
        $code = mt_rand(1000, 999999);
        $string = '您的验证码是：' . $code . ',请在5分钟内输入，请不要告知他人！';
        storageCode('resetPwd', $data['mobile'], $code, 2);
        $sendType = $this->sendMs($data['mobile'], $string);
        
        if ($sendType == 0) {
            $this->return_info(1, '发送成功，请注意查收！');
        } else {
            $this->return_info(0, '发送成功，请注意查收！');
        }
    }

    /**
     * [resetPwd 重设密码]
     * 
     * @return [type] [description]
     */
    public function resetPwd()
    {
        
        // $data = $this->getPostData( false );
        $data = I('GET.');
        $pwdone = trim($data['pwdone']);
        $pwdtwo = trim($data['pwdtwo']);
        
        if (! is_mobile($data['mobile']))
            $this->return_info(0, '手机号格式不正确！！');
        if (empty($data['mes_code']) || ! isset($data['mes_code']))
            $this->return_info(0, '短信验证码不能为空！');
        if ($pwdone != $pwdtwo)
            $this->return_info(0, '两次密码输入不一致！');
        if (! preg_match('/^[a-zA-Z]\w{6,10}$/', $pwdone))
            $this->return_info(0, '密码格式不正确！');
            
            // if( !verificationCode('resetPwd',$data['mobile'],$data['mes_code'],80,false) ) $this->return_info(0,'短信验证码输入错误！');
        
        $info = M('user_info')->where("mobile = '%s'", [
            $data['mobile']
        ])
            ->field('id,password')
            ->find();
        
        if (empty($info))
            $this->return_info(0, '该手机号还未注册，请注册！');
        
        if ($info['password'] == md5(md5($pwdone) . $pwdone))
            $this->return_info(0, '不可以与原密码相同！');
            
            // if( !verificationCode('resetPwd',$data['mobile'],$data['mes_code'],60,true) ) $this->return_info(0,'短信验证码输入错误！!');
        
        $check = M('user_info')->where("mobile = '%s'", [
            $data['mobile']
        ])->save([
            'password' => md5(md5($pwdone) . $pwdone)
        ]);
        
        if ($check) {
            $this->return_info(1, '修改成功！', $this->create_code($info['id']));
        } else {
            $this->return_info(0, '修改失败,请重试！');
        }
    }
}