<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use RuntimeException;

class SeesawMiniGamepadException extends RuntimeException
{
    public static function i2cReadFailed(int $expected_bytes): static
    {
        return new static("Seesaw I2C read failed; expected {$expected_bytes} bytes.");
    }

    public static function i2cWriteFailed(int $expected_bytes, int $written_bytes): static
    {
        return new static("Seesaw I2C write failed; expected {$expected_bytes} bytes, wrote {$written_bytes}.");
    }

    public static function unexpectedProduct(int $expected, int $actual): static
    {
        return new static("Expected seesaw product PID {$expected}, received {$actual}.");
    }

    public static function unknownButton(string $label): static
    {
        return new static("Unknown Mini Gamepad button [{$label}].");
    }
}
