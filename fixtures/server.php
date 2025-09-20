<?php
declare(strict_types = 1);

require __DIR__.'/../vendor/autoload.php';

use Innmind\Framework\{
    Application,
    Main\Async\Http,
};
use Innmind\Router\Respond;
use Innmind\Http\Response\StatusCode;

new class extends Http
{
    protected function configure(Application $app): Application
    {
        return $app->route(
            static fn($pipe) => $pipe
                ->get()
                ->endpoint('/hello')
                ->pipe(Respond::with(StatusCode::ok)),
        );
    }
};
