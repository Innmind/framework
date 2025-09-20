<?php
declare(strict_types = 1);

namespace Innmind\Framework\Middleware;

use Innmind\Framework\{
    Application,
    Middleware,
};

final class Optional implements Middleware
{
    /**
     * @param class-string<Middleware> $middleware
     * @param \Closure(): Middleware $factory
     */
    private function __construct(
        private string $middleware,
        private \Closure $factory,
    ) {
    }

    #[\Override]
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
            \Closure::fromCallable($factory ?? static fn() => new $middleware),
        );
    }
}
