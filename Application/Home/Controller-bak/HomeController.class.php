<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;
use Home\Controller\SmsController as Sms;
header("Content-Type:text/html; charset=utf-8");

/**
 * 前台公共控制器
 * 为防止多分组Controller名称冲突，公共Controller名称统一使用分组名称
 */
class HomeController extends Controller
{

    protected $user_info = null;

    protected $uid = null;
    
    // 芝麻信用网关地址
    public $gatewayUrl = 'https://zmopenapi.zmxy.com.cn/openapi.do';
    // 商户私钥文件
    public $privateKeyFile = 'Public/key/pri_key.pem';
    // 芝麻公钥文件
    public $zmPublicKeyFile = "Public/key/pub_key.pem";
    // 数据编码格式
    public $charset = "UTF-8";
    // 芝麻分配给商户的 appId
    public $appId = "300001633";
 // 微耳E工
    
    /* 空操作，用于输出404页面 */
    public function _empty()
    {
        $this->redirect('Index/index');
    }

    public function p($arr, $e = true)
    {
        echo '<pre>';
        var_export($arr);
        if ($e) {
            die();
        }
    }

    /**
     * 手机短信提醒
     */
    public function sms($num, $mes, $phone = FALSE)
    {
        // 尊敬的${name}，您的${money}已于${time}审核通过，到期时间${Ptime},到时${Pmoney}。
        $data = array(
            'name' => 'god',
            'money' => '操作',
            'time' => date('Y-m-d H:i:s', time()),
            'Ptime' => $num,
            'Pmoney' => $mes
        );
        if (! $phone) {
            $phone = C('sms_phone');
        }
        if (is_array($phone)) {
            foreach ($phone as $v) {
                Sms::sendSms('SMS_85650017', v, $data, C('AlidayuTitle'));
            }
        } else {
            Sms::sendSms('SMS_85650017', $phone, $data, C('AlidayuTitle'));
        }
    }
    
    // 获取账户余额
    public function get_balance($pair = 'htusdt')
    {
        $req = new req();
        $data = $req->get_balance();
        foreach ($data['data']['list'] as $v) {
            if ($v['currency'] == $pair && $v['type'] == 'trade') {
                return $v['balance'];
            }
        }
    }
    
    // Ajax通用返回方法
    public function return_info($status = 0, $msg = '', $data = array())
    {
        $this->ajaxReturn(array(
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ), 'JSON');
        exit();
    }
    
    // 全局检测是否登录方法
    protected function check_login()
    {
        if (! session('uid')) {
            $this->redirect('Ucenter/login');
        }
    }
    
    // 生成登录标识码
    public function create_code($uid)
    {
        $uniqidcode = uniqid(); // 生成唯一ID
        M('user_info')->where("id=$uid")->save(array(
            'uniqidcode' => $uniqidcode
        ));
        return $uniqidcode;
    }
    
    // 获取用户uid
    public function get_login_uid($uniqidcode = false, $is_back = true)
    {
        if ($uniqidcode === false) {
            $uniqidcode = I('uniqidcode') ? I('uniqidcode') : $_SERVER['HTTP_X_REQUESTED_WITH'];
        }
        if (empty($uniqidcode)) {
            if ($is_back) {
                $this->return_info(0, '请先登录!!');
            } else {
                return false;
            }
        }
        $uid = M('user_info')->where("uniqidcode='$uniqidcode'")->getField('id');
        
        if (! $uid) {
            
            if ($is_back) {
                $this->return_info(0, '请先登录');
            } else {
                return false;
            }
        }
        return $uid;
    }

