<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException;
use GeneralPurposeIO\Contracts\Digital\DigitalInTransport;
use GeneralPurposeIO\Contracts\I2C\I2CTransport;
use GeneralPurposeIO\Contracts\IntegratedCircuits\ReadWriter;
use GeneralPurposeIO\Contracts\NutsAndBolts\Splices16Bits;

/**
 * Seesaw framing: register = module byte, function byte. A read writes the
 * register, waits for the firmware (longer for an ADC conversion), then reads
 * in a separate transaction, as Adafruit's seesaw library does. IRQ is an
 * optional input.
 */
class SeesawI2CTransport implements ReadWriter
{
    use Splices16Bits;

    public function __construct(
        protected I2CTransport $transport,
        protected ?DigitalInTransport $irq = null,
    ) {}

    public function read(int $register, int $length, int $delay_us = 250): array
    {
        $this->send($register, []);

        if ($delay_us > 0) {
            usleep($delay_us);
        }

        $bytes = $this->transport->read($length);

        if ($bytes === false) {
            throw SeesawMiniGamepadException::readFailed($register, $length);
        }

        if (count($bytes) < $length) {
            throw SeesawMiniGamepadException::shortRead($register, $length, count($bytes));
        }

        return array_values($bytes);
    }

    public function write(int $register, array $data): int
    {
        return $this->send($register, $data);
    }

    /** The input wired to IRQ, or null. */
    public function interruptPin(): ?DigitalInTransport
    {
        return $this->irq;
    }

    /** Release IRQ. The bus connection belongs to its driver and stays open. */
    public function close(): void
    {
        $this->irq?->close();
    }

    protected function send(int $register, array $data): int
    {
        $payload = [...array_values($this->splitBytes($register)), ...$data];
        $written = $this->transport->write($payload);

        if ($written !== count($payload)) {
            throw SeesawMiniGamepadException::writeFailed($register, count($payload), $written);
        }

        return $written;
    }
}
