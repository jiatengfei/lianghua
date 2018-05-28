<?php
namespace Home\Controller;

use Think\Controller;

class IndexController extends Controller
{

    public function index()
    {
        $name = $_REQUEST['name'];
        $str = "\Home\Controller\\" . $name;
        // var_dump($str);exit;
        // \Home\Controller\TradSimdController()
        $trad = new \Home\Controller\TradSimController(new $str());
        var_dump($trad);
        exit();
    }
}