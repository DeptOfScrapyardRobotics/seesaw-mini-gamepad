<?php

/*
| Proven against a recording fake I2C transport and a fake IRQ pin: every
| byte the seesaw would see, every byte it would answer, in order. Nothing
| here touches a bus. The live check is a Mini Gamepad at 0x50 on the Pi 5.
*/
