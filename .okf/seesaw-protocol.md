---
type: Reference
title: Seesaw protocol
description: How the package frames seesaw registers, the read delay, the opcodes it uses and the hardware IDs it accepts.
tags: [seesaw, protocol, registers, i2c]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-04T23:40:00Z }
sources:
  - id: transport
    resource: src/Transports/SeesawI2CTransport.php
    title: SeesawI2CTransport
  - id: opcodes
    resource: src/Enums/SeesawOpCode.php
    title: SeesawOpCode
  - id: hardware
    resource: src/Enums/SeesawHardwareId.php
    title: SeesawHardwareId
---

# Framing

Register = `(module << 8) | function` → two bytes. Write = `[module, fn, ...data]`. Read = write `[module, fn]` → `usleep` → separate `read(n)`.[^transport] Delay 250 µs, ADC 500 µs. Multi-byte values big-endian. Same shape as Adafruit's library; delay gives ADC time to convert. Bench firmware also answers status reads in one combined `writeRead()`, but the package doesn't rely on it.

# Opcodes

From `SeesawOpCode`.[^opcodes]

| Opcode | Value | Use |
|---|---|---|
| `STATUS_HW_ID` | 0x0001 | 1 byte chip ID |
| `STATUS_VERSION` | 0x0002 | 4 bytes: product << 16 \| date |
| `STATUS_SWRST` | 0x007F | write 0xFF → firmware restart, before the ACK |
| `GPIO_DIRCLR_BULK` / `DIRSET` | 0x0103 / 0x0102 | 4-byte pin mask |
| `GPIO_BULK` | 0x0104 | read all levels |
| `GPIO_BULK_SET` | 0x0105 | drive high / pull up |
| `GPIO_INTENSET` / `INTENCLR` | 0x0108 / 0x0109 | change interrupts |
| `GPIO_INTFLAG` | 0x010A | read changed pins, clears IRQ |
| `GPIO_PULLENSET` | 0x010B | pull enable |
| `ADC_CHANNEL_OFFSET` | 0x0907 | + pin → 2-byte 10-bit count |


Button mask = pins 0, 1, 2, 5, 6, 16 → `0x00010067`.

# Hardware IDs

`SeesawHardwareId`: SAMD09 0x55, ATtiny1616 0x83, 816 0x84, 1617 0x86, 817 0x87, 807 0x88, 806 0x89.[^hardware]

[^transport]: SeesawI2CTransport
[^opcodes]: SeesawOpCode
[^hardware]: SeesawHardwareId
