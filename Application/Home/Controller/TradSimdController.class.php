<?php
namespace Home\Controller;

class TradSimdController
{

    public $system = [
        'startTime' => "2018/03/25 00:00:00",
        "endTime" => "2018/03/27 23:59:59",
        "jumpTime" => '900',
        'is_live' => FALSE,
        'back_segs' => 50
    ];

    public $strategy = [
        'mytrad1' => [
            'class' => 'MyTradeController',
            'params' => [
                'r_time' => '15min',
                'base' => 'HT',
                'money' => 10000
            ]
        ]
    ];
}
