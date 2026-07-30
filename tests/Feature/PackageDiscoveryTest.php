<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Providers\SeesawMiniGamepadServiceProvider;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;
use Fabricate\Contracts\Actuation\HumanInput\GameController;
use Fabricate\Contracts\Circuits\Attributes\IntegratedCircuit;
use Fabricate\Contracts\NutsAndBolts\BootSequence;

it('advertises v0.6 provider discovery and the circuit slug', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $provider = file_get_contents(dirname(__DIR__, 2).'/src/Providers/SeesawMiniGamepadServiceProvider.php');

    expect($composer['version'])->toBe('0.6.0')
        ->and($composer['autoload']['psr-4'])->toHaveKey(
            'DeptOfScrapyardRobotics\\Actuators\\SeesawMiniGamepad\\',
        )
        ->and($composer['extra']['scrapyard-io']['providers'])->toContain(
            SeesawMiniGamepadServiceProvider::class,
        )
        ->and($provider)->toContain("Circuit::addCircuit('seesaw-mini-gamepad'");
});

it('is an I2C-only bootable Fabricate game controller', function (): void {
    $reflection = new ReflectionClass(SeesawMiniGamepad::class);
    $attributes = $reflection->getAttributes(IntegratedCircuit::class);

    expect(is_subclass_of(SeesawMiniGamepad::class, GameController::class))->toBeTrue()
        ->and(is_subclass_of(SeesawMiniGamepad::class, BootSequence::class))->toBeTrue()
        ->and($attributes)->toHaveCount(1)
        ->and($attributes[0]->getArguments())->toBe(['I2C'])
        ->and(method_exists(SeesawMiniGamepad::class, 'i2c'))->toBeTrue()
        ->and(method_exists(SeesawMiniGamepad::class, 'fromI2CBus'))->toBeTrue()
        ->and(method_exists(SeesawMiniGamepad::class, 'close'))->toBeTrue()
        ->and($reflection->getConstants())->toBe([]);
});
