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
    private Application $app;

    protected function preload(OperatingSystem $os, Environment $env): void
    {
        $this->app = static::configure(Application::http($os, AppEnv::http($env)));
    }

    protected function main(ServerRequest $request): Response
    {
        /**
         * @psalm-suppress InvalidReturnStatement Let the app crash in case of a misuse
         * @var Response
         */
        return $this->app->run($request);
    }

    abstract protected function configure(Application $app): Application;
}
