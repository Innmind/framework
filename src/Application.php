<?php
declare(strict_types = 1);

namespace Innmind\Framework;

use Innmind\Framework\Http\{
    Routes,
    RequestHandler,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\{
    Environment as CliEnv,
    Command,
};
use Innmind\DI\Container;
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Environment as HttpEnvironment,
};
use Innmind\Immutable\Map;

final class Application
{
    private Application\Cli|Application\Http $app;

    private function __construct(Application\Cli|Application\Http $app)
    {
        $this->app = $app;
    }

    public static function http(OperatingSystem $os, HttpEnvironment $env): self
    {
        return new self(Application\Http::of($os, Environment::http($env)));
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
        if ($this->app instanceof Application\Cli) {
            return new self($this->app->command($command));
        }

        return $this;
    }

    /**
     * @param callable(Command, Container, OperatingSystem, Environment): Command $map
     */
    public function mapCommand(callable $map): self
    {
        if ($this->app instanceof Application\Cli) {
            return new self($this->app->mapCommand($map));
        }

        return $this;
    }

    /**
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     */
    public function appendRoutes(callable $append): self
    {
        if ($this->app instanceof Application\Http) {
            return new self($this->app->appendRoutes($append));
        }

        return $this;
    }

    /**
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
     */
    public function mapRequestHandler(callable $map): self
    {
        if ($this->app instanceof Application\Http) {
            return new self($this->app->mapRequestHandler($map));
        }

        return $this;
    }

    /**
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
     */
    public function notFoundRequestHandler(callable $handle): self
    {
        if ($this->app instanceof Application\Http) {
            return new self($this->app->notFoundRequestHandler($handle));
        }

        return $this;
    }

    /**
     * @internal
     */
    public function run(CliEnv|ServerRequest $input): CliEnv|Response
    {
        /** @psalm-suppress PossiblyInvalidArgument Let the app crash in case of a misuse */
        return $this->app->run($input);
    }
}
