<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Router\Component;
use Innmind\Http\Response;
use Innmind\Immutable\{
    Sequence,
    SideEffect,
};

/**
 * @psalm-immutable
 */
final class Routes
{
    /**
     * @param Sequence<Component<SideEffect, Response>> $routes
     */
    private function __construct(private Sequence $routes)
    {
    }

    /**
     * @psalm-pure
     */
    public static function lazy(): self
    {
        return new self(Sequence::lazyStartingWith());
    }

    /**
     * @param Component<SideEffect, Response> $route
     */
    public function add(Component $route): self
    {
        return new self(($this->routes)($route));
    }

    /**
     * @param Sequence<Component<SideEffect, Response>> $routes
     */
    public function append(Sequence $routes): self
    {
        return new self($this->routes->append($routes));
    }

    /**
     * @internal
     *
     * @return Sequence<Component<SideEffect, Response>>
     */
    public function toSequence(): Sequence
    {
        return $this->routes;
    }
}
