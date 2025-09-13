<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application\Async;

use Innmind\Framework\{
    Environment,
    Application\Implementation,
    Http\Router,
};
use Innmind\CLI\{
    Environment as CliEnv,
    Commands,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Async\HttpServer\Command\Serve;
use Innmind\DI\{
    Container,
    Builder,
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
    Maybe,
    Sequence,
    Attempt,
    SideEffect,
};

/**
 * @experimental
 * @internal
 * @implements Implementation<CliEnv, Attempt<CliEnv>>
 */
final class Http implements Implementation
{
    /**
     * @psalm-mutation-free
     *
     * @param \Closure(OperatingSystem, Environment): array{OperatingSystem, Environment} $map
     * @param \Closure(OperatingSystem, Environment): Builder $container
     * @param Sequence<callable(Pipe, Container, OperatingSystem, Environment): Component<SideEffect, Response>> $routes
     * @param \Closure(Component<SideEffect, Response>, Container): Component<SideEffect, Response> $mapRoute
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Attempt<Response>> $notFound
     */
    private function __construct(
        private OperatingSystem $os,
        private \Closure $map,
        private \Closure $container,
        private Sequence $routes,
        private \Closure $mapRoute,
        private Maybe $notFound,
    ) {
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os): self
    {
        /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Attempt<Response>> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            static fn(OperatingSystem $os, Environment $env) => [$os, $env],
            static fn() => Builder::new(),
            Sequence::lazyStartingWith(),
            static fn(Component $component) => $component,
            $notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapEnvironment(callable $map): self
    {
        $previous = $this->map;

        return new self(
            $this->os,
            static function(OperatingSystem $os, Environment $env) use ($previous, $map): array {
                [$os, $env] = $previous($os, $env);
                $env = $map($env, $os);

                return [$os, $env];
            },
            $this->container,
            $this->routes,
            $this->mapRoute,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapOperatingSystem(callable $map): self
    {
        $previous = $this->map;

        return new self(
            $this->os,
            static function(OperatingSystem $os, Environment $env) use ($previous, $map): array {
                [$os, $env] = $previous($os, $env);
                $os = $map($os, $env);

                return [$os, $env];
            },
            $this->container,
            $this->routes,
            $this->mapRoute,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function service(Service $name, callable $definition): self
    {
        $container = $this->container;

        return new self(
            $this->os,
            $this->map,
            static fn(OperatingSystem $os, Environment $env) => $container($os, $env)->add(
                $name,
                static fn($service) => $definition($service, $os, $env),
            ),
            $this->routes,
            $this->mapRoute,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function command(callable $command): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapCommand(callable $map): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function route(callable $handle): self
    {
        return new self(
            $this->os,
            $this->map,
            $this->container,
            ($this->routes)($handle),
            $this->mapRoute,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapRoute(callable $map): self
    {
        $previous = $this->mapRoute;

        return new self(
            $this->os,
            $this->map,
            $this->container,
            $this->routes,
            static fn($component, $get) => $map(
                $previous($component, $get),
                $get,
            ),
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function notFoundRequestHandler(callable $handle): self
    {
        return new self(
            $this->os,
            $this->map,
            $this->container,
            $this->routes,
            $this->mapRoute,
            Maybe::just($handle),
        );
    }

    #[\Override]
    public function run($input)
    {
        $map = $this->map;
        $container = $this->container;
        $routes = $this->routes;
        $notFound = $this->notFound;
        $mapRoute = $this->mapRoute;

        $run = Commands::of(Serve::of(
            $this->os,
            static function(ServerRequest $request, OperatingSystem $os) use (
                $map,
                $container,
                $routes,
                $notFound,
                $mapRoute,
            ): Response {
                $env = Environment::http($request->environment());
                [$os, $env] = $map($os, $env);
                $container = $container($os, $env)->build();
                $pipe = Pipe::new();
                $routes = $routes
                    ->map(static fn($handle) => $handle($pipe, $container, $os, $env))
                    ->map(static fn($component) => $mapRoute($component, $container));
                $router = new Router(
                    $routes,
                    $notFound->map(
                        static fn($handle) => static fn(ServerRequest $request) => $handle(
                            $request,
                            $container,
                            $os,
                            $env,
                        ),
                    ),
                );

                return $router($request);
            },
        ));

        return $run($input);
    }
}
