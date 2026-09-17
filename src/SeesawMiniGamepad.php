<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Concerns\SeesawMiniGamepadBootstrap;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadAxis;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;
use GeneralPurposeIO\Contracts\IntegratedCircuits\Actuator;
use GeneralPurposeIO\IntegratedCircuits\Bootable;

/** Adafruit Mini I2C STEMMA QT Gamepad (product 5743): six buttons and a two-axis joystick on seesaw. */
class SeesawMiniGamepad extends Bootable implements Actuator
{
    use SeesawMiniGamepadBootstrap;

    protected int $product_id = 5743;

    protected int $adc_max = 1023;

    /** @var array<int, GamepadButtonState> keyed by GamepadButton value */
    protected array $states = [];

    /** @var array<int, float> keyed by GamepadAxis value */
    protected array $axis_values = [];

    public function __construct(
        protected readonly SeesawI2CTransport $transport,
        protected readonly SeesawMiniGamepadConfiguration $config = new SeesawMiniGamepadConfiguration,
        bool $boot_now = false,
    ) {
        foreach (GamepadButton::cases() as $button) {
            $this->states[$button->value] = new GamepadButtonState($button);
        }

        foreach (GamepadAxis::cases() as $axis) {
            $this->axis_values[$axis->value] = 0.0;
        }

        parent::__construct($boot_now);
    }

    public function transport(): SeesawI2CTransport
    {
        return $this->transport;
    }

    public function config(): SeesawMiniGamepadConfiguration
    {
        return $this->config;
    }

    public function connected(): bool
    {
        return $this->hasBooted();
    }

    /** Release IRQ and forget button state. The bus connection stays with its driver. */
    public function close(): void
    {
        foreach ($this->states as $state) {
            $state->reset();
        }

        $this->transport->close();
    }
}
