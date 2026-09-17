<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

/** The gamepad's settings. Nothing here is read back from the chip. */
class SeesawMiniGamepadConfiguration
{
    /**
     * @param  int  $hold_ms  how long a button stays down before it counts as holding
     * @param  bool  $invert_x  flip the X axis; on, right reads +1.0
     * @param  bool  $invert_y  flip the Y axis; off, up reads -1.0
     * @param  bool  $button_interrupts  have seesaw pull IRQ low when a button changes
     * @param  int  $reset_wait_ms  wait after the boot software reset
     */
    public function __construct(
        protected int $hold_ms = 500,
        protected bool $invert_x = true,
        protected bool $invert_y = false,
        protected bool $button_interrupts = false,
        protected int $reset_wait_ms = 500,
    ) {}

    public function get(string $var): mixed
    {
        if (isset($this->$var)) {
            return $this->$var;
        }

        throw SeesawMiniGamepadException::invalidProperty($var, static::class);
    }

    public function set(string $var, mixed $value): void
    {
        if (isset($this->$var)) {
            $this->$var = $value;

            return;
        }

        throw SeesawMiniGamepadException::invalidProperty($var, static::class);
    }
}
