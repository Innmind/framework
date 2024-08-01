<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Framework\Environment;
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\DI\Container;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Router\Route\Variables;

final class To
{
    private string $service;

    private function __construct(string $service)
    {
        $this->service = $service;
    }

    public function __invoke(
        ServerRequest $request,
        Variables $variables,
        Container $container,
        OperatingSystem $os = null, // these arguments are not used, there here
        Environment $env = null, // to satisfy Psalm when used in Framework::route()
    ): Response {
        /**
         * @psalm-suppress InvalidFunctionCall If it fails here then the service doesn't conform to the signature callable(ServerRequest, Variables): Response
         * @var Response
         */
        return $container($this->service)($request, $variables);
    }

    public static function service(string $service): self
    {
        return new self($service);
    }
}
