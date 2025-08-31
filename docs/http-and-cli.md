# Build an app that runs through HTTP and CLI

If you looked at how to build an [HTTP](http.md) and [CLI](cli.md) app you may have noticed that we always configure the same `Application` class. This is intentional to allow you to configure services once (in a [middleware](middlewares.md)) and use them in both contexts.

Let's take an imaginary app where you can upload images via HTTP (persists them to the filesystem) and a CLI command that pulls a message from an AMQP queue to build the thumbnail. We would build a middleware that roughly looks like this:

```php
use Innmind\Framework\{
    Application,
    Middleware,
    Http\Routes,
    Http\Service,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\Router\Route;
use Innmind\Url\Path;

enum Services implements Service
{
    case images;
    case amqp;
    case upload;
}

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app
            ->service(
                Services::images,
                static fn($_, OperatingSystem $os) => $os
                    ->filesystem()
                    ->mount(Path::of('somewhere/on/the/filesystem/')),
            )
            ->service(Services::amqp, /* see services topic */)
            ->service(Services::upload, static fn(Container $container) => new UploadHandler( //(1)
                $container(Services::images),
                $container(Services::amqp),
            ))
            ->appendRoutes(
                static fn(Routes $routes, Container $container) => $routes->add(
                    Route::literal('POST /upload')->handle(Service::of($container, Services::upload)),
                ),
            )
            ->command(static fn(Container $container) => new ThumbnailWorker( //(2)
                $container(Services::images),
                $container(Services::amqp),
            ));
    }
}
```

1. imaginary class
2. imaginary class

Then you can use this middleware like this:

```php
use Innmind\Framework\{
    Main\Cli,
    Application,
};

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app->map(new Kernel);
    }
}
```

Or like this:

```php
use Innmind\Framework\{
    Main\Http,
    Application,
};

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->map(new Kernel);
    }
}
```

In the case on the CLI the call to `appendRoutes` will have no effect and for HTTP `command` will have no effect.
