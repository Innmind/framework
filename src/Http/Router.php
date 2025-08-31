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
    Under,
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
    /** @var Sequence<Route|Under> */
    private Sequence $routes;
    /** @var Maybe<\Closure(ServerRequest): Response> */
    private Maybe $notFound;

    /**
     * @param Sequence<Route|Under> $routes
     * @param Maybe<\Closure(ServerRequest): Response> $notFound
     */
    public function __construct(Sequence $routes, Maybe $notFound)
    {
        $this->routes = $routes;
        $this->notFound = $notFound;
    }

    #[\Override]
    public function __invoke(ServerRequest $request): Response
    {
        $match = new RequestMatcher($this->routes);
        $notFound = $this->notFound;

        return $match($request)
            ->map(static fn($route) => $route->respondTo(...))
            ->otherwise(static fn() => $notFound)
            ->match(
                static fn($handle) => $handle($request),
                static fn() => Response::of(
                    StatusCode::notFound,
                    $request->protocolVersion(),
                ),
            );
    }
}
