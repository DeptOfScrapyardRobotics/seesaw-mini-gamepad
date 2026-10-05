<?php

namespace DeptOfScrapyardRobotics\Actuators\SeesawMiniGamepad\Tests\Support;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Vessel\ControlPanel;

/** A bare container a provider can be handed: paths under one root, providers registered on the spot, boot callbacks run at once. */
final class ConfigPathVessel extends ControlPanel implements FrameworkCore
{
    public function __construct(public readonly string $config_root = '/app/config') {}

    public function version(): string
    {
        return '0.10.0';
    }

    public function basePath(string $path = ''): string
    {
        return dirname($this->config_root).($path === '' ? '' : '/'.$path);
    }

    public function configPath(string $path = ''): string
    {
        return $this->config_root.($path === '' ? '' : '/'.$path);
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database'.($path === '' ? '' : '/'.$path));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage'.($path === '' ? '' : '/'.$path));
    }

    public function register(string|ServiceProvider $provider, bool $force = false): ServiceProvider
    {
        $provider = is_string($provider) ? $this->resolveProvider($provider) : $provider;
        $provider->register();

        return $provider;
    }

    public function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider($this);
    }

    public function booted(callable $callback): void
    {
        $callback($this);
    }

    public function runningUnitTests(): bool
    {
        return true;
    }

    public function registerConfiguredProviders(): void {}

    public function bootstrapWith(array $bootstrappers): void {}

    public function boot(): void {}

    public function hasBeenBootstrapped(): bool
    {
        return true;
    }

    public function environment(string|array ...$environments): bool|string
    {
        return $environments === [] ? 'testing' : in_array('testing', array_merge(...array_map(fn (string|array $e): array => (array) $e, $environments)), true);
    }
}
