<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\DI\{
    Container,
    Service as Ref,
};
use Innmind\Router\Route\Variables;

final class Service
{
    private function __construct(
        private Container $container,
        private Ref $service,
    ) {
    }

    public function __invoke(ServerRequest $request, Variables $variables): Response
    {
        /**
         * @psalm-suppress InvalidFunctionCall If it fails here then the service doesn't conform to the signature callable(ServerRequest, Variables): Response
         * @var Response
         */
        return ($this->container)($this->service)($request, $variables);
    }

    public static function of(Container $container, Ref $service): self
    {
        return new self($container, $service);
    }
}
