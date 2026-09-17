---
okf_version: "0.2"
---

# dept-of-scrapyard-robotics/seesaw-mini-gamepad — knowledge bundle

Adafruit Mini I2C Gamepad (seesaw, product 5743) driver for `scrapyard-io/framework` 0.8. Seesaw boot + framing in-package, poll-based buttons + joystick, optional IRQ, dock polling.

Read this index first, open only concepts task needs. Every concept `status: draft` until human verifies.

# Concepts

* [overview.md](/overview.md) - package identity, requires, classes, boot, errors
* [seesaw-protocol.md](/seesaw-protocol.md) - register framing, read delay, opcodes, hardware IDs
* [polling.md](/polling.md) - poll(), button state, joystick, IRQ path, dock
* [settings.md](/settings.md) - configuration, properties, config file, publish tag

# Traps

* [traps/](/traps/index.md) - one-poll edges, reset wait, IRQ priming

# Log

* [log.md](/log.md)
