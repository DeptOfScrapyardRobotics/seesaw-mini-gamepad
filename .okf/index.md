---
okf_version: "0.2"
---

# dept-of-scrapyard-robotics/seesaw-mini-gamepad

Adafruit Mini I2C Gamepad (seesaw, product 5743) driver for `scrapyard-io/framework` 0.10. Conjured from config, seesaw boot + framing in-package, poll-based buttons + joystick, optional IRQ, polling on the event loop.

Read this index first, open only concepts task needs. Every concept `status: draft` until human verifies.

# Concepts

* [Package](overview.md) - Mini I2C Gamepad driver for scrapyard-io/framework 0.10 — identity, requires, classes, conjure, boot, errors.
* [Seesaw protocol](seesaw-protocol.md) - Register framing, read delay, opcodes, hardware IDs.
* [Polling](polling.md) - poll(), button state, joystick, IRQ path, event-loop timer.
* [Settings](settings.md) - Configuration, properties, config file, publish tag.

# Log

* [log.md](log.md)
