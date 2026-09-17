<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support;

use Closure;
use GeneralPurposeIO\Contracts\Core\ByteSource;
use GeneralPurposeIO\Contracts\Core\EdgeSource;
use GeneralPurposeIO\Contracts\Core\GPIOResourceDriver;
use GeneralPurposeIO\Contracts\Core\Recurrence;
use Voyager\IOPools\Presumption;

/** Records every() registrations so a test can run the recurrence by hand. */
final class FakeGPIOResource implements GPIOResourceDriver
{
    /** @var array<string, array{Recurrence, Closure}> */
    public array $recurrences = [];

    public function runRecurrence(string $name): mixed
    {
        return ($this->recurrences[$name][1])();
    }

    public function every(string $name, Closure $work, int $ticks = 1): Recurrence
    {
        $recurrence = new Recurrence($name, $ticks);
        $this->recurrences[$name] = [$recurrence, $work];

        return $recurrence;
    }

    public function recurring(string $name): ?Recurrence { return $this->recurrences[$name][0] ?? null; }
    public function tick(): void {}
    public function watch(EdgeSource $source, bool $rising = true, bool $falling = false): static { return $this; }
    public function unwatch(EdgeSource $source): static { return $this; }
    public function receive(ByteSource $source, int $max_bytes = 4096): static { return $this; }
    public function stopReceiving(ByteSource $source): static { return $this; }
    public function defer(string $name, Closure $work, ?Closure $envelope = null): Presumption { return new Presumption($name); }
    public function inFlight(string $name): ?Presumption { return null; }
    public function stream(string $name, Closure $write, string $bytes, int $chunk): Presumption { return new Presumption($name); }
    public function streaming(string $name): ?Presumption { return null; }
}
