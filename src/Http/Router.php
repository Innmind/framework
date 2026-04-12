<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Router\{
    Component,
    Router as Route,
    Any,
    Handle,
    Respond,
    Exception\NotFound,
    Exception\NoRouteProvided,
};
use Innmind\Immutable\{
    Maybe,
    Sequence,
    Attempt,
    SideEffect,
};

/**
 * @internal
 */
final class Router
{
    /**
     * @param Sequence<Component<SideEffect, Response>> $routes
     * @param Maybe<\Closure(ServerRequest): Attempt<Response>> $notFound
     * @param \Closure(ServerRequest, \Throwable): Attempt<Response> $recover
     */
    public function __construct(
        private Sequence $routes,
        private Maybe $notFound,
        private \Closure $recover,
    ) {
    }

    /**
     * @return Attempt<Response>
     */
    public function __invoke(ServerRequest $request): Attempt
    {
        $recover = $this->recover;
        $notFound = $this->notFound;

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        $route = Route::of(
            Any::from($this->routes)
                ->mapError(static fn($e) => match (true) {
                    $e instanceof NoRouteProvided => new NotFound,
                    default => $e,
                })
                ->otherwise(static fn(\Throwable $e) => Component::of(
                    static fn($request) => $notFound
                        ->filter(static fn() => $e instanceof NotFound)
                        ->match(
                            static fn($handle) => $handle($request),
                            static fn() => Attempt::error($e),
                        ),
                ))
                ->otherwise(Respond::withHttpErrors())
                ->otherwise(static fn($e) => Handle::via(
                    static fn($request) => $recover($request, $e),
                )),
        );

        return $route($request);
    }
}
