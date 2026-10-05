<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Concerns;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawMiniGamepadI2CAddress;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadConfiguration;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;
use GeneralPurposeIO\Contracts\Digital\DigitalInTransport;
use Voyager\Vessel\ControlPanel;

/**
 * The i2c() protocol factory the circuit catalog calls. Its parameters are the keys of a
 * config/circuits/seesaw-mini-gamepad.php entry, so app('circuit')->conjure('seesaw-mini-gamepad') builds a wired,
 * booted gamepad from the app's config alone. A bus or pin device that is not connected yet is connected here; one
 * the app already connected is shared as it is. Null settings keep SeesawMiniGamepadConfiguration's defaults.
 */
trait ConjuresSeesawMiniGamepad
{
    /** @param  array{enabled?: bool, driver?: string, device?: string|int, pin?: int}  $irq */
    public static function i2c(
        string $driver,
        string|int $device,
        int $slave = SeesawMiniGamepadI2CAddress::DEFAULT->value,
        array $irq = [],
        ?int $hold_ms = null,
        ?bool $invert_x = null,
        ?bool $invert_y = null,
        ?bool $button_interrupts = null,
        ?int $reset_wait_ms = null,
        bool $boot_now = true,
    ): static {
        $bus = static::gpio('gpio.i2c')->driver($driver);
        $i2c = $bus->device($device, $slave) ?? $bus->connectTo($device)->register()->device($device, $slave);

        if (is_null($i2c)) {
            throw SeesawMiniGamepadException::notConnected('I2C', $driver, $device);
        }

        $line = static::line($irq);
        $settings = array_filter(
            [
                'hold_ms' => $hold_ms,
                'invert_x' => $invert_x,
                'invert_y' => $invert_y,
                'button_interrupts' => $button_interrupts,
                'reset_wait_ms' => $reset_wait_ms,
            ],
            static fn (int|bool|null $value): bool => ! is_null($value),
        );

        return new static(new SeesawI2CTransport($i2c, $line), new SeesawMiniGamepadConfiguration(...$settings), $boot_now);
    }

    /**
     * IRQ's input, or null when its config is not enabled. The bus is connected first, so a pin on an FT232H rides
     * the bus's own context.
     *
     * @param  array{enabled?: bool, driver?: string, device?: string|int, pin?: int}  $line
     */
    protected static function line(array $line): ?DigitalInTransport
    {
        if (! ($line['enabled'] ?? false)) {
            return null;
        }

        $pins = static::gpio('gpio.digital')->driver($line['driver']);
        $pin = $pins->input($line['device'], $line['pin'])
            ?? $pins->connectTo($line['device'])->register()->input($line['device'], $line['pin']);

        return $pin ?? throw SeesawMiniGamepadException::notConnected('DigitalIO', $line['driver'], $line['device']);
    }

    /** A protocol manager from the app's container: gpio.i2c or gpio.digital. */
    protected static function gpio(string $manager): mixed
    {
        return ControlPanel::getInstance()->make($manager);
    }
}
