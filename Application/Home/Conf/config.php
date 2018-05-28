<?php
return array(
    // '配置项'=>'配置值'
    'HT' => [
        'USDT'
    ],
//     'USDT' => [
//         'HT',
//         'BTC',
//         'ETH',
//         'XRP',
//         'DASH',
//         'EOS'
//     ],
    'USDT' => [
        'HT'
    ],
//     'BTC' => [
//         'USDT',
//         'HT',
//         'ETH',
//         'XRP',
//         'DASH',
//         'EOS'
//     ],
    'BTC' => [
        'HT',
    ],
    'ETH' => [
        'USDT',
        'HT',
        'BTC',
        'EOS'
    ],
    'XRP' => [
        'USDT',
        'BTC'
    ],
    'DASH' => [
        'USDT',
        'BTC'
    ],
    'EOS' => [
        'USDT',
        'BTC'
    ],
    'timeType' => [
        '60' => '1min',
        '300' => '5min',
        '900' => '15min',
        '1800' => '30min',
        '3600' => '60min'
    ],
    'config' => "\Home\Controller\\",
    'huobiCrontab' => "CrontabController"
);
