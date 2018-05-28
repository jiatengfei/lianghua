<?php

// 调试工具
function sql()
{
    return M()->_sql();
}

/* 发送邮件类 */
function think_send_mail($to, $name, $subject = '', $body = '', $attachment = null)
{
    $config = C('THINK_EMAIL');
    vendor('PHPMailer.class#phpmailer'); // 从PHPMailer目录导class.phpmailer.php类文件
    vendor('SMTP');
    $mail = new PHPMailer(); // PHPMailer对象
    $mail->CharSet = 'UTF-8'; // 设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->IsSMTP(); // 设定使用SMTP服务
    $mail->SMTPDebug = 0; // 关闭SMTP调试功能
    $mail->SMTPAuth = true; // 启用 SMTP 验证功能
    $mail->SMTPSecure = 'ssl'; // 使用安全协议
    $mail->Host = $config['SMTP_HOST']; // SMTP 服务器
    $mail->Port = $config['SMTP_PORT']; // SMTP服务器的端口号
    $mail->Username = $config['SMTP_USER']; // SMTP服务器用户名
    $mail->Password = $config['SMTP_PASS']; // SMTP服务器密码
    $mail->SetFrom($config['FROM_EMAIL'], $config['FROM_NAME']);
    $replyEmail = $config['REPLY_EMAIL'] ? $config['REPLY_EMAIL'] : $config['FROM_EMAIL'];
    $replyName = $config['REPLY_NAME'] ? $config['REPLY_NAME'] : $config['FROM_NAME'];
    $mail->AddReplyTo($replyEmail, $replyName);
    $mail->Subject = $subject;
    $mail->AltBody = "为了查看该邮件，请切换到支持 HTML 的邮件客户端";
    $mail->MsgHTML($body);
    $mail->AddAddress($to, $name);
    if (is_array($attachment)) { // 添加附件
        foreach ($attachment as $file) {
            is_file($file) && $mail->AddAttachment($file);
        }
    }
    return $mail->Send() ? true : $mail->ErrorInfo;
}




     /*
     *  加密函数
     */
    function lock_url($txt,$key='weadmin.hxsmtrz.com')
    {
      $txt = $txt.$key;
      $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
      $nh = rand(0,64);
      $ch = $chars[$nh];
      $mdKey = md5($key.$ch);
      $mdKey = substr($mdKey,$nh%8, $nh%8+7);
      $txt = base64_encode($txt);
      $tmp = '';
      $i=0;$j=0;$k = 0;
      for ($i=0; $i<strlen($txt); $i++) {
        $k = $k == strlen($mdKey) ? 0 : $k;
        $j = ($nh+strpos($chars,$txt[$i])+ord($mdKey[$k++]))%64;
        $tmp .= $chars[$j];
      }
      return urlencode(base64_encode($ch.$tmp));
    }

    /*
     * 解密函数
     */
    function unlock_url($txt,$key='weadmin.hxsmtrz.com')
    {
      $txt = base64_decode(urldecode($txt));
      $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
      $ch = $txt[0];
      $nh = strpos($chars,$ch);
      $mdKey = md5($key.$ch);
      $mdKey = substr($mdKey,$nh%8, $nh%8+7);
      $txt = substr($txt,1);
      $tmp = '';
      $i=0;$j=0; $k = 0;
      for ($i=0; $i<strlen($txt); $i++) {
        $k = $k == strlen($mdKey) ? 0 : $k;
        $j = strpos($chars,$txt[$i])-$nh - ord($mdKey[$k++]);
        while ($j<0) $j+=64;
        $tmp .= $chars[$j];
      }
      return trim(base64_decode($tmp),$key);
    }





