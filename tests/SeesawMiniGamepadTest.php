<?php

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadAxis;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Enums\GamepadButton;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\GamepadButtonState;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadConfiguration;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepadException;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\FakeI2CTransport;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support\FakeInterruptPin;
use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Transports\SeesawI2CTransport;
use Voyager\Contracts\IOPools\LoopResources\Timer;

// --- boot ---------------------------------------------------------------------

it('boots like seesaw begin(), then sets the buttons up, byte for byte', function (): void {
    $bus = new FakeI2CTransport;
    $bus->replies = GAMEPAD_BOOT_REPLIES;

    $pad = new SeesawMiniGamepad(new SeesawI2CTransport($bus), new SeesawMiniGamepadConfiguration(reset_wait_ms: 0), boot_now: true);

    expect($pad->hasBooted())->toBeTrue()
        ->and($pad->connected())->toBeTrue()
        ->and($bus->log)->toBe([
            ['w', [0x00, 0x7F, 0xFF]],                // software reset
            ['w', [0x00, 0x01]], ['r', 1],             // hardware ID
            ['w', [0x00, 0x02]], ['r', 4],             // version → product
            ['w', [0x01, 0x03, ...GAMEPAD_MASK]],      // inputs
            ['w', [0x01, 0x0B, ...GAMEPAD_MASK]],      // pull-ups on
            ['w', [0x01, 0x05, ...GAMEPAD_MASK]],      // pull up, not down
            ['w', [0x01, 0x09, ...GAMEPAD_MASK]],      // button interrupts off
        ]);
});

it('boots through a refused reset write: the firmware restarts before acknowledging it', function (): void {
    $bus = new FakeI2CTransport;
    $bus->refuse = [[0x00, 0x7F, 0xFF]];
    $bus->replies = GAMEPAD_BOOT_REPLIES;

    $pad = new SeesawMiniGamepad(new SeesawI2CTransport($bus), new SeesawMiniGamepadConfiguration(reset_wait_ms: 0), boot_now: true);

    expect($pad->hasBooted())->toBeTrue()
        ->and($bus->log[0])->toBe(['w', [0x00, 0x7F, 0xFF]]);
});

it('turns button interrupts on at boot when configured', function (): void {
    $bus = new FakeI2CTransport;
    $bus->replies = GAMEPAD_BOOT_REPLIES;

    new SeesawMiniGamepad(new SeesawI2CTransport($bus), new SeesawMiniGamepadConfiguration(button_interrupts: true, reset_wait_ms: 0), boot_now: true);

    expect(end($bus->log))->toBe(['w', [0x01, 0x08, ...GAMEPAD_MASK]]);
});

it('refuses a chip that is not seesaw, before touching any pin', function (): void {
    $bus = new FakeI2CTransport;
    $bus->replies = [[0xB4]];

    expect(fn () => new SeesawMiniGamepad(new SeesawI2CTransport($bus), new SeesawMiniGamepadConfiguration(reset_wait_ms: 0), boot_now: true))
        ->toThrow(SeesawMiniGamepadException::class, 'hardware ID 0xB4 is not a seesaw chip')
        ->and(count($bus->log))->toBe(3);
});

it('refuses a seesaw that is not the Mini Gamepad', function (): void {
    $bus = new FakeI2CTransport;
    $bus->replies = [[0x87], [0x13, 0xA8, 0x00, 0x00]];

    expect(fn () => new SeesawMiniGamepad(new SeesawI2CTransport($bus), new SeesawMiniGamepadConfiguration(reset_wait_ms: 0), boot_now: true))
        ->toThrow(SeesawMiniGamepadException::class, 'Expected seesaw product 5743 (Mini Gamepad), got 5032');
});

