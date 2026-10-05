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
            'hold_ms' => null,            // null keeps 500
            'invert_x' => null,           // null keeps true: right reads +1.0
            'invert_y' => null,           // null keeps false: up reads -1.0
            'button_interrupts' => null,  // null keeps false
            'reset_wait_ms' => null,      // null keeps 500
        ],
    ],
];
