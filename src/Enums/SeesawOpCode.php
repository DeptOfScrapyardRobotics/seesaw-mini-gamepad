<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums;

/** Seesaw register as (module << 8) | function, sent as two bytes. */
enum SeesawOpCode: int
{
    case STATUS_HW_ID = 0x0001;
    case STATUS_VERSION = 0x0002;
    case STATUS_SWRST = 0x007F;

    case GPIO_DIRSET_BULK = 0x0102;
    case GPIO_DIRCLR_BULK = 0x0103;
    case GPIO_BULK = 0x0104;
    case GPIO_BULK_SET = 0x0105;
    case GPIO_INTENSET = 0x0108;
    case GPIO_INTENCLR = 0x0109;
    case GPIO_INTFLAG = 0x010A;
    case GPIO_PULLENSET = 0x010B;

    case ADC_CHANNEL_OFFSET = 0x0907;
}
