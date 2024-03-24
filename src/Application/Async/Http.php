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
    private OperatingSystem $os;
    /** @var callable(OperatingSystem, Environment): array{OperatingSystem, Environment} */
    private $map;
    /** @var callable(OperatingSystem, Environment): Builder */
    private $container;
    /** @var Sequence<callable(Routes, Container, OperatingSystem, Environment): Routes> */
    private Sequence $routes;
    /** @var callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler */
    private $mapRequestHandler;
    /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
    private Maybe $notFound;

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): array{OperatingSystem, Environment} $map
     * @param callable(OperatingSystem, Environment): Builder $container
     * @param Sequence<callable(Routes, Container, OperatingSystem, Environment): Routes> $routes
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $mapRequestHandler
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> $notFound
     */
    private function __construct(
        OperatingSystem $os,
        callable $map,
        callable $container,
        Sequence $routes,
        callable $mapRequestHandler,
        Maybe $notFound,
    ) {
        $this->os = $os;
        $this->map = $map;
        $this->container = $container;
        $this->routes = $routes;
        $this->mapRequestHandler = $mapRequestHandler;
        $this->notFound = $notFound;
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
    public function mapEnvironment(callable $map): self
    {
        return new self(
            $this->os,
            function(OperatingSystem $os, Environment $env) use ($map): array {
                [$os, $env] = ($this->map)($os, $env);
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
    public function mapOperatingSystem(callable $map): self
    {
        return new self(
            $this->os,
            function(OperatingSystem $os, Environment $env) use ($map): array {
                [$os, $env] = ($this->map)($os, $env);
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
    public function service(string|Service $name, callable $definition): self
    {
        return new self(
            $this->os,
            $this->map,
            fn(OperatingSystem $os, Environment $env) => ($this->container)($os, $env)->add(
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
    public function command(callable $command): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    public function mapCommand(callable $map): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
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
    public function mapRequestHandler(callable $map): self
    {
        return new self(
            $this->os,
            $this->map,
            $this->container,
            $this->routes,
            fn(
                RequestHandler $handler,
                Container $container,
                OperatingSystem $os,
                Environment $env,
            ) => $map(
                ($this->mapRequestHandler)($handler, $container, $os, $env),
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

    public function run($input)
    {
        $run = Commands::of(Serve::of(
            $this->os,
            function(ServerRequest $request, OperatingSystem $os): Response {
                $env = Environment::http($request->environment());
                [$os, $env] = ($this->map)($os, $env);
                $container = ($this->container)($os, $env)->build();
                $routes = Sequence::lazyStartingWith($this->routes)
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
                    $this->notFound->map(
                        static fn($handle) => static fn(ServerRequest $request) => $handle(
                            $request,
                            $container,
                            $os,
                            $env,
                        ),
                    ),
                );
                $handle = ($this->mapRequestHandler)($router, $container, $os, $env);

                return $handle($request);
            },
        ));

        return $run($input);
    }
}
