---
type: Package
title: dept-of-scrapyard-robotics/seesaw-mini-gamepad
description: Mini I2C Gamepad driver for scrapyard-io/framework 0.8 — identity, requires, classes, boot, errors.
resource: composer.json
tags: [seesaw, gamepad, joystick, i2c, package]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-16T00:00:00Z" }
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
---

# Identity

`dept-of-scrapyard-robotics/seesaw-mini-gamepad` 0.8.0, namespace `DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\`.[^composer] Split requires only: `gpio/contracts`, `gpio/integrated-circuits`, `venusian-voyager/nuts-and-bolts`. No seesaw package; protocol lives here.

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

Once, via `boot_now` or `boot()`:[^bootstrap] SWRST → wait `reset_wait_ms` (500) → hardware ID ∈ `SeesawHardwareId` → product 5743 → button pins input + pull-up → INTENSET / INTENCLR → button state cleared. Bench: ~506 ms, hardware `0x87` (ATtiny817).

# Errors

`SeesawMiniGamepadException` → `CircuitException` → `GPIOLevelException`.[^exception] Not seesaw, wrong product, short write, refused / short read, negative `hold_ms`, unknown property or config key.

[^composer]: Package manifest
[^chip]: SeesawMiniGamepad
[^bootstrap]: SeesawMiniGamepadBootstrap
[^exception]: SeesawMiniGamepadException
