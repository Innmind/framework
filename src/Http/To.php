<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Framework\Environment;
use Innmind\Http\Response;
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Router\{
    Component,
    Handle,
};
use Innmind\Immutable\Map;

final class To
{
    private function __construct(private Service $service)
    {
    }

    /**
     * @return Component<Map<string, mixed>, Response>
     */
    public function __invoke(
        Container $container,
        OperatingSystem $os,
        Environment $env,
    ): Component {
        $service = $this->service;

        /**
         * @psalm-suppress MissingClosureReturnType
         * @psalm-suppress MixedArgumentTypeCoercion
         * @psalm-suppress InvalidFunctionCall
         * @todo fix non lazy call
         */
        return Handle::of(
            $container($service),
        );
    }

    public static function service(Service $service): self
    {
        return new self($service);
    }
}
