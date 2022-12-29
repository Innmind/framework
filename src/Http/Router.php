<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\Message\{
    ServerRequest,
    Response,
    StatusCode,
};
use Innmind\Router\RequestMatcher\RequestMatcher;

/**
 * @internal
 */
final class Router implements RequestHandler
{
    private Routes $routes;

    public function __construct(Routes $routes)
    {
        $this->routes = $routes;
    }

    public function __invoke(ServerRequest $request): Response
    {
        $match = new RequestMatcher($this->routes->toSequence());

        return $match($request)->match(
            static fn($route) => $route->respondTo($request),
            static fn() => new Response\Response(
                StatusCode::notFound,
                $request->protocolVersion(),
            ),
        );
    }
}
