<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawGamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException;
use Fabricate\Contracts\Actuation\HumanInput\GameControllerAxis;
use Fabricate\Contracts\Actuation\Interfaces\Button;
use GeneralPurposeIO\I2C\Drivers\I2CDriver;
use GeneralPurposeIO\I2C\I2CSlave;

final class FakeSeesawI2CDriver extends I2CDriver
{
    /** @var list<list<int>> */
    public array $writes = [];

    /** @var list<list<int>|false> */
    public array $responses = [];

    public bool $closed = false;

    public function probe(int $address): bool
    {
        return true;
    }

    public function read(int $address, int $len): array|false
    {
        return array_shift($this->responses) ?? false;
    }

    public function write(int $address, array|string $data): int
    {
        $bytes = is_string($data) ? array_values(unpack('C*', $data)) : array_values($data);
        $this->writes[] = $bytes;

        return count($bytes);
    }

    public function writeRead(int $address, array|string $bytes_to_write, int $bytes_to_read): array|false
    {
        $this->write($address, $bytes_to_write);

        return $this->read($address, $bytes_to_read);
    }

    public function bulkWrite(int $address, array|string $messages): array|false
    {
        return false;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

/** @return array{SeesawMiniGamepad, FakeSeesawI2CDriver} */
function miniGamepadFixture(int $product_id = 5743): array
{
    $driver = new FakeSeesawI2CDriver;
    $driver->responses = [
        [0x84],
        [($product_id >> 8) & 0xFF, $product_id & 0xFF, 0x00, 0x01],
    ];
    $gamepad = SeesawMiniGamepad::fromI2CBus(new I2CSlave(0x50, $driver), boot_now: false);

    return [$gamepad, $driver];
}

it('boots product 5743 and configures every button with pullups', function (): void {
    [$gamepad, $driver] = miniGamepadFixture();

    $gamepad->boot();

    expect($gamepad->hasBooted())->toBeTrue()
        ->and($gamepad->connected())->toBeTrue()
        ->and($driver->writes)->toBe([
            [0x00, 0x01],
            [0x00, 0x02],
            [0x01, 0x03, 0x00, 0x01, 0x00, 0x67],
            [0x01, 0x0B, 0x00, 0x01, 0x00, 0x67],
            [0x01, 0x05, 0x00, 0x01, 0x00, 0x67],
        ]);
});

it('decodes active-low buttons from one bulk snapshot per poll', function (): void {
    [$gamepad, $driver] = miniGamepadFixture();
    $gamepad->boot();
    $driver->responses = [
        [0x00, 0x01, 0x00, 0x47],
        [0x02, 0x00],
        [0x02, 0x00],
    ];

    $gamepad->poll();

    $bulk_reads = array_values(array_filter(
        $driver->writes,
        static fn (array $write): bool => $write === [0x01, 0x04],
    ));

    expect($bulk_reads)->toHaveCount(1)
        ->and($gamepad->button('a'))->toBeInstanceOf(Button::class)
        ->and($gamepad->button('a'))->toBeInstanceOf(SeesawGamepadButton::class)
        ->and($gamepad->isDown('a'))->toBeTrue()
        ->and($gamepad->isDown('south'))->toBeTrue()
        ->and($gamepad->isDown('b'))->toBeFalse()
        ->and($gamepad->downLabels())->toBe(['a']);
});

it('normalizes right as positive X and up as negative Y', function (): void {
    [$gamepad, $driver] = miniGamepadFixture();
    $gamepad->boot();
    $driver->responses = [
        [0x00, 0x01, 0x00, 0x67],
        [0x00, 0x00],
        [0x00, 0x00],
    ];

    $gamepad->poll();

    expect($gamepad->axis(GameControllerAxis::LEFT_X))->toBe(1.0)
        ->and($gamepad->axis(GameControllerAxis::LEFT_Y))->toBe(-1.0)
        ->and($gamepad->axis(GameControllerAxis::RIGHT_X))->toBe(0.0)
        ->and($gamepad->axis(GameControllerAxis::RIGHT_Y))->toBe(0.0)
        ->and($gamepad->axis(GameControllerAxis::LEFT_TRIGGER))->toBe(0.0)
        ->and($gamepad->axis(GameControllerAxis::RIGHT_TRIGGER))->toBe(0.0)
        ->and($driver->writes)->toContain([0x09, 0x15], [0x09, 0x16]);
});

it('rejects a seesaw carrying the wrong product firmware', function (): void {
    [$gamepad] = miniGamepadFixture(1234);

    expect(fn () => $gamepad->boot())
        ->toThrow(SeesawMiniGamepadException::class, 'Expected seesaw product PID 5743, received 1234.');
});
