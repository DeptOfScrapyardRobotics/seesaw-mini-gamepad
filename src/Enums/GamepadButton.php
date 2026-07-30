<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums;

enum GamepadButton: string
{
    case A = 'a';
    case B = 'b';
    case X = 'x';
    case Y = 'y';
    case START = 'start';
    case SELECT = 'select';

    public function pin(): GamepadPin
    {
        return GamepadPin::from(match ($this) {
            self::A => GamepadPin::A->value,
            self::B => GamepadPin::B->value,
            self::X => GamepadPin::X->value,
            self::Y => GamepadPin::Y->value,
            self::START => GamepadPin::START->value,
            self::SELECT => GamepadPin::SELECT->value,
        });
    }
}
