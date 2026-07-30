<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawCommand;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawPinMode;
use GeneralPurposeIO\I2C\I2CSlave;

class SeesawClient
{
    public function __construct(protected readonly I2CSlave $i2c) {}

    /** @return array{hardware_id: int, version: int, product_id: int} */
    public function status(): array
    {
        $hardware_id = $this->hardwareId();
        $version = $this->version();

        return [
            'hardware_id' => $hardware_id,
            'version' => $version,
            'product_id' => ($version >> 16) & 0xFFFF,
        ];
    }

    public function hardwareId(): int
    {
        return $this->readRegister(SeesawCommand::STATUS_HARDWARE_ID, 1)[0];
    }

    public function version(): int
    {
        return $this->uint32($this->readRegister(SeesawCommand::STATUS_VERSION, 4));
    }

    public function productId(): int
    {
        return ($this->version() >> 16) & 0xFFFF;
    }

    public function pinModeBulk(int $pins, SeesawPinMode $mode): void
    {
        $payload = $this->uint32Bytes($pins);

        match ($mode) {
            SeesawPinMode::OUTPUT => $this->writeRegister(SeesawCommand::GPIO_DIRECTION_SET_BULK, $payload),
            SeesawPinMode::INPUT => $this->writeRegister(SeesawCommand::GPIO_DIRECTION_CLEAR_BULK, $payload),
            SeesawPinMode::INPUT_PULLUP => $this->enablePullups($payload),
        };
    }

    public function digitalReadBulk(int $pins): int
    {
        return $this->uint32($this->readRegister(SeesawCommand::GPIO_BULK, 4)) & $pins;
    }

    public function analogRead(int $pin): int
    {
        $bytes = $this->readRegister(SeesawCommand::ADC_CHANNEL, 2, $pin, 500);

        return (($bytes[0] << 8) | $bytes[1]) & 0xFFFF;
    }

    public function close(): void
    {
        $this->i2c->close();
    }

    /** @param list<int> $payload */
    protected function enablePullups(array $payload): void
    {
        $this->writeRegister(SeesawCommand::GPIO_DIRECTION_CLEAR_BULK, $payload);
        $this->writeRegister(SeesawCommand::GPIO_PULL_ENABLE_SET, $payload);
        $this->writeRegister(SeesawCommand::GPIO_BULK_SET, $payload);
    }

    /**
     * @return list<int>
     */
    protected function readRegister(
        SeesawCommand $command,
        int $length,
        int $channel = 0,
        int $delay_us = 250,
    ): array {
        $register = [$command->module(), $command->register($channel)];
        $written = $this->i2c->write($register);

        if ($written !== count($register)) {
            throw SeesawMiniGamepadException::i2cWriteFailed(count($register), $written);
        }

        if ($delay_us > 0) {
            usleep($delay_us);
        }

        $bytes = $this->i2c->read($length);

        if ($bytes === false || count($bytes) !== $length) {
            throw SeesawMiniGamepadException::i2cReadFailed($length);
        }

        return array_values($bytes);
    }

    /** @param list<int> $payload */
    protected function writeRegister(SeesawCommand $command, array $payload): void
    {
        $bytes = [$command->module(), $command->register(), ...$payload];
        $written = $this->i2c->write($bytes);

        if ($written !== count($bytes)) {
            throw SeesawMiniGamepadException::i2cWriteFailed(count($bytes), $written);
        }
    }

    /** @return list<int> */
    protected function uint32Bytes(int $value): array
    {
        return [
            ($value >> 24) & 0xFF,
            ($value >> 16) & 0xFF,
            ($value >> 8) & 0xFF,
            $value & 0xFF,
        ];
    }

    /** @param list<int> $bytes */
    protected function uint32(array $bytes): int
    {
        return (($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3]) & 0xFFFFFFFF;
    }
}
