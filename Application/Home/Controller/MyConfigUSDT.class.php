<?php
namespace Home\Controller;

class MyConfigUSDT
{

    public $system = [
        'startTime' => "2018/03/25 00:00:01",
        "endTime" => "2018/03/30 10:00:59",
        "jumpTime" => '900',
        'is_live' => FALSE,
        'base' => 'USDT',
        'back_segs' => 255,
        'platform' => 'huobi',
        'timeZone' => 8
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
