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
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
};
use Innmind\Immutable\Maybe;

final class Http
{
    private OperatingSystem $os;
    private Environment $env;
    /** @var callable(OperatingSystem, Environment): Builder */
    private $container;
    /** @var callable(Routes, Container, OperatingSystem, Environment): Routes */
    private $routes;
    /** @var callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler */
    private $mapRequestHandler;
    /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
    private Maybe $notFound;

    /**
     * @param callable(OperatingSystem, Environment): Builder $container
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $routes
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $mapRequestHandler
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> $notFound
     */
    private function __construct(
        OperatingSystem $os,
        Environment $env,
        callable $container,
        callable $routes,
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

    public static function of(OperatingSystem $os, Environment $env): self
    {
        /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            $env,
            static fn() => Builder::new(),
            static fn(Routes $routes) => $routes,
            static fn(RequestHandler $handler) => $handler,
            $notFound,
        );
    }

    /**
     * @param callable(Environment, OperatingSystem): Environment $map
     */
    public function mapEnvironment(callable $map): self
    {
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
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     */
    public function mapOperatingSystem(callable $map): self
    {
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
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
     */
    public function service(string $name, callable $definition): self
    {
        return new self(
            $this->os,
            $this->env,
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
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     */
    public function appendRoutes(callable $append): self
    {
        return new self(
            $this->os,
            $this->env,
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
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
     */
    public function mapRequestHandler(callable $map): self
    {
        return new self(
            $this->os,
            $this->env,
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
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
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

    public function run(ServerRequest $request): Response
    {
        $container = ($this->container)($this->os, $this->env)->build();
        $routes = ($this->routes)(
            Routes::lazy(),
            $container,
            $this->os,
            $this->env,
        );
        $router = new Router(
            $routes,
            $this->notFound->map(
                fn($handle) => fn(ServerRequest $request) => $handle(
                    $request,
                    $container,
                    $this->os,
                    $this->env,
                ),
            ),
        );
        $handle = ($this->mapRequestHandler)($router, $container, $this->os, $this->env);

        return $handle($request);
    }
}
