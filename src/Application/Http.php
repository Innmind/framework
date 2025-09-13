<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application;

use Innmind\Framework\{
    Environment,
    Http\Router,
};
use Innmind\OperatingSystem\OperatingSystem;
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
    SideEffect,
    Attempt,
};

/**
 * @internal
 * @implements Implementation<ServerRequest, Response>
 */
final class Http implements Implementation
{
    /**
     * @psalm-mutation-free
     *
     * @param \Closure(OperatingSystem, Environment): Builder $container
     * @param Sequence<callable(Pipe, Container, OperatingSystem, Environment): Component<SideEffect, Response>> $routes
     * @param \Closure(Component<SideEffect, Response>, Container): Component<SideEffect, Response> $mapRoute
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Attempt<Response>> $notFound
     */
    private function __construct(
        private OperatingSystem $os,
        private Environment $env,
        private \Closure $container,
        private Sequence $routes,
        private \Closure $mapRoute,
        private Maybe $notFound,
    ) {
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os, Environment $env): self
    {
        /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Attempt<Response>> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            $env,
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
        /** @psalm-suppress ImpureFunctionCall Mutation free to force the user to use the returned object */
        return new self(
            $this->os,
            $map($this->env, $this->os),
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
        /** @psalm-suppress ImpureFunctionCall Mutation free to force the user to use the returned object */
        return new self(
            $map($this->os, $this->env),
            $this->env,
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
            $this->env,
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
            $this->env,
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
            $this->env,
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
            $this->env,
            $this->container,
            $this->routes,
            $this->mapRoute,
            Maybe::just($handle),
        );
    }

    #[\Override]
    public function run($input)
    {
        $container = ($this->container)($this->os, $this->env)->build();
        $os = $this->os;
        $env = $this->env;
        $mapRoute = $this->mapRoute;
        $pipe = Pipe::new();
        $routes = $this
            ->routes
            ->map(static fn($handle) => $handle($pipe, $container, $os, $env))
            ->map(static fn($component) => $mapRoute($component, $container));
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

        return $router($input);
    }
}
