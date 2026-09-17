<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Concerns;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawHardwareId;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException;

trait SeesawMiniGamepadBootstrap
{
    use SeesawMiniGamepadAPI;

    /**
     * @throws SeesawMiniGamepadException
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'hardware_id' => $this->getHardwareId(),
            'version' => $this->getVersion(),
            'product_id' => $this->getProductId(),
            'hold_ms' => $this->getHoldMs(),
            'invert_x' => $this->getInvertX(),
            'invert_y' => $this->getInvertY(),
            'button_interrupts' => $this->getButtonInterrupts(),
            'x' => $this->x(),
            'y' => $this->y(),
            'axes' => $this->axes(),
            default => throw SeesawMiniGamepadException::invalidProperty($name, static::class),
        };
    }

    /**
     * @throws SeesawMiniGamepadException
     */
    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'hold_ms' => $this->setHoldMs($value),
            'invert_x' => $this->setInvertX($value),
            'invert_y' => $this->setInvertY($value),
            'button_interrupts' => $this->setButtonInterrupts($value),
            default => throw SeesawMiniGamepadException::invalidProperty($name, static::class),
        };
    }

    /** Adafruit seesaw begin(): reset, identify, then the gamepad's pins. */
    protected function _boot(): void
    {
        $this->softwareReset();
        $this->confirmHardware();
        $this->confirmProduct();
        $this->setButtonPullups(GamepadButton::allMask());
        $this->setButtonInterrupts($this->config()->get('button_interrupts'));

        foreach ($this->states as $state) {
            $state->reset();
        }

        $this->primed = false;
    }

    protected function confirmHardware(): void
    {
        $hardware_id = $this->getHardwareId();

        if (is_null(SeesawHardwareId::tryFrom($hardware_id))) {
            throw SeesawMiniGamepadException::unknownHardware($hardware_id);
        }
    }

    protected function confirmProduct(): void
    {
        $product_id = $this->getProductId();

        if ($product_id !== $this->product_id) {
            throw SeesawMiniGamepadException::unexpectedProduct($this->product_id, $product_id);
        }
    }
}
