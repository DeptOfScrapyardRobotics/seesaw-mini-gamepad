<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawMiniGamepadI2CAddress;

return [
    'default_config' => 'i2c',
    'configs' => [
        'i2c' => [
            'driver' => 'none',
            'device' => '',
            'slave' => SeesawMiniGamepadI2CAddress::DEFAULT->value,
            'irq' => [
                'enabled' => false,
                'driver' => 'none',
                'device' => '',
                'pin' => 0,
            ],
        ],
    ],
];
