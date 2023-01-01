# Build an app that runs through HTTP and CLI

If you looked at how to build an [HTTP](http.md) and [CLI](cli.md) app you may have noticed that we always configure the same `Application` class. This is intentional to allow you to configure services once (in a [middleware](middlewares)) and use them in both contexts.

Let's take an imaginary app where you can upload images via HTTP (persists them to the filesystem) and a CLI command that pulls a message from an AMQP queue to build the thumbnail. We would build a middleware that roughly looks like this:

```php
use Innmind\Framework\{
    Application,
    Middleware,
    Http\Routes,
    Http\Service,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\Container;
use Innmind\Router\Route;
use Innmind\Url\Path;

final class Kernel implements Middleware
{
    public function __invoke(Application $app): Application
    {
        return $app
            ->service(
                'images',
                static fn($_, OperatingSystem $os) => $os->filesystem()->mount(Path::of('somewhere/on/the/filesystem/')),
            )
            ->service('amqp', /* see services topic */)
            ->service('upload', static fn(Container $container) => new UploadHandler( // imaginary class
                $container('images'),
                $container('amqp'),
            ))
            ->appendRoutes(
                static fn(Routes $routes, Container $container) => $routes->add(
                    Route::literal('POST /upload')->handle(Service::of($container, 'upload')),
                ),
            )
            ->command(static fn(Container $container) => new ThumbnailWorker( // imaginary class
                $container('images'),
                $container('amqp'),
            ));
    }
}
```

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
