<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Concerns;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadAxis;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawOpCode;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\GamepadButtonState;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadConfiguration;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;
use GeneralPurposeIO\Contracts\Digital\DigitalInTransport;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\LoopResources\Timer;

trait SeesawMiniGamepadAPI
{
    /** false until the first poll after boot has read the buttons from the chip. */
    protected bool $primed = false;

    abstract public function transport(): SeesawI2CTransport;

    abstract public function config(): SeesawMiniGamepadConfiguration;

    protected function sendCommand(SeesawOpCode $register, array $command_data = []): int
    {
        return $this->transport()->write($register->value, $command_data);
    }

    protected function readData(SeesawOpCode $register, int $length, int $delay_us = 250): array
    {
        return $this->transport()->read($register->value, $length, $delay_us);
    }

    protected function readUint32(SeesawOpCode $register): int
    {
        [$b3, $b2, $b1, $b0] = $this->readData($register, 4);

        return ($b3 << 24) | ($b2 << 16) | ($b1 << 8) | $b0;
    }

    /** @return list<int> big-endian */
    protected function uint32Bytes(int $value): array
    {
        return [($value >> 24) & 0xFF, ($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    // --- identity ------------------------------------------------------------

    public function getHardwareId(): int
    {
        return $this->readData(SeesawOpCode::STATUS_HW_ID, 1)[0];
    }

    /** Product ID in the high 16 bits, firmware date code in the low 16. */
    public function getVersion(): int
    {
        return $this->readUint32(SeesawOpCode::STATUS_VERSION);
    }

    public function getProductId(): int
    {
        return ($this->getVersion() >> 16) & 0xFFFF;
    }

    /**
     * Restart the seesaw firmware, then wait reset_wait_ms. Clears pull-ups and interrupts. The firmware restarts
     * on the reset byte before acknowledging it, so the write reads as refused; Adafruit's begin() ignores it too.
     */
    public function softwareReset(): void
    {
        try {
            $this->sendCommand(SeesawOpCode::STATUS_SWRST, [0xFF]);
        } catch (SeesawMiniGamepadException) {
        }

        usleep($this->config()->get('reset_wait_ms') * 1_000);
    }

    // --- settings ------------------------------------------------------------

    /** Input with pull-up on every pin in the mask. */
    public function setButtonPullups(int $mask): void
    {
        $bytes = $this->uint32Bytes($mask);
        $this->sendCommand(SeesawOpCode::GPIO_DIRCLR_BULK, $bytes);
        $this->sendCommand(SeesawOpCode::GPIO_PULLENSET, $bytes);
        $this->sendCommand(SeesawOpCode::GPIO_BULK_SET, $bytes);
    }

    public function getButtonInterrupts(): bool
    {
        return $this->config()->get('button_interrupts');
    }

    /** Have seesaw pull IRQ low when any button changes, or stop it. */
    public function setButtonInterrupts(bool $enabled): void
    {
        $register = $enabled ? SeesawOpCode::GPIO_INTENSET : SeesawOpCode::GPIO_INTENCLR;
        $this->sendCommand($register, $this->uint32Bytes(GamepadButton::allMask()));
        $this->config()->set('button_interrupts', $enabled);
    }

    public function getHoldMs(): int
    {
        return $this->config()->get('hold_ms');
    }

    public function setHoldMs(int $hold_ms): void
    {
        if ($hold_ms < 0) {
            throw SeesawMiniGamepadException::invalidHoldTime($hold_ms);
        }

        $this->config()->set('hold_ms', $hold_ms);
    }

    public function getInvertX(): bool
    {
        return $this->config()->get('invert_x');
    }

    public function setInvertX(bool $invert): void
    {
        $this->config()->set('invert_x', $invert);
    }

    public function getInvertY(): bool
    {
        return $this->config()->get('invert_y');
    }

    public function setInvertY(bool $invert): void
    {
        $this->config()->set('invert_y', $invert);
    }

    // --- raw reads -----------------------------------------------------------

    /** Every GPIO level at once. A pressed button reads 0. */
    public function readGPIO(): int
    {
        return $this->readUint32(SeesawOpCode::GPIO_BULK);
    }

    /** Which pins changed since the last call; reading clears them and releases IRQ. */
    public function readInterruptFlags(): int
    {
        return $this->readUint32(SeesawOpCode::GPIO_INTFLAG);
    }

    /** 10-bit ADC count, 0 to 1023; the stick rests near the middle. */
    public function readAxisRaw(GamepadAxis $axis): int
    {
        [$high, $low] = $this->transport()->read(SeesawOpCode::ADC_CHANNEL_OFFSET->value + $axis->value, 2, 500);

        return (($high << 8) | $low) & 0xFFFF;
    }

    // --- polling -------------------------------------------------------------

    /**
     * Read the buttons and both axes once. With IRQ wired and button
     * interrupts on, an idle IRQ skips the button read.
     */
    public function poll(): static
    {
        $snapshot = $this->readButtons();
        $at_ns = hrtime(true);

        foreach ($this->states as $state) {
            $down = is_null($snapshot) ? $state->isDown() : ($snapshot & $state->button->mask()) === 0;
            $state->update($down, $at_ns);
        }

        foreach (GamepadAxis::cases() as $axis) {
            $this->axis_values[$axis->value] = $this->normalize($this->readAxisRaw($axis), $this->axisInverted($axis));
        }

        return $this;
    }

    /** poll() every $interval_s seconds on the event loop, under $name. */
    public function every(Loop $loop, float $interval_s = 0.01, string $name = 'seesaw-mini-gamepad'): Timer
    {
        return $loop->every($interval_s, fn (): static => $this->poll(), $name);
    }

    /** Take the every() timer named $name off the loop. */
    public function stop(Loop $loop, string $name = 'seesaw-mini-gamepad'): void
    {
        $loop->forget($name);
    }

    /** IRQ's input when it is wired and button interrupts are on; null means read the buttons every poll. */
    public function interruptLine(): ?DigitalInTransport
    {
        $line = $this->transport()->interruptPin();

        return is_null($line) || ! $this->config()->get('button_interrupts') ? null : $line;
    }

    /** The GPIO snapshot, or null when IRQ says nothing changed. */
    protected function readButtons(): ?int
    {
        $line = $this->interruptLine();

        if (! is_null($line) && $this->primed) {
            if ($line->read()) {
                return null;
            }

            $this->readInterruptFlags();
        }

        $this->primed = true;

        return $this->readGPIO();
    }

    protected function normalize(int $raw, bool $invert): float
    {
        $value = max(-1.0, min(1.0, ($raw / $this->adc_max) * 2.0 - 1.0));

        return $invert ? -$value : $value;
    }

    protected function axisInverted(GamepadAxis $axis): bool
    {
        return match ($axis) {
            GamepadAxis::X => $this->config()->get('invert_x'),
            GamepadAxis::Y => $this->config()->get('invert_y'),
        };
    }

    // --- state from the last poll -------------------------------------------

    public function button(GamepadButton $button): GamepadButtonState
    {
        return $this->states[$button->value];
    }

    /** @return array<string, GamepadButtonState> keyed by button name */
    public function buttons(): array
    {
        $out = [];

        foreach ($this->states as $state) {
            $out[$state->button->name] = $state;
        }

        return $out;
    }

    public function isDown(GamepadButton $button): bool
    {
        return $this->button($button)->isDown();
    }

    public function isPressed(GamepadButton $button): bool
    {
        return $this->button($button)->isPressed();
    }

    public function wasReleased(GamepadButton $button): bool
    {
        return $this->button($button)->wasReleased();
    }

    /** With $hold_ms given, compare it against heldMs(); otherwise use the configured threshold. */
    public function isHolding(GamepadButton $button, ?int $hold_ms = null): bool
    {
        if (! is_null($hold_ms)) {
            return $this->isDown($button) && $this->heldMs($button) >= $hold_ms;
        }

        return $this->button($button)->isHolding($this->config()->get('hold_ms'));
    }

    public function heldMs(GamepadButton $button): int
    {
        return $this->button($button)->heldMs();
    }

    /** @return list<GamepadButton> */
    public function downButtons(): array
    {
        return $this->filterButtons(fn (GamepadButton $b): bool => $this->isDown($b));
    }

    /** @return list<GamepadButton> */
    public function pressedButtons(): array
    {
        return $this->filterButtons(fn (GamepadButton $b): bool => $this->isPressed($b));
    }

    /** @return list<GamepadButton> */
    public function releasedButtons(): array
    {
        return $this->filterButtons(fn (GamepadButton $b): bool => $this->wasReleased($b));
    }

    /** @return list<GamepadButton> */
    public function holdingButtons(): array
    {
        return $this->filterButtons(fn (GamepadButton $b): bool => $this->isHolding($b));
    }

    /** Any of these down; no buttons means any button. */
    public function anyDown(GamepadButton ...$buttons): bool
    {
        return array_any($buttons ?: GamepadButton::cases(), fn (GamepadButton $b): bool => $this->isDown($b));
    }

    /** All of these down; no buttons means every button. */
    public function allDown(GamepadButton ...$buttons): bool
    {
        return array_all($buttons ?: GamepadButton::cases(), fn (GamepadButton $b): bool => $this->isDown($b));
    }

    public function chord(GamepadButton ...$buttons): bool
    {
        return $this->allDown(...$buttons);
    }

    /** Any of these pressed this poll; no buttons means any button. */
    public function anyPressed(GamepadButton ...$buttons): bool
    {
        return array_any($buttons ?: GamepadButton::cases(), fn (GamepadButton $b): bool => $this->isPressed($b));
    }

    /** -1.0 to 1.0 as of the last poll. */
    public function axis(GamepadAxis $axis): float
    {
        return $this->axis_values[$axis->value];
    }

    public function x(): float
    {
        return $this->axis(GamepadAxis::X);
    }

    public function y(): float
    {
        return $this->axis(GamepadAxis::Y);
    }

    /** @return array{x: float, y: float} */
    public function axes(): array
    {
        return ['x' => $this->x(), 'y' => $this->y()];
    }

    /** @return list<GamepadButton> */
    protected function filterButtons(callable $keep): array
    {
        return array_values(array_filter(GamepadButton::cases(), $keep));
    }
}