it('boots once, and only when asked', function (): void {
    $bus = new FakeI2CTransport;
    $bus->replies = GAMEPAD_BOOT_REPLIES;
    $pad = new SeesawMiniGamepad(new SeesawI2CTransport($bus), new SeesawMiniGamepadConfiguration(reset_wait_ms: 0));

    expect($bus->log)->toBe([])->and($pad->connected())->toBeFalse();

    $pad->boot();
    $pad->boot();

    expect($bus->log)->toHaveCount(9);
});

it('reads its identity', function (): void {
    [$pad] = gamepad([[0x87], [0x16, 0x6F, 0x7A, 0x97], [0x16, 0x6F, 0x7A, 0x97]]);

    expect($pad->hardware_id)->toBe(0x87)
        ->and($pad->version)->toBe(0x166F7A97)
        ->and($pad->product_id)->toBe(5743);
});

// --- polling -------------------------------------------------------------------

it('polls the buttons, then both joystick axes, on their seesaw registers', function (): void {
    [$pad, $bus] = gamepad(pollReplies(gpioWith(), 512, 512));

    expect($pad->poll())->toBe($pad)
        ->and($bus->log)->toBe([
            ['w', [0x01, 0x04]], ['r', 4],   // GPIO_BULK
            ['w', [0x09, 0x15]], ['r', 2],   // ADC channel 14, X
            ['w', [0x09, 0x16]], ['r', 2],   // ADC channel 15, Y
        ])
        ->and($pad->downButtons())->toBe([]);
});

it('tracks press, hold and release across polls', function (): void {
    [$pad] = gamepad([
        ...pollReplies(gpioWith(GamepadButton::A)),
        ...pollReplies(gpioWith(GamepadButton::A, GamepadButton::START)),
        ...pollReplies(gpioWith(GamepadButton::START)),
    ]);

    $pad->poll();
    expect($pad->isPressed(GamepadButton::A))->toBeTrue()
        ->and($pad->isDown(GamepadButton::A))->toBeTrue()
        ->and($pad->pressedButtons())->toBe([GamepadButton::A]);

    $pad->poll();
    expect($pad->isPressed(GamepadButton::A))->toBeFalse()
        ->and($pad->isDown(GamepadButton::A))->toBeTrue()
        ->and($pad->pressedButtons())->toBe([GamepadButton::START])
        ->and($pad->downButtons())->toBe([GamepadButton::A, GamepadButton::START])
        ->and($pad->chord(GamepadButton::A, GamepadButton::START))->toBeTrue();

    $pad->poll();
    expect($pad->wasReleased(GamepadButton::A))->toBeTrue()
        ->and($pad->isDown(GamepadButton::A))->toBeFalse()
        ->and($pad->releasedButtons())->toBe([GamepadButton::A])
        ->and($pad->heldMs(GamepadButton::A))->toBe(0);
});

it('counts a held button as holding once hold_ms has passed', function (): void {
    [$pad] = gamepad(pollReplies(gpioWith(GamepadButton::B)), config: ['hold_ms' => 5]);

    $pad->poll();
    expect($pad->isHolding(GamepadButton::B))->toBeFalse();

    usleep(10_000);
    expect($pad->isHolding(GamepadButton::B))->toBeTrue()
        ->and($pad->heldMs(GamepadButton::B))->toBeGreaterThanOrEqual(5)
        ->and($pad->holdingButtons())->toBe([GamepadButton::B]);

    $pad->hold_ms = 60_000;
    expect($pad->isHolding(GamepadButton::B))->toBeFalse();
});

it('answers any / all questions, with no buttons meaning every button', function (): void {
    [$pad] = gamepad([
        ...pollReplies(gpioWith(GamepadButton::X, GamepadButton::Y)),
        ...pollReplies(gpioWith(...GamepadButton::cases())),
    ]);

    $pad->poll();
    expect($pad->anyDown())->toBeTrue()
        ->and($pad->anyDown(GamepadButton::A, GamepadButton::X))->toBeTrue()
        ->and($pad->anyDown(GamepadButton::A, GamepadButton::B))->toBeFalse()
        ->and($pad->allDown(GamepadButton::X, GamepadButton::Y))->toBeTrue()
        ->and($pad->allDown())->toBeFalse()
        ->and($pad->anyPressed(GamepadButton::Y))->toBeTrue()
        ->and($pad->anyPressed(GamepadButton::A))->toBeFalse();

    $pad->poll();
    expect($pad->allDown())->toBeTrue()
        ->and($pad->anyPressed(GamepadButton::X))->toBeFalse()
        ->and($pad->anyPressed())->toBeTrue();
});

