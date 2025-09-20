<?php
declare(strict_types = 1);

namespace Innmind\Framework\Main;

use Innmind\Framework\{
    Application,
    Environment as AppEnv,
};
use Innmind\HttpServer\Main;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Http\{
    ServerRequest,
    Response,
    ServerRequest\Environment,
};

abstract class Http extends Main
{
    /** @var Application<ServerRequest, Response> */
    private Application $app;

    #[\Override]
    protected function preload(OperatingSystem $os, Environment $env): void
    {
        $this->app = static::configure(Application::http($os, AppEnv::http($env)));
    }

    #[\Override]
    protected function main(ServerRequest $request): Response
    {
        return $this->app->run($request)->unwrap();
    }

    /**
     * @param Application<ServerRequest, Response> $app
     *
     * @return Application<ServerRequest, Response>
     */
    abstract protected function configure(Application $app): Application;
}
