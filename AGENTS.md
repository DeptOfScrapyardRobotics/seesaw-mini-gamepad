# Agent guidelines — dept-of-scrapyard-robotics/seesaw-mini-gamepad

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from the Composer dist via `.gitattributes` `export-ignore`). Before changing code or advising on this package: read [`.okf/index.md`](.okf/index.md) first, open only the concepts the task needs, prefer `status: stable` over `draft`. When you learn something durable, update the affected concept(s) and append [`.okf/log.md`](.okf/log.md); new or changed concepts stay `status: draft` until a human verifies them.

Do **not** create `.okf` folders under `src/*` — knowledge for this package lives at the package root only. Transport and dock semantics belong to `scrapyard-io/framework`; point there, do not restate them here.

## Where this package sits

`ext-posi` / `ext-ftdi` → `microscrap/*` → `scrapyard-io/framework` (protocol managers, transports) → **`dept-of-scrapyard-robotics/seesaw-mini-gamepad`** (Adafruit Mini I2C Gamepad, product 5743) → Surface human input, once that component exists.

## Package rules (quick) — 0.8.x

- Composer: `dept-of-scrapyard-robotics/seesaw-mini-gamepad` **0.8.0**. PHP `^8.4|^8.5|^8.6`. Namespace `DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\` → `src/` (DOSR files input devices under Actuators).
- **Requires split components only**: `gpio/contracts`, `gpio/integrated-circuits`, `venusian-voyager/nuts-and-bolts`. Never `scrapyard-io/framework` or `venusian/framework`. Protocol components and adapters are `suggest`.
- **No seesaw package.** The seesaw protocol this board needs (status, GPIO, ADC) lives here. Don't split it out or grow it into a general seesaw client.
- **Gamepad = `Bootable` + `Actuator`.** Boot = Adafruit `begin()`: SWRST → wait `reset_wait_ms` → hardware ID must be a `SeesawHardwareId` → product (version >> 16) must be 5743 → button pins input + pull-up (DIRCLR, PULLENSET, BULK_SET) → INTENSET or INTENCLR per `button_interrupts`.
- **Seesaw framing**: register = `(module << 8) | function`; a read is write → `usleep` (250 µs, ADC 500 µs) → separate `read()`, as Adafruit does. ADC register = `0x0907 + pin`.
- **Poll model.** `poll()` reads GPIO_BULK (pressed = low) then ADC X and Y; button state and axes answer from the last poll. Press / release flags last one poll.
- **IRQ path.** Used only when the transport has IRQ and `button_interrupts` is on. First poll after boot always reads GPIO; after that, IRQ high → skip the GPIO read; IRQ low → read INTFLAG (clears) then GPIO.
- **Configuration is the state** for settings the chip can't report (`hold_ms`, `invert_x`, `invert_y`, `button_interrupts`, `reset_wait_ms`). `setButtonInterrupts()` writes the chip, then the configuration.
- **Transport**: `SeesawI2CTransport` — throws on short write, refused or short read; `close()` releases IRQ only.
- **Reach the framework through MagicAliases** (`I2C::`, `DigitalIO::`, `IOPool::`), never `app('gpio.*')`.
- **Config** merges under `circuits.seesaw-mini-gamepad`; publish tag `seesaw-mini-gamepad-config` → `config/circuits/seesaw-mini-gamepad.php`. The package reads none of it.
- **Exceptions** descend from `GeneralPurposeIO\Contracts\IntegratedCircuits\CircuitException` → `GPIOLevelException`.
- Enums int- or string-backed, FULLY UPPERCASE cases. No class constants. `is_null($x)` over `$x === null`.

## Verification

```bash
vendor/bin/pest            # recording fakes; no hardware
```

Hardware truth is the Mini Gamepad at `0x50` on the Pi 5's `i2c-1` (`fnk`), hardware ID `0x87` (ATtiny817), product 5743. IRQ is not connected on the bench; that path is proven on fakes only. Joystick defaults read right +X, up −Y. Announce with `say` before any run that needs someone to press buttons.
