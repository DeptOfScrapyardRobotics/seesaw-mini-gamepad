---
type: Trap
title: IRQ priming read
description: The first poll after boot reads GPIO even when IRQ is idle, so buttons already held at boot are seen.
tags: [trap, irq, poll]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-09-16T00:00:00Z" }
sources:
  - id: api
    resource: src/Concerns/SeesawMiniGamepadAPI.php
    title: SeesawMiniGamepadAPI::readButtons()
---

# Trap

Counting bus traffic with IRQ on: expect one GPIO_BULK on first poll, none while IRQ high after.[^api] Held-at-boot button → reported pressed on first poll, no IRQ needed.

[^api]: SeesawMiniGamepadAPI::readButtons()
