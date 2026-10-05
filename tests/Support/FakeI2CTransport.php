<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support;

use GeneralPurposeIO\I2C\I2CTransport;

/** Logs every write and read in order; answers read() from a queue of scripted replies. */
final class FakeI2CTransport extends I2CTransport
{
    /** @var list<array{0: 'w', 1: list<int>}|array{0: 'r', 1: int}> */
    public array $log = [];

    /** @var list<list<int>|false> */
    public array $replies = [];

    /** Bytes write() claims it sent short of the payload. */
    public int $short_by = 0;

    /** @var list<list<int>> payloads write() answers -1 for, as a NACK */
    public array $refuse = [];

    public bool $closed = false;

    public function __construct(int $address = 0x50)
    {
        parent::__construct($address);
    }

    public function handle(): string
    {
        return 'fake';
    }

    public function probe(): bool
    {
        return true;
    }

    public function read(int $len): array|false
    {
        $this->log[] = ['r', $len];

        return array_shift($this->replies) ?? false;
    }

    public function write(array|string $data): int
    {
        $bytes = is_array($data) ? array_values($data) : array_values(unpack('C*', $data));
        $this->log[] = ['w', $bytes];

        return in_array($bytes, $this->refuse, true) ? -1 : count($bytes) - $this->short_by;
    }

    public function writeRead(array|string $bytes_to_write, int $bytes_to_read): array|false
    {
        return false;
    }

    public function bulkWrite(array|string $messages): array|false
    {
        return false;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    protected function release(): void
    {
        $this->closed = true;
    }
}
