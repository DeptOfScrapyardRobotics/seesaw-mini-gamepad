# dept-of-scrapyard-robotics/seesaw-mini-gamepad Update Log

## 2026-10-04
* **Update**: 0.10 port. [overview](/overview.md): 0.10 split requires, `conjure()` through the new `i2c()` factory, catalog slug; Surface HumanInput gone in 0.10, chip enums only; reset write refused by the restarting firmware is ignored.
* **Update**: [polling](/polling.md): `every(Loop)` timer and `stop()` replace the dock; live reference from the Pi. [settings](/settings.md): settings keys in the config file, catalog. [seesaw-protocol](/seesaw-protocol.md): SWRST note.
* **Removal**: the three 0.8 warning notes (one-poll edges, reset wait, IRQ priming), folded into [polling](/polling.md) and [overview](/overview.md).

## 2026-09-17
* **Update**: [overview](/overview.md) — `SeesawMiniGamepad` implements `Surface\Contracts\HumanInput\Circuits\GameController` in place; chip API untouched, parameters widened. `require`: `surface/contracts`; `require-dev`: `surface/human-input`.

## 2026-09-16
* **Update**: [polling](/polling.md) stick directions checked on hardware (right +X, up −Y).
* **Creation**: bundle seeded for 0.8.0 — [overview](/overview.md), [seesaw-protocol](/seesaw-protocol.md), [polling](/polling.md), [settings](/settings.md), three [traps](/traps/index.md).
