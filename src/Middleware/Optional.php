<?php
declare(strict_types = 1);

namespace Innmind\Framework\Middleware;

use Innmind\Framework\{
    Application,
    Middleware,
};

final class Optional implements Middleware
{
    /** @var class-string<Middleware> */
    private string $middleware;
    /** @var callable(): Middleware */
    private $factory;

    /**
     * @param class-string<Middleware> $middleware
     * @param callable(): Middleware $factory
     */
    private function __construct(string $middleware, callable $factory)
    {
        $this->middleware = $middleware;
        $this->factory = $factory;
    }

    public function __invoke(Application $app): Application
    {
        if (!\class_exists($this->middleware)) {
            return $app;
        }

        $middleware = ($this->factory)();

        return $middleware($app);
    }

    /**
     * @param class-string<Middleware> $middleware
     * @param callable(): Middleware $factory
     */
    public static function of(string $middleware, ?callable $factory = null): self
    {
        return new self(
            $middleware,
            $factory ?? static fn() => new $middleware,
        );
    }
}
