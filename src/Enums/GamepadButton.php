<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums;

/** A face or menu button, backed by its seesaw GPIO pin. Pressed reads low. */
enum GamepadButton: int
{
    case SELECT = 0;
    case B = 1;
    case Y = 2;
    case A = 5;
    case X = 6;
    case START = 16;

    public function mask(): int
    {
        return 1 << $this->value;
    }

    public static function allMask(): int
    {
        return array_reduce(self::cases(), static fn (int $mask, self $button): int => $mask | $button->mask(), 0);
    }
}
