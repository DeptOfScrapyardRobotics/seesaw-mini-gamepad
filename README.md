# seesaw-mini-gamepad

Read the Adafruit Mini I2C STEMMA QT Gamepad from PHP: six buttons and a two-axis joystick, over I2C with the ScrapyardIO GPIO framework.

`dept-of-scrapyard-robotics/seesaw-mini-gamepad` boots the gamepad's seesaw firmware, sets up its button pins, and turns each poll into button presses, releases, holds and joystick positions from -1.0 to 1.0. It talks to seesaw directly, so no separate seesaw package is needed.

## Requirements

- PHP 8.4 or newer
- A Venusian application with `scrapyard-io/framework` 0.8
- An adapter for your hardware:
  - `microscrap/scrapyard-linux` for native `i2c-dev` and `libgpiod` (needs `ext-posi`)
  - `microscrap/scrapyard-usb` for FTDI MPSSE boards such as the FT232H (needs `ext-ftdi`)

## Installation

```bash
composer require dept-of-scrapyard-robotics/seesaw-mini-gamepad
```

The service provider is discovered automatically. It merges the package's wiring config under `circuits.seesaw-mini-gamepad`. To publish that config into your app, run:

```bash
php computer vendor:publish --tag=seesaw-mini-gamepad-config
```

That writes `config/circuits/seesaw-mini-gamepad.php`.

## Quick start

A gamepad at `0x50` on a Raspberry Pi's `i2c-1`:

```php
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawMiniGamepadI2CAddress;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;
use GeneralPurposeIO\I2C\I2C;

$slave = I2C::driver('native')
    ->connectTo(1)
    ->register()
    ->device(1, SeesawMiniGamepadI2CAddress::DEFAULT->value);

$pad = new SeesawMiniGamepad(new SeesawI2CTransport($slave), boot_now: true);

while (true) {
    $pad->poll();

    if ($pad->isPressed(GamepadButton::A)) {
        echo "A!\n";
    }

    printf("stick %+.2f %+.2f\n", $pad->x(), $pad->y());
    usleep(10_000);
}
```

Booting resets the seesaw firmware, which takes about half a second. It then checks that the chip is a seesaw running the Mini Gamepad firmware (product 5743), and sets every button pin to input with pull-up.

## Connecting

The gamepad takes a `SeesawI2CTransport`. It wraps an I2C connection from the framework, plus an optional IRQ input.

```php
use GeneralPurposeIO\Digital\DigitalIO;
use GeneralPurposeIO\I2C\I2C;

// Linux i2c-dev and gpiochip0
$slave = I2C::driver('native')->connectTo(1)->register()->device(1, 0x50);
$irq = DigitalIO::driver('native')->connectTo(0)->register()->input(0, 17);

// FTDI MPSSE
$slave = I2C::driver('usb')->connectTo('ft232h')->register()->device('ft232h', 0x50);

$pad = new SeesawMiniGamepad(new SeesawI2CTransport($slave, $irq), boot_now: true);
```

The default address is `0x50` (`SeesawMiniGamepadI2CAddress::DEFAULT`). Seesaw registers are a module byte and a function byte. Each read writes the register, waits, then reads in a separate transaction.

### From the published config

The config file holds your wiring. The package merges it but does not open connections from it, so read it where you build the gamepad:

```php
$name = config('circuits.seesaw-mini-gamepad.default_config');      // 'i2c'
$wiring = config("circuits.seesaw-mini-gamepad.configs.{$name}");

$slave = I2C::driver($wiring['driver'])
    ->connectTo($wiring['device'])
    ->register()
    ->device($wiring['device'], $wiring['slave']);
```

## Reading the gamepad

Everything below answers from the last `poll()`. One poll reads the buttons and both joystick axes, in about 2.3 ms on a Raspberry Pi 5.

### Buttons

| `GamepadButton` | Seesaw pin |
|---|---|
| `SELECT` | 0 |
| `B` | 1 |
| `Y` | 2 |
| `A` | 5 |
| `X` | 6 |
| `START` | 16 |

```php
$pad->isDown(GamepadButton::B);        // down right now
$pad->isPressed(GamepadButton::B);     // went down on this poll
$pad->wasReleased(GamepadButton::B);   // came up on this poll
$pad->isHolding(GamepadButton::B);     // down for at least hold_ms
$pad->heldMs(GamepadButton::B);        // how long it has been down

$pad->downButtons();       // [GamepadButton::A, GamepadButton::START]
$pad->pressedButtons();
$pad->releasedButtons();
$pad->holdingButtons();

$pad->anyDown();                                          // any button
$pad->anyDown(GamepadButton::A, GamepadButton::B);
$pad->allDown(GamepadButton::X, GamepadButton::Y);
$pad->chord(GamepadButton::START, GamepadButton::SELECT);   // same as allDown()
$pad->anyPressed();
```

