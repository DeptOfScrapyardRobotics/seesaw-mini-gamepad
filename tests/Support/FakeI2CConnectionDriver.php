<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support;

use GeneralPurposeIO\I2C\I2CConnectionDriver;
use GeneralPurposeIO\I2C\I2CConnectionFactory;

/** Hands out FakeI2CTransports answering from one script, and keeps each one, keyed device:address. */
final class FakeI2CConnectionDriver extends I2CConnectionDriver
{
    /** @var list<string|int> every bus connectTo() opened */
    public array $opened = [];

    /** @var array<string, FakeI2CTransport> */
    public array $slaves = [];

    /** @var list<list<int>|false> replies every slave handed out from now on starts with */
    public array $replies = [];

    protected function newConnection(int|string $device): I2CConnectionFactory
    {
        $this->opened[] = $device;

        return new FakeI2CConnectionFactory($device, $this);
    }

    protected function getTransport(string|int $device, int $slave_address): FakeI2CTransport
    {
        $transport = new FakeI2CTransport($slave_address);
        $transport->replies = $this->replies;

        return $this->slaves["{$device}:{$slave_address}"] = $transport;
    }

    protected function closeConnection(mixed $handle): void {}
}
