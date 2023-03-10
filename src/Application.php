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
};

final class Application
{
    private Application\Cli|Application\Http|Application\Async\Http $app;

    /**
     * @psalm-mutation-free
     */
    private function __construct(Application\Cli|Application\Http|Application\Async\Http $app)
    {
        $this->app = $app;
    }

    /**
     * @psalm-pure
     */
    public static function http(OperatingSystem $os, Environment $env): self
    {
        return new self(Application\Http::of($os, $env));
    }

    /**
     * @psalm-pure
     */
    public static function cli(OperatingSystem $os, Environment $env): self
    {
        return new self(Application\Cli::of($os, $env));
    }

    /**
     * @psalm-pure
     * @experimental
     */
    public static function asyncHttp(OperatingSystem $os): self
    {
        return new self(Application\Async\Http::of($os));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Environment, OperatingSystem): Environment $map
     */
    public function mapEnvironment(callable $map): self
    {
        return new self($this->app->mapEnvironment($map));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     */
    public function mapOperatingSystem(callable $map): self
    {
        return new self($this->app->mapOperatingSystem($map));
    }

    /**
     * @psalm-mutation-free
     */
    public function map(Middleware $map): self
    {
        /** @psalm-suppress ImpureMethodCall Mutation free to force the user to use the returned object */
        return $map($this);
    }

    /**
     * @psalm-mutation-free
     *
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
     */
    public function service(string $name, callable $definition): self
    {
        return new self($this->app->service($name, $definition));
    }

    /**
     * @psalm-mutation-free
     *
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
     * @psalm-mutation-free
     *
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
     * @psalm-mutation-free
     *
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     */
    public function appendRoutes(callable $append): self
    {
        if (
            $this->app instanceof Application\Http ||
            $this->app instanceof Application\Async\Http
        ) {
            return new self($this->app->appendRoutes($append));
        }

        return $this;
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
     */
    public function mapRequestHandler(callable $map): self
    {
        if (
            $this->app instanceof Application\Http ||
            $this->app instanceof Application\Async\Http
        ) {
            return new self($this->app->mapRequestHandler($map));
        }

        return $this;
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
     */
    public function notFoundRequestHandler(callable $handle): self
    {
        if (
            $this->app instanceof Application\Http ||
            $this->app instanceof Application\Async\Http
        ) {
            return new self($this->app->notFoundRequestHandler($handle));
        }

        return $this;
    }

    public function run(CliEnv|ServerRequest $input): CliEnv|Response
    {
        /** @psalm-suppress PossiblyInvalidArgument Let the app crash in case of a misuse */
        return $this->app->run($input);
    }
}
