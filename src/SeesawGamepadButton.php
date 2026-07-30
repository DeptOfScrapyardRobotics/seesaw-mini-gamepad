<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use Fabricate\Contracts\Actuation\Interfaces\Button;

class SeesawGamepadButton implements Button
{
    protected bool $down = false;
    protected bool $pressed = false;
    protected bool $released = false;
    protected ?int $down_since_ns = null;
    protected int $hold_ms = 500;

    /** @var list<array{down: bool, pressed: bool, released: bool, holding: bool, at_ns: int}> */
    protected array $history = [];

    public function __construct(protected readonly string $button_label) {}

    public function update(bool $down, ?int $at_ns = null): void
    {
        $at_ns ??= hrtime(true);
        $this->pressed = $down && ! $this->down;
        $this->released = ! $down && $this->down;
        $this->down = $down;

        if ($this->pressed) {
            $this->down_since_ns = $at_ns;
        } elseif ($this->released) {
            $this->down_since_ns = null;
        }

        $this->history[] = [
            'down' => $this->down,
            'pressed' => $this->pressed,
            'released' => $this->released,
            'holding' => $this->isHolding(),
            'at_ns' => $at_ns,
        ];
    }

    public function label(): string
    {
        return $this->button_label;
    }

    public function poll(): static
    {
        return $this;
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

    public function isHolding(): bool
    {
        return $this->down && $this->heldMs() >= $this->hold_ms;
    }

    public function heldMs(): int
    {
        return is_null($this->down_since_ns)
            ? 0
            : (int) ((hrtime(true) - $this->down_since_ns) / 1_000_000);
    }

    public function holdMs(): int
    {
        return $this->hold_ms;
    }

    public function setHoldMs(int $hold_ms): static
    {
        $this->hold_ms = max(0, $hold_ms);

        return $this;
    }

    public function history(): array
    {
        return $this->history;
    }

    public function clearHistory(): static
    {
        $this->history = [];

        return $this;
    }

    public function close(): void
    {
        $this->down = false;
        $this->pressed = false;
        $this->released = false;
        $this->down_since_ns = null;
    }
}
