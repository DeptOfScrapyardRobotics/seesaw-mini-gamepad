---
type: Trap
title: Reset wait
description: Boot sleeps reset_wait_ms after the software reset; with no wait the chip is still restarting and NACKs the next write.
tags: [trap, boot, reset]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-16T00:00:00Z" }
sources:
  - id: api
    resource: src/Concerns/SeesawMiniGamepadAPI.php
    title: SeesawMiniGamepadAPI::softwareReset()
---

# Trap

Boot ≈ 500 ms by design (Adafruit waits 500 ms).[^api] Bench, `reset_wait_ms: 0` → hardware-ID write NACKs (`wrote -1`); any write within a few ms of SWRST does the same. Tests pass 0; hardware keeps default.

[^api]: SeesawMiniGamepadAPI::softwareReset()
