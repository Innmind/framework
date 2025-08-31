<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application\Async;

use Innmind\Framework\{
    Environment,
    Application\Implementation,
    Http\Routes,
    Http\Router,
    Http\RequestHandler,
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
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Router\{
    Route,
};
use Innmind\Immutable\{
    Maybe,
    Sequence,
};

/**
 * @experimental
 * @internal
 * @implements Implementation<CliEnv, CliEnv>
 */
final class Http implements Implementation
{
    /**
     * @psalm-mutation-free
     *
     * @param \Closure(OperatingSystem, Environment): array{OperatingSystem, Environment} $map
     * @param \Closure(OperatingSystem, Environment): Builder $container
     * @param Sequence<callable(Routes, Container, OperatingSystem, Environment): Routes> $routes
     * @param \Closure(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $mapRequestHandler
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> $notFound
     */
    private function __construct(
        private OperatingSystem $os,
        private \Closure $map,
        private \Closure $container,
        private Sequence $routes,
        private \Closure $mapRequestHandler,
        private Maybe $notFound,
    ) {
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os): self
    {
        /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            static fn(OperatingSystem $os, Environment $env) => [$os, $env],
            static fn() => Builder::new(),
            Sequence::of(),
            static fn(RequestHandler $handler) => $handler,
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
            $this->mapRequestHandler,
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
            $this->mapRequestHandler,
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
            $this->mapRequestHandler,
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
    public function route(string $pattern, callable $handle): self
    {
        return $this->appendRoutes(
            static fn($routes, $container, $os, $env) => $routes->add(
                Route::literal($pattern)->handle(static fn($request, $variables) => $handle(
                    $request,
                    $variables,
                    $container,
                    $os,
                    $env,
                )),
            ),
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function appendRoutes(callable $append): self
    {
        return new self(
            $this->os,
            $this->map,
            $this->container,
            ($this->routes)($append),
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapRequestHandler(callable $map): self
    {
        $previous = $this->mapRequestHandler;

        return new self(
            $this->os,
            $this->map,
            $this->container,
            $this->routes,
            static fn(
                RequestHandler $handler,
                Container $container,
                OperatingSystem $os,
                Environment $env,
            ) => $map(
                $previous($handler, $container, $os, $env),
                $container,
                $os,
                $env,
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
            $this->mapRequestHandler,
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
        $mapRequestHandler = $this->mapRequestHandler;

        $run = Commands::of(Serve::of(
            $this->os,
            static function(ServerRequest $request, OperatingSystem $os) use (
                $map,
                $container,
                $routes,
                $notFound,
                $mapRequestHandler,
            ): Response {
                $env = Environment::http($request->environment());
                [$os, $env] = $map($os, $env);
                $container = $container($os, $env)->build();
                $routes = Sequence::lazyStartingWith($routes)
                    ->flatMap(static fn($routes) => $routes)
                    ->map(static fn($provide) => $provide(
                        Routes::lazy(),
                        $container,
                        $os,
                        $env,
                    ))
                    ->flatMap(static fn($routes) => $routes->toSequence());
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
                $handle = $mapRequestHandler($router, $container, $os, $env);

                return $handle($request);
            },
        ));

        return $run($input);
    }
}
