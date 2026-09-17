<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support;

use Voyager\Vessel\Vessel;

/** A bare container that answers configPath(), the one Application method a publishing provider calls. */
final class ConfigPathVessel extends Vessel
{
    public function __construct(public readonly string $config_root) {}

    public function configPath(string $path = ''): string
    {
        return $this->config_root.($path === '' ? '' : '/'.$path);
    }
}
