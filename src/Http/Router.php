<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Router\{
    Component,
    Router as Route,
    Any,
    Handle,
    Respond,
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
     * @param Maybe<\Closure(ServerRequest): Response> $notFound
     */
    public function __construct(
        private Sequence $routes,
        private Maybe $notFound,
    ) {
    }

    public function __invoke(ServerRequest $request): Response
    {
        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        $route = Route::of(
            Any::from($this->routes)
                ->otherwise(Respond::withHttpErrors())
                ->or(Handle::via(
                    fn($request, SideEffect $_) => $this->notFound->match(
                        static fn($handle) => Attempt::result($handle($request)),
                        static fn() => Attempt::result(Response::of(
                            StatusCode::notFound,
                            $request->protocolVersion(),
                        )),
                    ),
                )),
        );

        return $route($request)->unwrap();
    }
}
