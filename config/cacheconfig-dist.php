<?php

return [
    'phpthread' => [
        'instance' => '\\ByJG\\Cache\\ShmopCacheEngine',
        'shmop' => [
            'max-size' => 0x100000,
            'default-permission' => '0700'
        ]
    ]
];