it('hands out one state object per button, keyed by name', function (): void {
    [$pad] = gamepad();

    $buttons = $pad->buttons();

    expect(array_keys($buttons))->toBe(['SELECT', 'B', 'Y', 'A', 'X', 'START'])
        ->and($buttons['A'])->toBeInstanceOf(GamepadButtonState::class)
        ->and($buttons['A'])->toBe($pad->button(GamepadButton::A))
        ->and($buttons['A']->button)->toBe(GamepadButton::A);
});

it('scales the joystick to -1 … 1, X flipped by default', function (): void {
    [$pad] = gamepad([
        ...pollReplies(gpioWith(), 1023, 0),
        ...pollReplies(gpioWith(), 0, 1023),
    ]);

    $pad->poll();
    expect($pad->x())->toBe(-1.0)
        ->and($pad->y())->toBe(-1.0)
        ->and($pad->axis(GamepadAxis::X))->toBe(-1.0);

    $pad->invert_x = false;
    $pad->invert_y = true;
    $pad->poll();

    expect($pad->axes())->toBe(['x' => -1.0, 'y' => -1.0])
        ->and($pad->invert_x)->toBeFalse()
        ->and($pad->invert_y)->toBeTrue();
});

it('reads the stick near zero at rest', function (): void {
    [$pad] = gamepad(pollReplies(gpioWith(), 507, 501));

    $pad->poll();

    expect(abs($pad->x))->toBeLessThan(0.01)
        ->and(abs($pad->y))->toBeLessThan(0.03);
});

// --- IRQ ------------------------------------------------------------------------

it('skips the button read while IRQ is idle, after one priming read', function (): void {
    $irq = new FakeInterruptPin(4);
    [$pad, $bus] = gamepad([
        ...pollReplies(gpioWith(GamepadButton::A)),
        adc(512), adc(512),
    ], $irq, ['button_interrupts' => true]);

    $pad->poll();
    expect($pad->isPressed(GamepadButton::A))->toBeTrue()
        ->and($irq->reads)->toBe(0);

    $bus->log = [];
    $pad->poll();

    expect($irq->reads)->toBe(1)
        ->and($bus->log)->toBe([['w', [0x09, 0x15]], ['r', 2], ['w', [0x09, 0x16]], ['r', 2]])
        ->and($pad->isDown(GamepadButton::A))->toBeTrue()
        ->and($pad->isPressed(GamepadButton::A))->toBeFalse();
});

it('clears the interrupt flags, then reads the buttons, when IRQ is low', function (): void {
    $irq = new FakeInterruptPin(4);
    [$pad, $bus] = gamepad([
        ...pollReplies(gpioWith()),
        [0x00, 0x00, 0x00, 0x20],
        ...pollReplies(gpioWith(GamepadButton::A)),
    ], $irq, ['button_interrupts' => true]);

    $pad->poll();
    $irq->level = false;
    $bus->log = [];
    $pad->poll();

    expect(array_slice($bus->log, 0, 4))->toBe([['w', [0x01, 0x0A]], ['r', 4], ['w', [0x01, 0x04]], ['r', 4]])
        ->and($pad->pressedButtons())->toBe([GamepadButton::A]);
});

