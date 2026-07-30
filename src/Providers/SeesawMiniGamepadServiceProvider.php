<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Providers;

use DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\SeesawMiniGamepad;
use Fabricate\NutsAndBolts\MagicAliases\Circuit;
use Fabricate\NutsAndBolts\ServiceProvider;

class SeesawMiniGamepadServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Circuit::addCircuit('seesaw-mini-gamepad', SeesawMiniGamepad::class);
    }
}
