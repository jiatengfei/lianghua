<?php
namespace Alidayu;

/**
 * [log 宝付 错误记录方法]
 * 
 * @param [type] $Astring
 *            [description]
 * @return [type] [description]
 */
class Log
{

    public static function logWirte($Astring, $type = 'Error')
    {
        // 检测是否开启错误记录 正常交易记录不限制
        if (! C('Ali_ERROR') && $type == 'Error') {
            return false;
        }
        $path = $_SERVER['DOCUMENT_ROOT'];
        $path = $path . C('Ali_LOGPATH') . $type . '/';
        $file = $path . "log" . date('Ymd', time()) . ".txt";
        
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        } // 递归创建文件
        
        $LogTime = date('Y-m-d H:i:s', time());
        $con = '********************  A l D Y   *******************' . "\r\n";
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
}