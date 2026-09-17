<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;

/** One button's state as of the last poll. */
final class GamepadButtonState
{
    protected bool $down = false;

    protected bool $pressed = false;

    protected bool $released = false;

    protected ?int $down_since_ns = null;

    public function __construct(public readonly GamepadButton $button) {}

    /** Record this poll's level. pressed / released hold for exactly the poll that saw the change. */
    public function update(bool $down, int $at_ns): void
    {
        $this->pressed = $down && ! $this->down;
        $this->released = ! $down && $this->down;
        $this->down = $down;

        if ($this->pressed) {
            $this->down_since_ns = $at_ns;
        } elseif ($this->released) {
            $this->down_since_ns = null;
        }
    }

    public function isDown(): bool
    {
        return $this->down;
    }

    public function isPressed(): bool
    {
        return $this->pressed;
    }

    public function wasReleased(): bool
    {
        return $this->released;
    }

    public function isHolding(int $hold_ms): bool
    {
        return $this->down && $this->heldMs() >= $hold_ms;
    }

    /** How long the button has been down, 0 when up. */
    public function heldMs(?int $now_ns = null): int
    {
        return is_null($this->down_since_ns)
            ? 0
            : intdiv(($now_ns ?? hrtime(true)) - $this->down_since_ns, 1_000_000);
    }

    public function reset(): void
    {
        $this->down = false;
        $this->pressed = false;
        $this->released = false;
        $this->down_since_ns = null;
    }
}
