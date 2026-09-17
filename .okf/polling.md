---
type: Guide
title: Polling the gamepad
description: What poll() reads, how button state and joystick values are derived, when the IRQ path skips the button read, and dock polling.
tags: [poll, buttons, joystick, irq, io-pools]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-16T00:00:00Z" }
sources:
  - id: api
    resource: src/Concerns/SeesawMiniGamepadAPI.php
    title: SeesawMiniGamepadAPI
  - id: state
    resource: src/GamepadButtonState.php
    title: GamepadButtonState
---

# poll()

Order: buttons → ADC X (pin 14) → ADC Y (pin 15).[^api] Pi 5 native: ~2.3 ms. Everything else answers from last poll.

# Buttons

Pressed = pin low. `GamepadButtonState::update()` → `pressed` / `released` true only on the poll that saw the change; `down_since` set on press.[^state] `isHolding` = down and `heldMs() >= hold_ms`.

Queries take `GamepadButton`: `isDown`, `isPressed`, `wasReleased`, `isHolding`, `heldMs`; lists `downButtons` / `pressedButtons` / `releasedButtons` / `holdingButtons`; `anyDown`, `allDown`, `chord`, `anyPressed` — no args = all six. `buttons()` keyed by name.

# Joystick

`(raw / 1023) × 2 − 1`, clamped, flipped per `invert_x` (default on) / `invert_y`. Rest ≈ raw 500 → ≈ 0. Defaults, bench-checked: right → X +1, up → Y −1 (screen coords); `invert_y` → up +1. `readAxisRaw()` reads now.

# IRQ path

`interruptLine()` = IRQ input when wired **and** `button_interrupts`; else null.

| State | Button traffic |
|---|---|
| null line | GPIO_BULK every poll |
| first poll after boot | GPIO_BULK (prime) |
| line high | none; states keep level, edges false |
| line low | INTFLAG (clears) → GPIO_BULK |

Axes read every poll regardless.

# Dock

`every($gpio, $ticks = 1, $name = 'seesaw-mini-gamepad')` → `Recurrence` running `poll()`. Mixes freely with direct `poll()`.

[^api]: SeesawMiniGamepadAPI
[^state]: GamepadButtonState
