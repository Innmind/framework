<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application\Async;

use Innmind\Framework\{
    Environment,
    Http\Routes,
    Http\Router,
    Http\RequestHandler,
};
use Innmind\CLI\{
    Environment as CliEnv,
    Commands,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    OperatingSystem\Unix,
};
use Innmind\Async\HttpServer\Command\Serve;
use Innmind\DI\{
    Container,
    Builder,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
};
use Innmind\Router\{
    Route,
    Route\Variables,
};
use Innmind\Immutable\Maybe;

/**
 * @experimental
 */
final class Http
{
    private OperatingSystem $os;
    /** @var callable(OperatingSystem, Environment): array{OperatingSystem, Environment} */
    private $map;
    /** @var callable(OperatingSystem, Environment): Builder */
    private $container;
    /** @var callable(Routes, Container, OperatingSystem, Environment): Routes */
    private $routes;
    /** @var callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler */
    private $mapRequestHandler;
    /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
    private Maybe $notFound;

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): array{OperatingSystem, Environment} $map
     * @param callable(OperatingSystem, Environment): Builder $container
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $routes
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $mapRequestHandler
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> $notFound
     */
    private function __construct(
        OperatingSystem $os,
        callable $map,
        callable $container,
        callable $routes,
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
            static fn(Routes $routes) => $routes,
            static fn(RequestHandler $handler) => $handler,
            $notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Environment, OperatingSystem): Environment $map
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
     *
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
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
     *
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
     */
    public function service(string $name, callable $definition): self
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
     *
     * @param literal-string $pattern
     * @param callable(ServerRequest, Variables, Container, OperatingSystem, Environment): Response $handle
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
     *
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     */
    public function appendRoutes(callable $append): self
    {
        return new self(
            $this->os,
            $this->map,
            $this->container,
            fn(
                Routes $routes,
                Container $container,
                OperatingSystem $os,
                Environment $env,
            ) => $append(
                ($this->routes)($routes, $container, $os, $env),
                $container,
                $os,
                $env,
            ),
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
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
     *
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
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

    public function run(CliEnv $env): CliEnv
    {
        $run = Commands::of(Serve::of(
            $this->os,
            function(ServerRequest $request, OperatingSystem $os): Response {
                $env = Environment::http($request->environment());
                [$os, $env] = ($this->map)($os, $env);
                $container = ($this->container)($os, $env)->build();
                $routes = ($this->routes)(
                    Routes::lazy(),
                    $container,
                    $os,
                    $env,
                );
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

        return $run($env);
    }
}
