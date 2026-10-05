<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Providers\SeesawMiniGamepadServiceProvider;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;

/** The provider's config with the app's wiring over it, booted onto the bench's catalog, every slave answering boot. */
function gamepadBench(array $i2c): array
{
    $bench = fakeBench(['circuits' => ['seesaw-mini-gamepad' => ['default_config' => 'i2c', 'configs' => ['i2c' => [
        'driver' => 'fake', 'device' => 1, 'reset_wait_ms' => 0, ...$i2c,
    ]]]]]);
    $bench['i2c']->replies = GAMEPAD_BOOT_REPLIES;
    $provider = new SeesawMiniGamepadServiceProvider($bench['app']);
    $provider->register();
    $provider->boot();

    return $bench;
}

it('conjures a booted gamepad over I2C at 0x50, connecting the bus', function (): void {
    $bench = gamepadBench([]);

    $pad = $bench['app']->make('circuit')->conjure('seesaw-mini-gamepad');

    expect($pad)->toBeInstanceOf(SeesawMiniGamepad::class)
        ->and($pad->hasBooted())->toBeTrue()
        ->and($bench['i2c']->opened)->toBe([1])
        ->and($bench['i2c']->slaves)->toHaveKey('1:80')
        ->and($pad->button_interrupts)->toBeFalse()
        ->and($pad->interruptLine())->toBeNull();
});

it('shares an I2C bus the app already connected', function (): void {
    $bench = gamepadBench([]);
    $bench['app']->make('gpio.i2c')->driver('fake')->connectTo(1)->register();

    $bench['app']->make('circuit')->conjure('seesaw-mini-gamepad');

    expect($bench['i2c']->opened)->toBe([1]);
});

it('wires IRQ after the bus', function (): void {
    $bench = gamepadBench(['irq' => ['enabled' => true, 'driver' => 'fake', 'device' => 0, 'pin' => 4], 'button_interrupts' => true]);

    $pad = $bench['app']->make('circuit')->conjure('seesaw-mini-gamepad');

    expect($pad->button_interrupts)->toBeTrue()
        ->and($pad->interruptLine())->toBe($bench['digital']->inputs['0:4'])
        ->and($bench['digital']->opened)->toBe([0]);
});

it('takes the settings from config, keeping the defaults for null ones', function (): void {
    $bench = gamepadBench(['hold_ms' => 250, 'invert_x' => false, 'button_interrupts' => true, 'invert_y' => null]);

    $pad = $bench['app']->make('circuit')->conjure('seesaw-mini-gamepad');

    expect($pad->hold_ms)->toBe(250)
        ->and($pad->invert_x)->toBeFalse()
        ->and($pad->invert_y)->toBeFalse()
        ->and($pad->button_interrupts)->toBeTrue()
        ->and($pad->interruptLine())->toBeNull();
});

it('builds without booting when boot_now is false', function (): void {
    $bench = gamepadBench(['boot_now' => false]);

    $pad = $bench['app']->make('circuit')->conjure('seesaw-mini-gamepad');

    expect($pad->hasBooted())->toBeFalse()
        ->and($bench['i2c']->slaves['1:80']->log)->toBe([]);
});

