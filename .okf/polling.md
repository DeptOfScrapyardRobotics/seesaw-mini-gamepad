---
type: Guide
title: Polling the gamepad
description: What poll() reads, how button state and joystick values are derived, when the IRQ path skips the button read, and polling on the event loop.
tags: [poll, buttons, joystick, irq, io-pools]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T23:40:00Z }
sources:
  - id: api
    resource: src/Concerns/SeesawMiniGamepadAPI.php
    title: SeesawMiniGamepadAPI
  - id: state
    resource: src/GamepadButtonState.php
    title: GamepadButtonState
---

# poll()

Order: buttons → ADC X (pin 14) → ADC Y (pin 15).[^api] Everything else answers from last poll.

# Buttons

Pressed = pin low. `GamepadButtonState::update()` → `pressed` / `released` true only on the poll that saw the change, cleared by the next; `down_since` set on press.[^state] Check edges after each `poll()`. GPIO_BULK reads levels, so a tap shorter than the poll interval goes unseen: poll every ~10 ms. `isHolding` = down and `heldMs() >= hold_ms`.

Queries take `GamepadButton`: `isDown`, `isPressed`, `wasReleased`, `isHolding`, `heldMs`; lists `downButtons` / `pressedButtons` / `releasedButtons` / `holdingButtons`; `anyDown`, `allDown`, `chord`, `anyPressed` — no args = all six. `buttons()` keyed by name.

# Joystick

`(raw / 1023) × 2 − 1`, clamped, flipped per `invert_x` (default on) / `invert_y`. Rest ≈ raw 500 → ≈ 0. Defaults, bench-checked: right → X +1, up → Y −1 (screen coords); `invert_y` → up +1. `readAxisRaw()` reads now.

# IRQ path

`interruptLine()` = IRQ input when wired **and** `button_interrupts`; else null.

| State | Button traffic |
|---|---|
| null line | GPIO_BULK every poll |
| first poll after boot | GPIO_BULK (prime): a button held through boot reads pressed |
| line high | none; states keep level, edges false |
| line low | INTFLAG (clears) → GPIO_BULK |

Axes read every poll regardless.

# Event loop

`every(Loop $loop, float $interval_s = 0.01, string $name = 'seesaw-mini-gamepad')` → loop `Timer` running `poll()`; `stop($loop, $name)` forgets it. A name per gamepad lets several poll side by side. Mixes freely with direct `poll()`.

Live, Pi 5, 10 ms timer: six buttons pressed and released, Start holding after 1001 ms, stick right x 1.000, up y −1.000.

[^api]: SeesawMiniGamepadAPI
[^state]: GamepadButtonState
