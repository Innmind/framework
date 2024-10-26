<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application;

use Innmind\Framework\{
    Environment,
    Http\Routes,
    Http\Router,
    Http\RequestHandler,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\{
    Container,
    Builder,
    Service,
};
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Router\Route;
use Innmind\Immutable\{
    Maybe,
    Sequence,
};

/**
 * @internal
 * @implements Implementation<ServerRequest, Response>
 */
final class Http implements Implementation
{
    private OperatingSystem $os;
    private Environment $env;
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
     * @param callable(OperatingSystem, Environment): Builder $container
     * @param Sequence<callable(Routes, Container, OperatingSystem, Environment): Routes> $routes
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $mapRequestHandler
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> $notFound
     */
    private function __construct(
        OperatingSystem $os,
        Environment $env,
        callable $container,
        Sequence $routes,
        callable $mapRequestHandler,
        Maybe $notFound,
    ) {
        $this->os = $os;
        $this->env = $env;
        $this->container = $container;
        $this->routes = $routes;
        $this->mapRequestHandler = $mapRequestHandler;
        $this->notFound = $notFound;
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os, Environment $env): self
    {
        /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            $env,
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
        /** @psalm-suppress ImpureFunctionCall Mutation free to force the user to use the returned object */
        return new self(
            $this->os,
            $map($this->env, $this->os),
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
        /** @psalm-suppress ImpureFunctionCall Mutation free to force the user to use the returned object */
        return new self(
            $map($this->os, $this->env),
            $this->env,
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
        $container = $this->container;

        return new self(
            $this->os,
            $this->env,
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
            $this->env,
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
        $previous = $this->mapRequestHandler;

        return new self(
            $this->os,
            $this->env,
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
    public function notFoundRequestHandler(callable $handle): self
    {
        return new self(
            $this->os,
            $this->env,
            $this->container,
            $this->routes,
            $this->mapRequestHandler,
            Maybe::just($handle),
        );
    }

    public function run($input)
    {
        $container = ($this->container)($this->os, $this->env)->build();
        $os = $this->os;
        $env = $this->env;
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
        $handle = ($this->mapRequestHandler)($router, $container, $this->os, $this->env);

        return $handle($input);
    }
}
