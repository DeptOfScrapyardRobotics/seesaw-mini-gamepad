<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadPin;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadSpecification;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawPinMode;
use Fabricate\Contracts\Actuation\HumanInput\GameController;
use Fabricate\Contracts\Actuation\HumanInput\GameControllerAxis;
use Fabricate\Contracts\Actuation\HumanInput\GameControllerButton;
use Fabricate\Contracts\Actuation\Interfaces\Button;
use Fabricate\Contracts\Circuits\Attributes\IntegratedCircuit;
use Fabricate\Contracts\NutsAndBolts\BootSequence;
use GeneralPurposeIO\I2C\I2C;
use GeneralPurposeIO\I2C\I2CSlave;

#[IntegratedCircuit('I2C')]
class SeesawMiniGamepad implements GameController, BootSequence
{
    /** @var array<string, SeesawGamepadButton> */
    protected array $button_states = [];

    /** @var array<string, float> */
    protected array $axis_states = [];

    protected bool $booted = false;

    public function __construct(
        protected readonly SeesawClient $seesaw,
        bool $boot_now = false,
    ) {
        foreach (GamepadButton::cases() as $button) {
            $this->button_states[$button->value] = new SeesawGamepadButton($button->value);
        }

        foreach (GameControllerAxis::cases() as $axis) {
            $this->axis_states[$axis->value] = 0.0;
        }

        if ($boot_now) {
            $this->boot();
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $status = $this->seesaw->status();
        $product_id = $status['product_id'];

        if ($product_id !== GamepadSpecification::PRODUCT_ID->value) {
            throw SeesawMiniGamepadException::unexpectedProduct(
                GamepadSpecification::PRODUCT_ID->value,
                $product_id,
            );
        }

        $this->seesaw->pinModeBulk($this->buttonMask(), SeesawPinMode::INPUT_PULLUP);
        $this->booted = true;
    }

    public function hasBooted(): bool
    {
        return $this->booted;
    }

    public function connected(): bool
    {
        return $this->booted;
    }

    public function poll(): static
    {
        if (! $this->booted) {
            $this->boot();
        }

        $snapshot = $this->seesaw->digitalReadBulk($this->buttonMask());
        $at_ns = hrtime(true);

        foreach (GamepadButton::cases() as $button) {
            $down = ($snapshot & (1 << $button->pin()->value)) === 0;
            $this->button_states[$button->value]->update($down, $at_ns);
        }

        $this->axis_states[GameControllerAxis::LEFT_X->value] = $this->normalizedJoystick(
            $this->seesaw->analogRead(GamepadPin::JOYSTICK_X->value),
            invert: true,
        );
        $this->axis_states[GameControllerAxis::LEFT_Y->value] = $this->normalizedJoystick(
            $this->seesaw->analogRead(GamepadPin::JOYSTICK_Y->value),
            invert: false,
        );

        return $this;
    }

    /** @return array<string, Button> */
    public function buttons(): array
    {
        return $this->button_states;
    }

    /** @return list<string> */
    public function labels(): array
    {
        return array_keys($this->button_states);
    }

    public function button(string $label): Button
    {
        $label = $this->physicalButtonLabel($label);

        if (! isset($this->button_states[$label])) {
            throw SeesawMiniGamepadException::unknownButton($label);
        }

        return $this->button_states[$label];
    }

    public function has(string $label): bool
    {
        return isset($this->button_states[$this->physicalButtonLabel($label)]);
    }

    public function isDown(string $label): bool
    {
        return $this->button($label)->isDown();
    }

    public function isPressed(string $label): bool
    {
        return $this->button($label)->isPressed();
    }

    public function wasReleased(string $label): bool
    {
        return $this->button($label)->wasReleased();
    }

    public function isHolding(string $label): bool
    {
        return $this->button($label)->isHolding();
    }

    /** @return list<string> */
    public function downLabels(): array
    {
        return array_values(array_filter($this->labels(), fn (string $label): bool => $this->isDown($label)));
    }

    /** @return list<string> */
    public function pressedLabels(): array
    {
        return array_values(array_filter($this->labels(), fn (string $label): bool => $this->isPressed($label)));
    }

    /** @return list<string> */
    public function holdingLabels(): array
    {
        return array_values(array_filter($this->labels(), fn (string $label): bool => $this->isHolding($label)));
    }

    public function anyDown(string ...$labels): bool
    {
        $labels = $labels === [] ? $this->labels() : $labels;

        return array_any($labels, fn (string $label): bool => $this->isDown($label));
    }

    public function allDown(string ...$labels): bool
    {
        $labels = $labels === [] ? $this->labels() : $labels;

        return $labels !== [] && array_all($labels, fn (string $label): bool => $this->isDown($label));
    }

    public function chord(string ...$labels): bool
    {
        return $this->allDown(...$labels);
    }

    public function anyPressed(string ...$labels): bool
    {
        $labels = $labels === [] ? $this->labels() : $labels;

        return array_any($labels, fn (string $label): bool => $this->isPressed($label));
    }

    public function axis(GameControllerAxis $axis): float
    {
        return $this->axis_states[$axis->value];
    }

    /** @return array<string, float> */
    public function axes(): array
    {
        return $this->axis_states;
    }

    public function close(): void
    {
        foreach ($this->button_states as $button) {
            $button->close();
        }

        $this->seesaw->close();
        $this->booted = false;
    }

    public static function i2c(
        string|int $device,
        ?string $adapter = null,
        int $slave = GamepadSpecification::DEFAULT_ADDRESS->value,
        bool $boot_now = true,
    ): static {
        $i2c = I2C::adapter($adapter)
            ->device($device)
            ->bus()
            ->slave($slave);

        return static::fromI2CBus($i2c, $boot_now);
    }

    public static function fromI2CBus(I2CSlave $i2c, bool $boot_now = true): static
    {
        return new static(new SeesawClient($i2c), $boot_now);
    }

    protected function buttonMask(): int
    {
        return array_reduce(
            GamepadButton::cases(),
            static fn (int $mask, GamepadButton $button): int => $mask | (1 << $button->pin()->value),
            0,
        );
    }

    protected function normalizedJoystick(int $raw, bool $invert): float
    {
        $normalized = ($raw / GamepadSpecification::ADC_MAX->value) * 2.0 - 1.0;

        return max(-1.0, min(1.0, $invert ? -$normalized : $normalized));
    }

    protected function physicalButtonLabel(string $label): string
    {
        return match ($label) {
            GameControllerButton::SOUTH->value => GamepadButton::A->value,
            GameControllerButton::EAST->value => GamepadButton::B->value,
            GameControllerButton::WEST->value => GamepadButton::X->value,
            GameControllerButton::NORTH->value => GamepadButton::Y->value,
            GameControllerButton::BACK->value => GamepadButton::SELECT->value,
            default => $label,
        };
    }
}
