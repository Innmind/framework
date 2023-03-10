<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Router\Route;
use Innmind\Immutable\Sequence;

/**
 * @psalm-immutable
 */
final class Routes
{
    /** @var Sequence<Route> */
    private Sequence $routes;

    /**
     * @param Sequence<Route> $routes
     */
    private function __construct(Sequence $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @psalm-pure
     */
    public static function lazy(): self
    {
        return new self(Sequence::lazyStartingWith());
    }

    public function add(Route $route): self
    {
        return new self(($this->routes)($route));
    }

    /**
     * @internal
     *
     * @return Sequence<Route>
     */
    public function toSequence(): Sequence
    {
        return $this->routes;
    }
}
