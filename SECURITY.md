# Security Policy

## Supported versions

seesaw-mini-gamepad is pre-1.0. No 0.x release receives security fixes or advisories; fixes land
in the next release line. Security support starts with 1.0.

| Version | Security fixes |
|---------|----------------|
| < 1.0   | No             |

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: the **Report a vulnerability** button on this repository's
**Security** tab. If that isn't available, email **info@projectsaturnstudios.com**.

Include what you found, the affected version, the adapter and hardware in use, and steps to
reproduce. Reports are read and weighed for the release line in development; before 1.0 there is
no response-time commitment.

## Security model

seesaw-mini-gamepad is plain PHP. It reads and writes the gamepad's seesaw registers through the
bus and pin transports `scrapyard-io/framework` hands it, and holds no native code of its own.
What a PHP process may touch is decided below it: the adapter (`microscrap/scrapyard-linux` over
ext-posi, `microscrap/scrapyard-usb` over ext-ftdi) and the operating system's permissions on the
I2C, GPIO or USB device. Grant those through device groups or udev rules scoped to the hardware,
not by running PHP as root.

- **Configuration is trusted input.** `conjure()` connects whatever bus, address and IRQ pin the
  `circuits.seesaw-mini-gamepad` config names. Keep that config under the app's control.
- **Bus results are checked.** A short write, a refused read or a short read throws instead of
  returning partial data. The one write whose result is ignored is the software reset, which the
  firmware cannot acknowledge because it restarts on it.
- **Input is data.** Button and joystick values come from the chip; an app that maps them to
  actions decides what each may do.

A report is in scope when this package writes a register it was not asked to, or lets a
well-formed call leave the gamepad in a state its configuration does not describe. Weaknesses in
an adapter or extension belong to that package's own policy.
