<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums;

/** STATUS_HW_ID answers: the microcontroller running seesaw. */
enum SeesawHardwareId: int
{
    case SAMD09 = 0x55;
    case ATTINY1616 = 0x83;
    case ATTINY816 = 0x84;
    case ATTINY1617 = 0x86;
    case ATTINY817 = 0x87;
    case ATTINY807 = 0x88;
    case ATTINY806 = 0x89;
}
