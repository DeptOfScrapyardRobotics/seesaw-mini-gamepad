---
type: Package
title: dept-of-scrapyard-robotics/seesaw-mini-gamepad
description: Mini I2C Gamepad driver for scrapyard-io/framework 0.10 — identity, requires, classes, conjure, boot, errors.
resource: composer.json
tags: [seesaw, gamepad, joystick, i2c, package]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T23:40:00Z }
sources:
  - id: composer
    resource: composer.json
    title: Package manifest
  - id: chip
    resource: src/SeesawMiniGamepad.php
    title: SeesawMiniGamepad
  - id: bootstrap
    resource: src/Concerns/SeesawMiniGamepadBootstrap.php
    title: SeesawMiniGamepadBootstrap
  - id: exception
    resource: src/SeesawMiniGamepadException.php
    title: SeesawMiniGamepadException
  - id: factory
    resource: src/Concerns/ConjuresSeesawMiniGamepad.php
    title: ConjuresSeesawMiniGamepad
---

# Identity

`dept-of-scrapyard-robotics/seesaw-mini-gamepad` 0.10.0, alias `dev-main` → `0.10.x-dev`, namespace `DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\`.[^composer] Split requires only: `gpio/contracts`, `gpio/integrated-circuits`, `venusian-voyager/contracts` (`Loop`), `venusian-voyager/nuts-and-bolts`, `venusian-voyager/vessel` (`ControlPanel`). No seesaw package; protocol lives here. Catalog slug `seesaw-mini-gamepad`.

# Surface

0.8 implemented `Surface\Contracts\HumanInput\Circuits\GameController` and took Surface's `GamepadButton` / `GamepadAxis` beside the chip's. Surface 0.10 has no HumanInput contracts, so 0.10 takes the chip enums only.

# Conjure

`app('circuit')->conjure('seesaw-mini-gamepad')` → `SeesawMiniGamepad::i2c(driver, device, slave = 0x50, irq = [], hold_ms, invert_x, invert_y, button_interrupts, reset_wait_ms, boot_now = true)`.[^factory] Bus from `gpio.i2c` (shared if app connected it), IRQ input from `gpio.digital` after it when `irq.enabled`. Null settings → configuration defaults.

# Classes

| Class | Role |
|---|---|
| `SeesawMiniGamepad` | `Bootable` + `Actuator`; `transport()`, `config()`, `connected()`, `close()`[^chip] |
| `SeesawMiniGamepadConfiguration` | settings chip can't report |
| `GamepadButtonState` | one button: down / pressed / released / held |
| `Transports\SeesawI2CTransport` | seesaw framing, optional IRQ |
| `Concerns\SeesawMiniGamepadAPI` | identity, settings, raw reads, poll, queries |
| `Concerns\SeesawMiniGamepadBootstrap` | `__get` / `__set`, `_boot` |
| `Enums\GamepadButton` / `GamepadAxis` | backed by seesaw pin |

# Boot

Once, via `boot_now` or `boot()`:[^bootstrap] SWRST (firmware restarts before acknowledging the byte, so a refused write is ignored, as Adafruit's `begin()` does) → wait `reset_wait_ms` (500; a write within a few ms of the reset is refused) → hardware ID ∈ `SeesawHardwareId` → product 5743 → button pins input + pull-up → INTENSET / INTENCLR → button state cleared. Bench (Pi 5, 0.10): 509 ms, hardware `0x87` (ATtiny817), version `0x166F7A97`.

# Errors

`SeesawMiniGamepadException` → `CircuitException` → `GPIOLevelException`.[^exception] Not seesaw, wrong product, short write, refused / short read, no bus or pin from the driver (`notConnected`), negative `hold_ms`, unknown property or config key.

[^composer]: Package manifest
[^chip]: SeesawMiniGamepad
[^bootstrap]: SeesawMiniGamepadBootstrap
[^exception]: SeesawMiniGamepadException
[^factory]: ConjuresSeesawMiniGamepad
