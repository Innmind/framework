<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Router\{
    Route,
    Under,
};
use Innmind\Immutable\Sequence;

/**
 * @psalm-immutable
 */
final class Routes
{
    /** @var Sequence<Route|Under> */
    private Sequence $routes;

    /**
     * @param Sequence<Route|Under> $routes
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

    public function add(Route|Under $route): self
    {
        return new self(($this->routes)($route));
    }

    /**
     * @internal
     *
     * @return Sequence<Route|Under>
     */
    public function toSequence(): Sequence
    {
        return $this->routes;
    }
}
