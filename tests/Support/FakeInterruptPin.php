<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support;

use GeneralPurposeIO\Contracts\Digital\DigitalEdgeEvent;
use GeneralPurposeIO\Digital\DigitalInputTransport;

/** GPIO1 with a scripted level: listen() hands over `listen_edges` one at a time and moves the level to `level_on_edge`. */
final class FakeInterruptPin extends DigitalInputTransport
{
    public bool $closed = false;

    public bool $level = true;

    public ?bool $level_on_edge = null;

    public int $reads = 0;

    /** @var list<DigitalEdgeEvent> */
    public array $listen_edges = [];

    /** @var list<array{int, bool, bool}> timeout and edge flags handed to listen() */
    public array $listens = [];

    public function read(): bool
    {
        $this->reads++;

        return $this->level;
    }

    public function pollEdges(bool $rising_events, bool $falling_events): array
    {
        return [];
    }

    public function listen(int $timeout, bool $rising_events, bool $falling_events): ?DigitalEdgeEvent
    {
        $this->listens[] = [$timeout, $rising_events, $falling_events];
        $edge = array_shift($this->listen_edges);

        if (! is_null($edge) && ! is_null($this->level_on_edge)) {
            $this->level = $this->level_on_edge;
        }

        return $edge;
    }

    protected function drainEdges(): array
    {
        return [];
    }

    protected function awaitEdges(int $timeout_ms): void {}

    protected function edgeStreams(): array
    {
        return [];
    }

    protected function samplingInterval(): ?float
    {
        return null;
    }

    protected function release(): void
    {
        $this->closed = true;
    }
}
