<?php
namespace Home\Controller;

class MyConfig
{

    public $system = [
        'startTime' => "2018/03/25 00:00:01",
        "endTime" => "2018/03/27 20:59:59",
        "jumpTime" => '900',
        'is_live' => FALSE,
        'back_segs' => 255,
        'base' => 'HT',
        'platform' => 'huobi'
    ];

    public $strategy = [
        'mytrad1' => [
            'class' => 'MyTradeController',
            'params' => [
                'r_time' => '15min',
                'money' => 10000
            ]
        ]
    ];
    
    public $orderStra = [
        'class' => 'PlaceOrderController',
        'params' => [
            
        ]
    ];
}
