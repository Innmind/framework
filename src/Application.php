<?php
declare(strict_types = 1);

namespace Innmind\Framework;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\{
    Environment as CliEnv,
    Command,
};
use Innmind\DI\Container;
use Innmind\Immutable\Map;

final class Application
{
    private Application\Cli $app;

    private function __construct(Application\Cli $app)
    {
        $this->app = $app;
    }

    /**
     * @param Map<string, string> $env
     */
    public static function cli(OperatingSystem $os, Map $env): self
    {
        return new self(Application\Cli::of($os, Environment::of($env)));
    }

    /**
     * @param callable(Environment, OperatingSystem): Environment $map
     */
    public function mapEnvironment(callable $map): self
    {
        return new self($this->app->mapEnvironment($map));
    }

    /**
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     */
    public function mapOperatingSystem(callable $map): self
    {
        return new self($this->app->mapOperatingSystem($map));
    }

    public function map(Middleware $map): self
    {
        return $map($this);
    }

    /**
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
     */
    public function service(string $name, callable $definition): self
    {
        return new self($this->app->service($name, $definition));
    }

    /**
     * @param callable(Container, OperatingSystem, Environment): Command $command
     */
    public function command(callable $command): self
    {
        return new self($this->app->command($command));
    }

    /**
     * @param callable(Command, Container, OperatingSystem, Environment): Command $map
     */
    public function mapCommand(callable $map): self
    {
        return new self($this->app->mapCommand($map));
    }

    public function runCli(CliEnv $env): CliEnv
    {
        return $this->app->run($env);
    }
}
