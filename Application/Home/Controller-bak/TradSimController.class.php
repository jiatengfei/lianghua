<?php
namespace Home\Controller;

use Think\Controller;
use JYS\huobi\req;

class TradSimController extends HomeController
{

    public $startTime;

    public $endTime;

    public $jumpTime;

    public $obj_arr = [];

    public function index()
    {
        $name = $_REQUEST['name'];
        $str = C('config') . $name;
        // var_dump($str);exit;
        // \Home\Controller\TradSimdController()
        // $trad = new \Home\Controller\TradSimController(new $str);
        $obj = new $str();
        
        // 声明新的对象
        $system = $obj->system;
        $strategv = $obj->strategy;
        $config_str = C('config');
        foreach ($strategv as $v) {
            $obj_mytrade = $config_str . $v['class'];
            $this->obj_arr[] = new $obj_mytrade($system, $v['params']);
        }
        $this->startTime = strtotime($obj->system['startTime']) * 1000;
        $this->endTime = strtotime($obj->system['endTime']) * 1000;
        $this->jumpTime = $obj->system['jumpTime'] * 1000;
        $this->sim();
        // var_dump($trad);exit;
    }
    
    // 模拟交易
    public function sim()
    {
        for ($i = $this->startTime; $i < $this->endTime;) {
            // 调用getpos函数
            var_dump($i);
            foreach ($this->obj_arr as $v) {
                $v->prepareData($i);
                $v->prepareDepData($i);
                var_dump('prepare over');
                $v->objPos = $v->holdPos;
                var_dump('objpos over');
                $v->getPos($i);
                var_dump($v->objPos);
                exit();
            }
            // 修改当前持有量
            
            $i += $this->jumpTime;
        }
    }
}
