# Agent guidelines — dept-of-scrapyard-robotics/seesaw-mini-gamepad

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from the Composer dist via `.gitattributes` `export-ignore`). Before changing code or advising on this package: read [`.okf/index.md`](.okf/index.md) first, open only the concepts the task needs, prefer `status: stable` over `draft`. When you learn something durable, update the affected concept(s), bump `generated.at`, and append [`.okf/log.md`](.okf/log.md); new or changed concepts stay `status: draft` until a human verifies them. The bundle documents the package, never a session.

Do **not** create `.okf` folders under `src/*` — knowledge for this package lives at the package root only. Catalog, transport, loop and adapter semantics belong to `scrapyard-io/framework`'s and Venusian's bundles; point there, do not restate them here.

## Where this package sits

`ext-posi` / `ext-ftdi` → `microscrap/*` → `scrapyard-io/framework` (protocol managers, transports, the circuit catalog) → **`dept-of-scrapyard-robotics/seesaw-mini-gamepad`** (chip driver) → apps.

## Package rules (quick) — 0.10.x

- Composer: `dept-of-scrapyard-robotics/seesaw-mini-gamepad` **0.10.0**. PHP `^8.4|^8.5|^8.6`. Namespace `DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\` → `src/`.
- **Requires split components only**: `gpio/contracts`, `gpio/integrated-circuits`, `venusian-voyager/contracts`, `venusian-voyager/nuts-and-bolts`, `venusian-voyager/vessel`. Never `scrapyard-io/framework`, `venusian/surface` or `venusian/framework`. Protocol components, io-pools and adapters are `suggest`.
- **Chip = `Bootable` + `Actuator`.** Boot = seesaw `begin()`: software reset (its refused write ignored: the firmware restarts before the ACK), wait `reset_wait_ms`, hardware ID, product 5743, button pins input + pull-up, interrupt enable/clear. `close()` releases IRQ only; the bus belongs to its driver.
- **The factory is the config shape.** `ConjuresSeesawMiniGamepad::i2c()` parameters are exactly a `circuits.seesaw-mini-gamepad.configs.*` entry's keys; the provider catalogs `seesaw-mini-gamepad`. A new config key = a new factory parameter, and the reverse. Bus first, IRQ after.
- **Polling**: `poll()` reads buttons then both axes; edges last one poll. `every(Loop, interval, name)` / `stop(Loop, name)` put it on a loop timer. The IRQ path (wired + `button_interrupts`) skips the button read while IRQ is high, after one priming read.
- **Transport** frames seesaw registers (module, function), reads with a separate transaction after a delay, and throws on a short write or a refused / short read.
- **Reach the framework through the container.** 0.10 has no protocol aliases, facades or dock. The factory resolves `gpio.i2c` / `gpio.digital` from `ControlPanel::getInstance()`; apps call `app('circuit')->conjure()`.
- **Surface**: 0.10 has no HumanInput contracts; the chip's `GamepadButton` / `GamepadAxis` are the only vocabulary until it does.
- **Exceptions** descend from `GeneralPurposeIO\Contracts\IntegratedCircuits\CircuitException` → `GPIOLevelException`.
- Enums int-backed, FULLY UPPERCASE cases. No class constants. `is_null($x)` over `$x === null`.

## Verification

```bash
vendor/bin/pest            # recording fake bus and IRQ pin, a real EventLoop; no hardware
```

Run it under NTS and ZTS PHP before every commit. Suites stay hardware-free: chips are for scratch smoke scripts, never committed and never in `tests/`.

Hardware truth: a Mini Gamepad at `0x50` on a Raspberry Pi 5's I2C bus 1, IRQ not wired; proven by someone pressing the buttons and moving the stick. The seesaw firmware does not take bus probes well; reach it through its own registers.
