<?php
declare(strict_types = 1);

namespace Innmind\Framework;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\{
    Environment as CliEnv,
    Command,
};
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\Router\{
    Component,
    Pipe,
};
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Immutable\{
    Attempt,
    SideEffect,
};

/**
 * @template I of ServerRequest|CliEnv
 * @template O of Response|Attempt<CliEnv>
 */
final class Application
{
    /**
     * @psalm-mutation-free
     *
     * @param Application\Implementation<I, O> $app
     */
    private function __construct(
        private Application\Implementation $app,
    ) {
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
     * @return self<CliEnv, Attempt<CliEnv>>
     */
    public static function cli(OperatingSystem $os, Environment $env): self
    {
        return new self(Application\Cli::of($os, $env));
    }

    /**
     * @psalm-pure
     * @experimental
     *
     * @return self<CliEnv, Attempt<CliEnv>>
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
     * @param callable(Container, OperatingSystem, Environment): object $definition
     *
     * @return self<I, O>
     */
    public function service(Service $name, callable $definition): self
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
     * @param callable(Command, Container): Command $map
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
     * @param Http\Route\Reference|callable(Pipe, Container): Component<SideEffect, Response> $handle
     *
     * @return self<I, O>
     */
    public function route(Http\Route\Reference|callable $handle): self
    {
        if ($handle instanceof Http\Route\Reference) {
            $handle = $handle->route();
        }

        return new self($this->app->route($handle));
    }

    /**
     * @psalm-mutation-free
     *
     * @param class-string<Http\Route\Reference> $routes
     *
     * @return self<I, O>
     */
    public function routes(string $routes): self
    {
        $self = $this;

        foreach ($routes::cases() as $route) {
            $self = $self->route($route);
        }

        return $self;
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Component<SideEffect, Response>, Container): Component<SideEffect, Response> $map
     *
     * @return self<I, O>
     */
    public function mapRoute(callable $map): self
    {
        return new self($this->app->mapRoute($map));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, Container): Attempt<Response> $handle
     *
     * @return self<I, O>
     */
    public function routeNotFound(callable $handle): self
    {
        return new self($this->app->routeNotFound($handle));
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, \Throwable, Container): Attempt<Response> $recover
     *
     * @return self<I, O>
     */
    public function recoverRouteError(callable $recover): self
    {
        return new self($this->app->recoverRouteError($recover));
    }

    /**
     * @param I $input
     *
     * @return O
     */
    public function run(CliEnv|ServerRequest $input): Attempt|Response
    {
        return $this->app->run($input);
    }
}
