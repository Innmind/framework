<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Router\{
    Route,
    RequestMatcher\RequestMatcher,
};
use Innmind\Immutable\{
    Maybe,
    Sequence,
};

/**
 * @internal
 */
final class Router implements RequestHandler
{
    /** @var Sequence<Route> */
    private Sequence $routes;
    /** @var Maybe<\Closure(ServerRequest): Response> */
    private Maybe $notFound;

    /**
     * @param Sequence<Route> $routes
     * @param Maybe<\Closure(ServerRequest): Response> $notFound
     */
    public function __construct(Sequence $routes, Maybe $notFound)
    {
        $this->routes = $routes;
        $this->notFound = $notFound;
    }

    public function __invoke(ServerRequest $request): Response
    {
        $match = new RequestMatcher($this->routes);

        return $match($request)
            ->map(static fn($route) => $route->respondTo(...))
            ->otherwise(fn() => $this->notFound)
            ->match(
                static fn($handle) => $handle($request),
                static fn() => Response::of(
                    StatusCode::notFound,
                    $request->protocolVersion(),
                ),
            );
    }
}
