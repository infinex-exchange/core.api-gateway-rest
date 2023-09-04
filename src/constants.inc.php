<?php

$STREAMS = [
    'marketTrade' => [
        'public' => true,
        'arguments' => NULL,
        'bind' => [
            [
                'event' => 'trade',
                'res' => 'pairid'
            ]
        ]
    ],
    'candleStick' => [
        'public' => true,
        'arguments' => [
            '1' => [
                'view_suffix' => 'minute',
                'interval' => '1 minute'
            ],
            '60' => [
                'view_suffix' => 'hour',
                'interval' => '1 hour'
            ],
            '1D' => [
                'view_suffix' => 'day',
                'interval' => '1 day'
            ]
        ],
        'bind' => [
            [
                'event' => 'trade',
                'res' => 'pairid'
            ]
        ]
    ],
    'ticker' => [
        'public' => true,
        'arguments' => NULL,
        'bind' => [
            [
                'event' => 'aggTicker',
                'res' => 'pairid'
            ]
        ]
    ],
    'tickerEx' => [
        'public' => true,
        'arguments' => NULL,
        'bind' => [
            [
                'event' => 'aggTicker',
                'res' => 'pairid'
            ]
        ]
    ],
    'orderBook' => [
        'public' => true,
        'arguments' => NULL,
        'bind' => [
            [
                'event' => 'aggOrderbook',
                'res' => 'pairid'
            ]
        ]
    ],
    'myOrders' => [
        'public' => false,
        'arguments' => NULL,
        'bind' => [
            [
                'event' => 'orderAccepted',
                'res' => 'uid'
            ],
            [
                'event' => 'orderRejected',
                'res' => 'uid'
            ],
            [
                'event' => 'orderUpdate',
                'res' => 'uid'
            ],
            [
                'event' => 'orderCancelFailed',
                'res' => 'uid'
            ]
        ]
    ],
    'myTrades' => [
        'public' => false,
        'arguments' => NULL,
        'bind' => [
            [
                'event' => 'trade',
                'res' => 'maker_uid'
            ],
            [
                'event' => 'trade',
                'res' => 'taker_uid'
            ]
        ]
    ]
];

?>