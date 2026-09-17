<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\SeesawMiniGamepadI2CAddress;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Providers\SeesawMiniGamepadServiceProvider;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\ConfigPathVessel;
use Voyager\Config\Repository;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Vessel\Vessel;

it('registers the config under circuits.seesaw-mini-gamepad, keeping anything the app already set', function (): void {
    $vessel = new Vessel;
    $vessel->instance('config', new Repository(['circuits' => ['seesaw-mini-gamepad' => ['default_config' => 'bench']]]));

    (new SeesawMiniGamepadServiceProvider($vessel))->register();

    $config = $vessel->make('config');

    expect($config->get('circuits.seesaw-mini-gamepad.default_config'))->toBe('bench')
        ->and($config->get('circuits.seesaw-mini-gamepad.configs.i2c.slave'))->toBe(SeesawMiniGamepadI2CAddress::DEFAULT->value)
        ->and($config->get('circuits.seesaw-mini-gamepad.configs.i2c.irq.enabled'))->toBeFalse()
        ->and($config->has('seesaw-mini-gamepad'))->toBeFalse();
});

it('leaves other circuits config beside its own key untouched', function (): void {
    $vessel = new Vessel;
    $vessel->instance('config', new Repository(['circuits' => ['vl6180x' => ['default_config' => 'i2c']]]));

    (new SeesawMiniGamepadServiceProvider($vessel))->register();

    $config = $vessel->make('config');

    expect($config->get('circuits.vl6180x'))->toBe(['default_config' => 'i2c'])
        ->and($config->get('circuits.seesaw-mini-gamepad.default_config'))->toBe('i2c');
});

it('publishes the config file into config/circuits under the seesaw-mini-gamepad-config tag', function (): void {
    $app = new ConfigPathVessel('/app/config');
    $app->instance('config', new Repository);

    $provider = new SeesawMiniGamepadServiceProvider($app);
    $provider->register();
    $provider->boot();

    $root = dirname(__DIR__, 2);

    expect(ServiceProvider::pathsToPublish(SeesawMiniGamepadServiceProvider::class, 'seesaw-mini-gamepad-config'))->toBe([
        "{$root}/config/seesaw-mini-gamepad.php" => '/app/config/circuits/seesaw-mini-gamepad.php',
    ]);
});
