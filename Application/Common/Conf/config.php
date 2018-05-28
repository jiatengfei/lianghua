<?php
$config = array(
    // '配置项'=>'配置值'
    'DEFAULT_FILTER' => 'htmlspecialchars',
    /* 数据库配置 */
    'DB_TYPE' => 'mysqli', // 数据库类型
    'DB_HOST' => 'localhost', // 服务器地址
    'DB_NAME' => 'cash', // 数据库名
    'DB_USER' => 'root', // 用户名
    'DB_PWD' => 'imexroot', // 密码
    'DB_PORT' => '3306', // 端口
    'DB_PREFIX' => '', // 数据库表前缀
    'AlidayuAppKey' => '24528410', //
    'AlidayuAppSecret' => '471a73da28a38343706d8041f53a0f74', //
    'AlidayuApiEnv' => true,
    'AlidayuTitle' => '微耳钱包', // 短信签名
    'Aliday_SERVER_PHONE' => '010-84845085', // 短息体中客服联系电话
    'Ali_LOGPATH' => '/Logs/Aliday/', // 错误日志路径
    'Ali_ERROR' => true, // 错误开关
    'Ali_OPEN_DAY' => '30', // 订单被拒绝的等待天数
    'DEFAULT_TIMEZONE'      => 'PRC',
    'SMS_PHONE' => 15210445038,
    'URL_MODEL' => 3,
    // ********************************不同币的配置***********************************
    'CURRENCY' => array(
        'htusdt' => array(
            'time' => '15min', // 曲线取值
            'num' => 1, // 操作数量
            'dotrade' => true
        ) // 自由交易 其他的严进宽出
,
        'btcusdt' => array(
            'time' => '15min', // 曲线取值
            'num' => 0.1
        ) // 操作数量
,
        'ethusdt' => array(
            'time' => '15min', // 曲线取值
            'num' => 1
        ) // 操作数量

    ),
    // ********************************发送邮件配置***********************************
    'THINK_EMAIL' => array(
        'SMTP_HOST' => 'SMTP.163.com', // SMTP服务器
        'SMTP_PORT' => '465', // SMTP服务器端口
        'SMTP_USER' => 'nudepeng@163.com', // SMTP服务器用户名
        'SMTP_PASS' => 'nanshen521', // SMTP服务器密码
        'FROM_EMAIL' => 'nudepeng@163.com', // 发件人EMAIL
        'FROM_NAME' => '微耳钱包贷款平台/客服', // 发件人名称
        'REPLY_EMAIL' => '', // 回复EMAIL（留空则为发件人EMAIL）
        'REPLY_NAME' => '', // 回复名称（留空则为发件人名称）
        'Email_title' => '微耳钱包贷款注册邮箱验证'
    ),
    'TMPL_CACHE_ON' => false,
    // ********************************发送邮件配置***********************************
    'THINK_EMAIL' => array(
        'SMTP_HOST' => 'SMTP.163.com', // SMTP服务器
        'SMTP_PORT' => '465', // SMTP服务器端口
        'SMTP_USER' => 'nudepeng@163.com', // SMTP服务器用户名
        'SMTP_PASS' => 'nanshen521', // SMTP服务器密码
        'FROM_EMAIL' => 'nudepeng@163.com', // 发件人EMAIL
        'FROM_NAME' => '客服', // 发件人名称
        'REPLY_EMAIL' => '', // 回复EMAIL（留空则为发件人EMAIL）
        'REPLY_NAME' => '', // 回复名称（留空则为发件人名称）
        'Email_title' => 'notice'
    ),
    'TMPL_CACHE_ON' => false,
    'CRONTAB_ERROR_PATH' => '/Logs/Crontab/',
    /**
     * ****** 数据列表每页显示的条数 ***************
     */
    'PAGE_SHOW_NUM' => 15
);

if ($_SERVER['HTTP_HOST'] == 'cb.sunwindy.com') {
    $config['DB_HOST'] = '127.0.0.1';
    $config['DB_NAME'] = 'cb';
    $config['DB_USER'] = 'tnb';
    $config['DB_PWD'] = 'vZu6upwm3vQAEmEm';
}
if ($_SERVER['HTTP_HOST'] == 'www.test.com') {
    $config['DB_HOST'] = '127.0.0.1';
    $config['DB_NAME'] = 'cb';
    $config['DB_USER'] = 'root';
    $config['DB_PWD'] = 'root';
}
return $config;
