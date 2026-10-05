<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadConfiguration;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\FakeI2CTransport;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\FakeInterruptPin;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\ConfigPathVessel;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\FakeDigitalIOConnectionDriver;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\FakeI2CConnectionDriver;
use GeneralPurposeIO\Digital\DigitalOConnectionManager;
use GeneralPurposeIO\I2C\I2CConnectionManager;
use GeneralPurposeIO\IntegratedCircuits\CircuitRegistry;
use Voyager\Config\Repository;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\Vessel\ControlPanel;

/*
| Proven against a recording fake I2C transport and a fake IRQ pin: every
| byte the seesaw would see, every byte it would answer, in order. Nothing
| here touches a bus. The live check is a Mini Gamepad at 0x50 on the Pi 5.
*/

/*
| A Venusian app's core defines config() over the container's config repository;
| CircuitRegistry::conjure() calls it. A package suite has no core, so this
| stands in for it the same way.
*/
if (! function_exists('config')) {
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        $config = ControlPanel::getInstance()->make('config');

        return match (true) {
            is_null($key) => $config,
            is_array($key) => $config->set($key),
            default => $config->get($key, $default),
        };
    }
}

/** A loop on the select backend, the way IOPools builds one. */
function testLoop(int $pace_ms = 16): EventLoop
{
    $registry = new ResourceRegistry;

    return new EventLoop($registry, new LoopWaiter($registry, new StreamSelectWaiterBackend, $pace_ms * 1_000_000), new GuzzlePromiseEngine);
}

/**
 * The shared container as an app sets it up: config, the circuit catalog, and the I2C and digital managers, each
 * with a 'fake' driver.
 *
 * @return array{i2c: FakeI2CConnectionDriver, digital: FakeDigitalIOConnectionDriver, app: ConfigPathVessel}
 */
function fakeBench(array $config = []): array
{
    $app = new ConfigPathVessel;
    $app->registerInstance('config', new Repository($config));
    $app->registerInstance('circuit', new CircuitRegistry);
    ControlPanel::setInstance($app);

    $bench = ['i2c' => new FakeI2CConnectionDriver, 'digital' => new FakeDigitalIOConnectionDriver, 'app' => $app];

    $app->registerInstance('gpio.i2c', (new I2CConnectionManager($app))->extend('fake', fn () => $bench['i2c']));
    $app->registerInstance('gpio.digital', (new DigitalOConnectionManager($app))->extend('fake', fn () => $bench['digital']));

    return $bench;
}

pest()->afterEach(function (): void {
    ControlPanel::setInstance(null);
})->in(__DIR__);

/** Every button pin: 0, 1, 2, 5, 6, 16. */
const GAMEPAD_MASK = [0x00, 0x01, 0x00, 0x67];

/** Version 0x166F7A97: product 5743, as read from the bench gamepad. */
const GAMEPAD_BOOT_REPLIES = [[0x87], [0x16, 0x6F, 0x7A, 0x97]];

/** A GPIO_BULK reply with these buttons pressed (low) and every other pin high. */
function gpioWith(GamepadButton ...$pressed): array
{
    $value = 0xFFFFFFFF;

    foreach ($pressed as $button) {
        $value &= ~$button->mask();
    }

    return [($value >> 24) & 0xFF, ($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
}

function adc(int $value): array
{
    return [$value >> 8, $value & 0xFF];
}

/** One poll's replies: buttons, then X, then Y. */
function pollReplies(array $gpio, int $x = 512, int $y = 512): array
{
    return [$gpio, adc($x), adc($y)];
}

/** @return array{0: SeesawMiniGamepad, 1: FakeI2CTransport} booted, boot traffic cleared */
function gamepad(array $replies = [], ?FakeInterruptPin $irq = null, array $config = []): array
{
    $bus = new FakeI2CTransport;
    $bus->replies = [...GAMEPAD_BOOT_REPLIES, ...$replies];
    $pad = new SeesawMiniGamepad(
        new SeesawI2CTransport($bus, $irq),
        new SeesawMiniGamepadConfiguration(...['reset_wait_ms' => 0, ...$config]),
        boot_now: true,
    );
    $bus->log = [];

    return [$pad, $bus];
}