A press or release shows for exactly one poll. A press and release that both happen between two polls are not seen, so poll often.

`button(GamepadButton::A)` returns that button's `GamepadButtonState`, and `buttons()` returns all six keyed by name.

### Joystick

```php
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadAxis;

$pad->x();                     // -1.0 … 1.0
$pad->y();
$pad->axis(GamepadAxis::X);
$pad->axes();                  // ['x' => …, 'y' => …]

$pad->readAxisRaw(GamepadAxis::X);   // 0 … 1023, read now
```

The stick rests near the middle of the 10-bit range, so it reads close to zero. With the defaults, right reads +1.0 and up reads -1.0, the way screen coordinates run. X is flipped by default to get that. Set `invert_y` to make up read +1.0 instead.

### Polling on the dock

```php
use Voyager\IOPools\MagicAliases\IOPool;

$recurrence = $pad->every(IOPool::gpio());    // poll() on every gpio tick
// …
$recurrence->stop();
```

`every($gpio, $ticks, $name)` puts `poll()` on the gpio dock. Pass `$ticks` to poll less often, and `$name` when several gamepads share a dock. Polling on the dock and calling `poll()` yourself can be mixed.

## IRQ

The gamepad has an IRQ pin. Seesaw pulls it low when a button changes and releases it when the change flags are read.

With IRQ wired and `button_interrupts` on, a poll skips the button read while the pin is high. It reads the change flags and the buttons only when the pin is low. The first poll after boot always reads the buttons. The joystick has no interrupt, so each poll still reads both axes.

```php
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadConfiguration;

$pad = new SeesawMiniGamepad(
    new SeesawI2CTransport($slave, $irq),
    new SeesawMiniGamepadConfiguration(button_interrupts: true),
    boot_now: true,
);

$pad->button_interrupts = false;   // back to reading the buttons every poll
```

Without IRQ wired, or with `button_interrupts` off, every poll reads the buttons. `interruptLine()` returns the pin when the IRQ path is in use, and `null` otherwise.

## Configuration object

`SeesawMiniGamepadConfiguration` holds the gamepad's settings. Every argument is optional.

| Argument | Default | Meaning |
|---|---|---|
| `hold_ms` | `500` | how long a button stays down before it counts as holding |
| `invert_x` | `true` | flip the X axis, so right reads +1.0 |
| `invert_y` | `false` | flip the Y axis; off, up reads -1.0 |
| `button_interrupts` | `false` | have seesaw pull IRQ low when a button changes |
| `reset_wait_ms` | `500` | wait after the boot reset |

## Settings

```php
$pad->hold_ms = 250;
$pad->invert_y = true;
$pad->button_interrupts = true;     // written to the chip straight away
```

| Property | Read | Write | Type |
|---|---|---|---|
| `hardware_id` | yes | | `int`, `0x87` on this board |
| `version` | yes | | `int`, product in the high 16 bits |
| `product_id` | yes | | `int`, `5743` |
| `hold_ms` | yes | yes | `int`, 0 or more |
| `invert_x`, `invert_y` | yes | yes | `bool` |
| `button_interrupts` | yes | yes | `bool` |
| `x`, `y` | yes | | `float` |
| `axes` | yes | | `array` |

Each has a matching method, such as `getProductId()` or `setHoldMs()`. `softwareReset()`, `setButtonPullups()`, `readGPIO()` and `readInterruptFlags()` reach the seesaw registers directly.

## Errors

Failures throw `DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException`, which extends the framework's `GPIOLevelException`:

- The chip's hardware ID isn't a seesaw chip, or its product isn't 5743.
- The bus writes fewer bytes than asked, refuses a read, or returns fewer bytes than asked.
- `hold_ms` is negative.
- Your code reads or writes a property or configuration key that doesn't exist.

## Closing

```php
$pad->close();
```

`close()` releases the IRQ pin and clears the button state. The I2C connection belongs to the protocol driver and stays open for other devices on the bus.

## Configuration file

`config/circuits/seesaw-mini-gamepad.php`:

| Key | Default | Meaning |
|---|---|---|
| `default_config` | `'i2c'` | which entry under `configs` to use |
| `configs.i2c.driver` | `'none'` | I2C adapter: `native` or `usb` |
| `configs.i2c.device` | `''` | a bus number, or `ft232h` |
| `configs.i2c.slave` | `0x50` | gamepad address |
| `configs.i2c.irq` | disabled, pin 0 | `enabled`, `driver`, `device` and `pin` for IRQ |

## Testing

```bash
composer install
vendor/bin/pest
```

The suite runs against a recording fake of the I2C bus and a fake IRQ pin, so it needs no hardware. The boot sequence is checked byte for byte.

## License

MIT. See [LICENSE](LICENSE).
