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

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        $route = Route::of(
            Any::from($this->routes)
                ->otherwise(Respond::withHttpErrors())
                ->otherwise(static fn($e) => Handle::via(
                    static fn($request) => $recover($request, $e),
                ))
                ->or(Handle::via(
                    fn($request, SideEffect $_) => $this->notFound->match(
                        static fn($handle) => $handle($request),
                        static fn() => Attempt::result(Response::of(
                            StatusCode::notFound,
                            $request->protocolVersion(),
                        )),
                    ),
                )),
        );

        return $route($request);
    }
}
