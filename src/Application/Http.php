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
     * @param Sequence<callable(Pipe, Container): Component<SideEffect, Response>> $routes
     * @param \Closure(Component<SideEffect, Response>, Container): Component<SideEffect, Response> $mapRoute
     * @param Maybe<callable(ServerRequest, Container): Attempt<Response>> $notFound
     * @param \Closure(ServerRequest, \Throwable, Container): Attempt<Response> $recover
     */
    private function __construct(
        private OperatingSystem $os,
        private Environment $env,
        private \Closure $container,
        private Sequence $routes,
        private \Closure $mapRoute,
        private Maybe $notFound,
        private \Closure $recover,
    ) {
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os, Environment $env): self
    {
        /** @var Maybe<callable(ServerRequest, Container): Attempt<Response>> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            $env,
            static fn() => Builder::new(),
            Sequence::lazyStartingWith(),
            static fn(Component $component) => $component,
            $notFound,
            static fn(ServerRequest $request, \Throwable $e) => Attempt::error($e),
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
            $this->recover,
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
            $this->recover,
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
            $this->recover,
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
            $this->recover,
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
            $this->recover,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function routeNotFound(callable $handle): self
    {
        return new self(
            $this->os,
            $this->env,
            $this->container,
            $this->routes,
            $this->mapRoute,
            Maybe::just($handle),
            $this->recover,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function recoverRouteError(callable $recover): self
    {
        $previous = $this->recover;

        return new self(
            $this->os,
            $this->env,
            $this->container,
            $this->routes,
            $this->mapRoute,
            $this->notFound,
            static fn($request, $e, $container) => $previous($request, $e, $container)->recover(
                static fn($e) => $recover($request, $e, $container),
            ),
        );
    }

    #[\Override]
    public function run($input)
    {
        $container = ($this->container)($this->os, $this->env)->build();
        $mapRoute = $this->mapRoute;
        $recover = $this->recover;
        $pipe = Pipe::new();
        $routes = $this
            ->routes
            ->map(static fn($handle) => $handle($pipe, $container))
            ->map(static fn($component) => $mapRoute($component, $container));
        $router = new Router(
            $routes,
            $this->notFound->map(
                static fn($handle) => static fn(ServerRequest $request) => $handle(
                    $request,
                    $container,
                ),
            ),
            static fn($request, $e) => $recover($request, $e, $container),
        );

        return $router($input);
    }
}
