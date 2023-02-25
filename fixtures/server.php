<?php
declare(strict_types = 1);

require __DIR__.'/../vendor/autoload.php';

use Innmind\Framework\{
    Application,
    Main\Async\Http,
    Http\Routes,
};
use Innmind\Router\Route;

new class extends Http
{
    protected function configure(Application $app): Application
    {
        return $app->appendRoutes(static fn($routes) => $routes->add(
            Route::literal('GET /hello'),
        ));
    }
};
