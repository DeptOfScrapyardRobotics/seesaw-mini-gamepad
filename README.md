# seesaw-mini-gamepad

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dept-of-scrapyard-robotics/seesaw-mini-gamepad.svg)](https://packagist.org/packages/dept-of-scrapyard-robotics/seesaw-mini-gamepad)
[![License](https://img.shields.io/packagist/l/dept-of-scrapyard-robotics/seesaw-mini-gamepad.svg)](LICENSE)

Read the Adafruit Mini I2C STEMMA QT Gamepad from PHP: six buttons and a two-axis joystick, over I2C with the ScrapyardIO GPIO framework.

`dept-of-scrapyard-robotics/seesaw-mini-gamepad` boots the gamepad's seesaw firmware, sets up its button pins, and turns each poll into button presses, releases, holds and joystick positions from -1.0 to 1.0. It talks to seesaw directly, so no separate seesaw package is needed.

```
ext-posi / ext-ftdi            1:1 system and libftdi calls
  → microscrap/*               libgpiod, i2c-dev, libmpsse in PHP
    → microscrap/scrapyard-*   adapters: the `native` and `usb` drivers
      → scrapyard-io/framework protocol managers, transports, the circuit catalog
        → dept-of-scrapyard-robotics/seesaw-mini-gamepad   ← this package
```

## Requirements

- PHP 8.4 or newer
- A Venusian 0.10 application with the `scrapyard-io/framework` 0.10 components (`gpio/i2c`, `gpio/digital`, `gpio/integrated-circuits`)
- An adapter for your hardware:
  - `microscrap/scrapyard-linux` (driver `native`) for native `i2c-dev` and `libgpiod`, needs `ext-posi`
  - `microscrap/scrapyard-usb` (driver `usb`) for FTDI MPSSE boards such as the FT232H, needs `ext-ftdi`
- `venusian-voyager/io-pools` 0.10 if you poll on the event loop

## Installation

```bash
composer require dept-of-scrapyard-robotics/seesaw-mini-gamepad
```

The service provider is discovered automatically. It merges the package's wiring config under `circuits.seesaw-mini-gamepad` and registers the gamepad with the circuit catalog. To publish the config into your app, run:

```bash
php computer vendor:publish --tag=seesaw-mini-gamepad-config
```

That writes `config/circuits/seesaw-mini-gamepad.php`, with `driver => 'none'` until you fill in your bench.

## Quick start

A gamepad at `0x50` on a Raspberry Pi's I2C bus 1:

```php
// config/circuits/seesaw-mini-gamepad.php
return [
    'default_config' => 'i2c',
    'configs' => [
        'i2c' => [
            'driver' => 'native',
            'device' => 1,
            'slave' => 0x50,
        ],
    ],
];
```

```php
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;

$pad = app('circuit')->conjure('seesaw-mini-gamepad');   // connected and booted

while (true) {
    $pad->poll();

    if ($pad->isPressed(GamepadButton::A)) {
        echo "A!\n";
    }

    printf("stick %+.2f %+.2f\n", $pad->x(), $pad->y());
    usleep(10_000);
}
```

Booting resets the seesaw firmware and waits `reset_wait_ms` (500 ms) for it to come back; on a Raspberry Pi 5 the whole boot takes about 510 ms. It then checks that the chip is a seesaw running the Mini Gamepad firmware (product 5743), and sets every button pin to input with pull-up.

## Connecting

`conjure('seesaw-mini-gamepad')` reads `circuits.seesaw-mini-gamepad`, picks `default_config`, and calls the gamepad's `i2c()` factory with that entry's keys. You can call the factory directly too:

```php
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;

$pad = SeesawMiniGamepad::i2c('native', 1, slave: 0x50);

// with IRQ on GPIO17 and button interrupts on
$pad = SeesawMiniGamepad::i2c(
    'native', 1,
    irq: ['enabled' => true, 'driver' => 'native', 'device' => 0, 'pin' => 17],
    button_interrupts: true,
);
```

A bus or pin device that isn't connected yet is connected by the factory. One your app already connected is shared as it is. The IRQ pin is opened after the bus, so on an FT232H it rides the same USB context. The settings arguments (`hold_ms`, `invert_x`, `invert_y`, `button_interrupts`, `reset_wait_ms`) default to `null`, which keeps the configuration object's defaults. Pass `boot_now: false` to build without booting.

### Building the transport yourself

```php
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;

$slave = app('gpio.i2c')->driver('native')->connectTo(1)->register()->device(1, 0x50);
$irq = app('gpio.digital')->driver('native')->connectTo(0)->register()->input(0, 17);

$pad = new SeesawMiniGamepad(new SeesawI2CTransport($slave, $irq), boot_now: true);
```

The default address is `0x50` (`SeesawMiniGamepadI2CAddress::DEFAULT`). Seesaw registers are a module byte and a function byte. Each read writes the register, waits, then reads in a separate transaction.

## Reading the gamepad

Everything below answers from the last `poll()`. One poll reads the buttons and both joystick axes.

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

A press or release shows for exactly one poll. A press and release that both happen between two polls are not seen, so poll every 10 ms or so.

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

### Polling on the event loop

```php
use Voyager\Contracts\IOPools\Loop;

$loop = app(Loop::class);

$pad->every($loop);              // poll() every 10 ms
$pad->every($loop, 0.02);        // every 20 ms
// …
$pad->stop($loop);
```

`every($loop, $interval_s, $name)` returns the loop `Timer` that runs `poll()`. Give each gamepad its own `$name` (default `seesaw-mini-gamepad`) to poll several side by side, and pass the same name to `stop()`. Polling on the loop and calling `poll()` yourself can be mixed.

## IRQ

The gamepad has an IRQ pin. Seesaw pulls it low when a button changes and releases it when the change flags are read.

With IRQ wired and `button_interrupts` on, a poll skips the button read while the pin is high. It reads the change flags and the buttons only when the pin is low. The first poll after boot always reads the buttons, so a button held through boot reads pressed then. The joystick has no interrupt, so each poll still reads both axes.

```php
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
| `reset_wait_ms` | `500` | wait after the boot reset; the firmware refuses writes for a few ms after it |

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

Each has a matching method, such as `getProductId()` or `setHoldMs()`. `softwareReset()`, `setButtonPullups()`, `readGPIO()` and `readInterruptFlags()` reach the seesaw registers directly. The firmware restarts on the reset byte before acknowledging it, so `softwareReset()` ignores that write's result, as Adafruit's library does.

## Errors

Failures throw `SeesawMiniGamepadException`, which descends from `GeneralPurposeIO\Contracts\IntegratedCircuits\CircuitException`:

- The chip's hardware ID isn't a seesaw chip, or its product isn't 5743.
- The bus writes fewer bytes than asked, refuses a read, or returns fewer bytes than asked.
- The protocol driver hands back no bus or pin (`notConnected`).
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
| `default_config` | `'i2c'` | which entry under `configs` `conjure()` uses |
| `configs.i2c.driver` | `'none'` | I2C adapter: `native` or `usb` |
| `configs.i2c.device` | `''` | a bus number, or `ft232h` |
| `configs.i2c.slave` | `0x50` | gamepad address |
| `configs.i2c.irq` | disabled, pin 0 | `enabled`, `driver`, `device` and `pin` for IRQ |
| `configs.i2c.hold_ms`, `invert_x`, `invert_y`, `button_interrupts`, `reset_wait_ms` | `null` | settings; null keeps the configuration object's default |
| `configs.i2c.boot_now` | `true` | boot during `conjure()` |

## Upgrading from 0.8

| 0.8 | 0.10 |
|---|---|
| `scrapyard-io/framework` 0.8 components, `surface/contracts` | the 0.10 components; no Surface requirement |
| `I2C::driver(...)`, `DigitalIO::driver(...)` | `app('circuit')->conjure()`, `SeesawMiniGamepad::i2c()`, or `app('gpio.i2c')->driver(...)` |
| the package merged config but never read it | `conjure()` builds the gamepad from it |
| `$pad->every(IOPool::gpio(), $ticks)` → `Recurrence` | `$pad->every($loop, $interval_s)` → loop `Timer`; `$pad->stop($loop)` |
| implements Surface's `GameController`; methods took Surface's `GamepadButton` / `GamepadAxis` too | Surface 0.10 has no HumanInput contracts; the chip's `GamepadButton` / `GamepadAxis` only |

## Testing

```bash
composer install
vendor/bin/pest
```

The suite runs against a recording fake of the I2C bus and a fake IRQ pin, so it needs no hardware. The boot sequence is checked byte for byte. The gamepad was also exercised on a Raspberry Pi 5's I2C bus for this release: all six buttons pressed and released, Start reported holding after a second, and the stick read +1.0 right and -1.0 up.

## Security

The driver reads and writes seesaw registers on hardware the PHP process can open. See [SECURITY.md](SECURITY.md) for the support policy and how to report a vulnerability.

## License

MIT. See [LICENSE](LICENSE).