it('ignores IRQ while button interrupts are off, and follows the setting at runtime', function (): void {
    $irq = new FakeInterruptPin(4);
    [$pad, $bus] = gamepad([...pollReplies(gpioWith()), ...pollReplies(gpioWith())], $irq);

    expect($pad->interruptLine())->toBeNull();

    $pad->poll();
    $pad->poll();

    expect($irq->reads)->toBe(0);

    $bus->log = [];
    $pad->button_interrupts = true;

    expect($bus->log)->toBe([['w', [0x01, 0x08, ...GAMEPAD_MASK]]])
        ->and($pad->button_interrupts)->toBeTrue()
        ->and($pad->interruptLine())->toBe($irq);

    $pad->button_interrupts = false;

    expect(end($bus->log))->toBe(['w', [0x01, 0x09, ...GAMEPAD_MASK]])
        ->and($pad->interruptLine())->toBeNull();
});

// --- event loop -----------------------------------------------------------------

it('runs poll() on a named loop timer', function (): void {
    [$pad, $bus] = gamepad(array_merge(...array_fill(0, 50, pollReplies(gpioWith(GamepadButton::SELECT)))));
    $loop = testLoop();
    $polls = 0;
    $loop->every(0.001, function () use (&$polls, $bus, $loop): void {
        $polls = intdiv(count($bus->log), 6);

        if ($polls >= 2) {
            $loop->stop();
        }
    }, 'watcher');

    $timer = $pad->every($loop, 0.001);

    expect($timer)->toBeInstanceOf(Timer::class)->and($bus->log)->toBe([]);

    $loop->run();
    $pad->stop($loop);
    $loop->forget('watcher');

    expect($polls)->toBeGreaterThanOrEqual(2)
        ->and($pad->isDown(GamepadButton::SELECT))->toBeTrue()
        ->and($loop->registry->hasWork())->toBeFalse();
});

it('takes a timer name, so two gamepads poll side by side', function (): void {
    [$left] = gamepad();
    [$right] = gamepad();
    $loop = testLoop();

    $left->every($loop, 0.01, 'left.gamepad');
    $right->every($loop, 0.01, 'right.gamepad');

    expect($loop->registry->hasWork())->toBeTrue();

    $left->stop($loop, 'left.gamepad');
    $right->stop($loop, 'right.gamepad');

    expect($loop->registry->hasWork())->toBeFalse();
});

// --- settings and errors -------------------------------------------------------------

it('refuses a negative hold time and unknown names', function (): void {
    [$pad] = gamepad();

    expect(fn () => $pad->hold_ms = -1)->toThrow(SeesawMiniGamepadException::class, 'hold_ms takes 0 or more; got -1')
        ->and(fn () => $pad->product_id = 1)->toThrow(SeesawMiniGamepadException::class, "Invalid property 'product_id'")
        ->and(fn () => $pad->trigger)->toThrow(SeesawMiniGamepadException::class, "Invalid property 'trigger'")
        ->and(fn () => $pad->config()->get('slave'))->toThrow(SeesawMiniGamepadException::class, "Invalid property 'slave'");
});

it('throws on a short write, a refused read or a short read', function (): void {
    [$pad, $bus] = gamepad([false, [0x00, 0x01]]);

    expect(fn () => $pad->readGPIO())->toThrow(SeesawMiniGamepadException::class, 'register 0x0104: the bus refused a 4 byte read')
        ->and(fn () => $pad->readGPIO())->toThrow(SeesawMiniGamepadException::class, 'register 0x0104: wanted 4 bytes, got 2');

    $bus->short_by = 1;

    expect(fn () => $pad->button_interrupts = true)->toThrow(SeesawMiniGamepadException::class, 'register 0x0108: wanted to write 6 bytes, wrote 5')
        ->and($pad->button_interrupts)->toBeFalse();
});

it('releases IRQ and forgets button state on close, leaving the bus to its driver', function (): void {
    $irq = new FakeInterruptPin(4);
    [$pad, $bus] = gamepad(pollReplies(gpioWith(GamepadButton::A)), $irq);

    $pad->poll();
    $pad->close();

    expect($irq->closed)->toBeTrue()
        ->and($bus->closed)->toBeFalse()
        ->and($pad->downButtons())->toBe([]);
});
