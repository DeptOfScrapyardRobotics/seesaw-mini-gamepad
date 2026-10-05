<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use GeneralPurposeIO\Contracts\IntegratedCircuits\CircuitException;

class SeesawMiniGamepadException extends CircuitException
{
    public static function unknownHardware(int $hardware_id): static
    {
        return new static(sprintf('No seesaw answered: hardware ID 0x%02X is not a seesaw chip.', $hardware_id));
    }

    public static function unexpectedProduct(int $expected, int $actual): static
    {
        return new static("Expected seesaw product {$expected} (Mini Gamepad), got {$actual}.");
    }

    public static function invalidProperty(string $name, string $class): static
    {
        return new static("Invalid property '{$name}' on {$class}.");
    }

    public static function writeFailed(int $register, int $wanted, int $wrote): static
    {
        return new static(sprintf('Seesaw register 0x%04X: wanted to write %d bytes, wrote %d.', $register, $wanted, $wrote));
    }

    public static function readFailed(int $register, int $length): static
    {
        return new static(sprintf('Seesaw register 0x%04X: the bus refused a %d byte read.', $register, $length));
    }

    public static function shortRead(int $register, int $wanted, int $got): static
    {
        return new static(sprintf('Seesaw register 0x%04X: wanted %d bytes, got %d.', $register, $wanted, $got));
    }

    public static function notConnected(string $protocol, string $driver, string|int $device): static
    {
        return new static("Seesaw gamepad could not get a {$protocol} connection from driver [{$driver}] on device [{$device}].");
    }

    public static function invalidHoldTime(int $hold_ms): static
    {
        return new static("hold_ms takes 0 or more; got {$hold_ms}.");
    }
}
