<?php
namespace Home\Controller;

// use Think\Controller;
class BController extends HomeController
{
    // public $var = 3;
    public function p()
    {
        $symbol = $_GET['inifile'];
        var_dump($symbol);
        print('1');
    }
}

#class A extends B{
#    public function p2() {
#        echo 'aa'.$this->var;
#        print('2');
#    }
#}
