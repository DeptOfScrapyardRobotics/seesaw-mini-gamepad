<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums;

enum SeesawCommand: string
{
    case STATUS_HARDWARE_ID = 'status.hardware_id';
    case STATUS_VERSION = 'status.version';
    case GPIO_DIRECTION_SET_BULK = 'gpio.direction_set_bulk';
    case GPIO_DIRECTION_CLEAR_BULK = 'gpio.direction_clear_bulk';
    case GPIO_BULK = 'gpio.bulk';
    case GPIO_BULK_SET = 'gpio.bulk_set';
    case GPIO_PULL_ENABLE_SET = 'gpio.pull_enable_set';
    case ADC_CHANNEL = 'adc.channel';

    public function module(): int
    {
        return match ($this) {
            self::STATUS_HARDWARE_ID, self::STATUS_VERSION => 0x00,
            self::GPIO_DIRECTION_SET_BULK,
            self::GPIO_DIRECTION_CLEAR_BULK,
            self::GPIO_BULK,
            self::GPIO_BULK_SET,
            self::GPIO_PULL_ENABLE_SET => 0x01,
            self::ADC_CHANNEL => 0x09,
        };
    }

    public function register(int $channel = 0): int
    {
        return match ($this) {
            self::STATUS_HARDWARE_ID => 0x01,
            self::STATUS_VERSION => 0x02,
            self::GPIO_DIRECTION_SET_BULK => 0x02,
            self::GPIO_DIRECTION_CLEAR_BULK => 0x03,
            self::GPIO_BULK => 0x04,
            self::GPIO_BULK_SET => 0x05,
            self::GPIO_PULL_ENABLE_SET => 0x0B,
            self::ADC_CHANNEL => 0x07 + $channel,
        };
    }
}
