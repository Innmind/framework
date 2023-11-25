<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Router\RequestMatcher\RequestMatcher;
use Innmind\Immutable\Maybe;

/**
 * @internal
 */
final class Router implements RequestHandler
{
    private Routes $routes;
    /** @var Maybe<\Closure(ServerRequest): Response> */
    private Maybe $notFound;

    /**
     * @param Maybe<\Closure(ServerRequest): Response> $notFound
     */
    public function __construct(Routes $routes, Maybe $notFound)
    {
        $this->routes = $routes;
        $this->notFound = $notFound;
    }

    public function __invoke(ServerRequest $request): Response
    {
        $match = new RequestMatcher($this->routes->toSequence());

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