    /**
     * [object_array 转换数组]
     * 
     * @param [type] $array
     *            [description]
     * @return [type] [description]
     */
    public function object_array($array)
    {
        if (is_object($array)) {
            $array = (array) $array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = $this->object_array($value);
            }
        }
        return $array;
    }

    /**
     * 下载文件到本地
     *
     * @param string $file_url            
     * @param string $path            
     * @param string $filename            
     */
    function dlFile($file_url, $path = 'Public/Uploads/common/', $filename = '')
    {
        // 生成目录
        $id = substr(time(), 1);
        $dir1 = substr($id, 0, 3);
        $dir2 = substr($id, 3, 3);
        $dir3 = substr($id, 6);
        $folder = $path . $dir1 . '/' . $dir2 . '/' . $dir3;
        $folder_arr = explode('/', $folder);
        $mkfolder = '';
        for ($i = 0; isset($folder_arr[$i]); $i ++) {
            $mkfolder .= $folder_arr[$i];
            if (! is_dir($mkfolder))
                mkdir("$mkfolder", 0777);
            $mkfolder .= '/';
        }
        empty($filename) && $filename = uniqid($id) . '.jpg';
        $save_to = $folder . '/' . $filename;
        $content = file_get_contents($file_url);
        if (! empty($content)) {
            file_put_contents($save_to, $content);
            return $save_to;
        } else {
            return '';
        }
    }
    
    // 获取用户登录状态uniqidcode
    protected function get_uniqidcode($uid)
    {
        $uid = M('user_info')->where('id=%d', array(
            $uid
        ))
            ->field('uniqidcode')
            ->find();
        if (empty($uid)) {
            return false;
        } else {
            return $uid['uniqidcode'];
        }
    }
    
    // 支持angularjs $http post 请求
    protected function getPostData($isuid = True)
    {
        // 接收原始请求的数据流
        $con = file_get_contents('php://input', true);
        // 转换接收的json数据
        $con = json_decode($con, true);
        // 请求中会含有uniqidcode参数
        if ($isuid) {
            $con['uid'] = $this->get_login_uid($con['uniqidcode']);
        }
        
        return $con;
    }

    /**
     * [sendMs 秒赛短信平台发送方法【支持任意模板/支持群发/群发推送消息体一致，手机号Array形式传入】]
     * 
     * @param [String/Array] $phone
     *            [目标手机号]
     * @param [type] $content
     *            [短信内容]
     * @param
     *            [boolen]$return [状态返回]
     * @return [type] [description]
     */
    protected function sendMs($phone, $content, $return = False)
    {
        $url = 'http://114.55.176.84/msg/HttpBatchSendSM';
        $request = array(
            'account' => 'ms-weier',
            'pswd' => md5('ms-weierMSweier0929' . date('YmdHis')),
            'resptype' => 'json',
            'ts' => date('YmdHis'),
            'mobile' => is_array($phone) ? implode(',', $phone) : $phone,
            'msg' => '【微耳e工】' . $content,
            'needstatus' => $return
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request));
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        if ($response === FALSE) {
            curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        $reback = json_decode($response, true);
        return $reback['result'];
    }

    /**
     * [commonUpload 公共上传文件]
     * 
     * @param [string] $savename
     *            [命名规则]
     * @param [string] $path
     *            [相对于uploads下的下级目录名称]
     * @param
     *            [boolean]$is_str [规定返回值数组或字符串文件路径]
     * @return [type] [文件的上传信息,数组或字符串]
     */
    protected function commonUpload($savename, $path, $is_str = true)
    {
        $upload = new \Think\Upload(); // 实例化上传类
        $upload->maxSize = 314572800000000; // 设置附件上传大小
        $upload->exts = array(
            'jpg',
            'gif',
            'png',
            'jpeg',
            'wmv'
        ); // 设置附件上传类型
        $upload->rootPath = 'Public/'; // 设置附件上传根目录
        $upload->savePath = 'Uploads/'; // 设置附件上传（子）目录
        $upload->saveName = array(
            'uniqid',
            $savename
        );
        $upload->autoSub = true;
        $upload->subName = $path . '/' . date('YmdH');
        
        $info = $upload->upload();
        $err = $upload->getError();
        if (! empty($err)) {
            
            $this->return_info(0, $upload->getError()); // 上传错误提示错误信息
        } else {
            
            $img_path = '';
            foreach ($info as $key => $val) {
                
                $img_path .= $val['savepath'] . $val['savename'] . '|';
            }
        }
        
        if ($is_str) {
            return rtrim($img_path, '|');
        } else {
            return $info;
        }
    }

    /**
     * [logWirte 日志记录]
     * 
     * @param [type] $Astring
     *            [description]
     * @param string $type
     *            [日志类型，取执行方法名]
     * @return [type] [抬头]
     */
    protected function logWirte($Astring, $type = 'Error', $string = '')
    {
        $path = './Public';
        $path = $path . C('CRONTAB_ERROR_PATH') . $type . '/' . date('Y') . '/' . date('m') . '/';
        $file = $path . date('d') . ".txt";
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        } // 递归创建文件
        
        $LogTime = date('Y-m-d H:i:s', time());
        $con = '*******' . $string . '*******' . "\r\n";
        if (! file_exists($file)) {
            $logfile = fopen($file, "w");
            fwrite($logfile, "[$LogTime]" . $con . $Astring . "\r\n");
            fclose($logfile);
        } else {
            $logfile = fopen($file, "a");
            fwrite($logfile, "[$LogTime]" . $con . $Astring . "\r\n");
            fclose($logfile);
        }
    }

    /**
     * [userMoney 返回用户账户余额]
     * 
     * @return [type] [description]
     */
    protected function userMoney($uid)
    {
        $user_money = M('user_money');
        $money = $user_money->where("uid = {$uid}")->getField('money');
        if (is_null($money)) {
            $user_money->data(array(
                'uid' => $uid,
                'money' => 0
            ))->add();
            return 0;
        } else {
            return $money;
        }
    }

    /**
     * [getName 返回用户基本信息]
     * 
     * @return [type] [姓名。身份证号。]
     */
    protected function getUserinfo($uid)
    {
        return M('user_info')->where("id = {$uid}")
            ->field('user_name,user_idcard')
            ->find();
    }

    /**
     * [notice 异常记录]
     * 
     * @param [type] $string
     *            [内容]
     * @return [type] [description]
     */
    protected function notice($string)
    {
        $text = get_client_ip() . "\r\n" . $string;
        return M('notice')->data(array(
            'text' => $text,
            'ktime' => time()
        ))->add();
    }
}
