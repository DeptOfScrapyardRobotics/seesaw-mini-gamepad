<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Providers;

use Voyager\NutsAndBolts\ServiceProvider;

/**
 * The gamepad's wiring config lives under the circuits tree: config('circuits.seesaw-mini-gamepad'),
 * published to config/circuits/seesaw-mini-gamepad.php, which the config loader keys the same way.
 */
class SeesawMiniGamepadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/seesaw-mini-gamepad.php', 'circuits.seesaw-mini-gamepad');
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2).'/config/seesaw-mini-gamepad.php' => $this->app->configPath('circuits/seesaw-mini-gamepad.php'),
        ], 'seesaw-mini-gamepad-config');
    }
}
