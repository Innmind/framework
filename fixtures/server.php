<?php
declare(strict_types = 1);

require __DIR__.'/../vendor/autoload.php';

use Innmind\Framework\{
    Application,
    Main\Async\Http,
    Http\Routes,
};
use Innmind\Router\{
    Method,
    Endpoint,
    Handle,
    Respond,
};
use Innmind\Http\Response\StatusCode;
use Innmind\Immutable\Attempt;

new class extends Http
{
    protected function configure(Application $app): Application
    {
        return $app->appendRoutes(static fn($routes) => $routes->add(
            Method::get()
                ->pipe(Endpoint::of('/hello'))
                ->pipe(Respond::with(StatusCode::ok)),
        ));
    }
};
