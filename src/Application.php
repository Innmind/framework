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
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Router\Route\Variables;

/**
 * @template I of ServerRequest|CliEnv
 * @template O of Response|CliEnv
 */
final class Application
{
    /** @var Application\Implementation<I, O> */
    private Application\Implementation $app;

    /**
     * @psalm-mutation-free
     *
     * @param Application\Implementation<I, O> $app
     */
    private function __construct(Application\Implementation $app)
    {
        $this->app = $app;
    }

    /**
     * @psalm-pure
     *
     * @return self<ServerRequest, Response>
     */
    public static function http(OperatingSystem $os, Environment $env): self
    {
        return new self(Application\Http::of($os, $env));
    }

    /**
     * @psalm-pure
     *
     * @return self<CliEnv, CliEnv>
     */
    public static function cli(OperatingSystem $os, Environment $env): self
    {
        return new self(Application\Cli::of($os, $env));
    }

    /**
     * @psalm-pure
     * @experimental
     *
     * @return self<CliEnv, CliEnv>
     */
    public static function asyncHttp(OperatingSystem $os): self
    {
        return new self(Application\Async\Http::of($os));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Environment, OperatingSystem): Environment $map
     *
     * @return self<I, O>
     */
    public function mapEnvironment(callable $map): self
    {
        return new self($this->app->mapEnvironment($map));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     *
     * @return self<I, O>
     */
    public function mapOperatingSystem(callable $map): self
    {
        return new self($this->app->mapOperatingSystem($map));
    }

    /**
     * @psalm-mutation-free
     *
     * @return self<I, O>
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
     *
     * @return self<I, O>
     */
    public function service(string $name, callable $definition): self
    {
        return new self($this->app->service($name, $definition));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Container, OperatingSystem, Environment): Command $command
     *
     * @return self<I, O>
     */
    public function command(callable $command): self
    {
        return new self($this->app->command($command));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Command, Container, OperatingSystem, Environment): Command $map
     *
     * @return self<I, O>
     */
    public function mapCommand(callable $map): self
    {
        return new self($this->app->mapCommand($map));
    }

    /**
     * @psalm-mutation-free
     *
     * @param literal-string $pattern
     * @param callable(ServerRequest, Variables, Container, OperatingSystem, Environment): Response $handle
     *
     * @return self<I, O>
     */
    public function route(string $pattern, callable $handle): self
    {
        return new self($this->app->route($pattern, $handle));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     *
     * @return self<I, O>
     */
    public function appendRoutes(callable $append): self
    {
        return new self($this->app->appendRoutes($append));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
     *
     * @return self<I, O>
     */
    public function mapRequestHandler(callable $map): self
    {
        return new self($this->app->mapRequestHandler($map));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
     *
     * @return self<I, O>
     */
    public function notFoundRequestHandler(callable $handle): self
    {
        return new self($this->app->notFoundRequestHandler($handle));
    }

    /**
     * @param I $input
     *
     * @return O
     */
    public function run(CliEnv|ServerRequest $input): CliEnv|Response
    {
        return $this->app->run($input);
    }
}
