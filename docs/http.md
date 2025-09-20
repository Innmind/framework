# Build an HTTP app

The first of any HTTP app is to create an `index.php` that will be exposed via a web server.

```php title="index.php"
<?php
declare(strict_types = 1);

require 'path/to/composer/autoload.php';

use Innmind\Framework\{
    Main\Http,
    Application,
};

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app;
    }
};
```

By default this application will respond with `404 Not Found` on any incoming request.

## Handle routes

```php
use Innmind\Framework\{
    Main\Http,
    Application,
    Http\Route,
};
use Innmind\DI\Service;
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\File\Content;
use Innmind\Immutable\Attempt;

enum Services implements Service
{
    case hello;
}

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app
            ->service(Services::hello, static fn() => static fn(
                ServerRequest $request,
                ?string $name = null,
            ) => Attempt::result(Response::of(
                StatusCode::ok,
                $request->protocolVersion(),
                null,
                Content::ofString(\sprintf(
                    'Hello %s!',
                    $name ?? 'world',
                )),
            )))
            ->route(Route::get(
                '/',
                Services::hello,
            ))
            ->route(Route::get(
                '/{name}',
                Services::hello,
            ));
    }
};
```

This example defines 2 routes both accessible via a `GET` method. When called, a route will be handled by the `Services::hello` service.

For simplicity here the route handler is defined as a `Closure` but you can use objects instead.

## Multiple methods for the same path

For REST apis it is common to implements differents methods for the same path in a CRUD like fashion. To avoid duplicating the template for each route you can regroup your routes like this:

```php
use Innmind\Framework\{
    Main\Http,
    Application,
};
use Innmind\DI\Container;
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\File\Content;
use Innmind\Immutable\Attempt;

enum Services implements Service
{
    case get;
    case delete;
}

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app
            ->service(
                Services::get,
                static fn() => static fn(ServerRequest $request) => Attempt::result(
                    Response::of(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        Content::ofString('{"id": 42, "name": "resource"}'),
                    ),
                ),
            )
            ->service(
                Services::delete,
                static fn() => static fn(ServerRequest $request) => Attempt::result(
                    Response::of(
                        StatusCode::noContent,
                        $request->protocolVersion(),
                    ),
                ),
            )
            ->route(
                static fn(Pipe $pipe, Container $container) => $pipe
                    ->endpoint('/some/resource/{id}')
                    ->any(
                        $pipe
                            ->forward()
                            ->get()
                            ->spread()
                            ->handle($container(Services::get))),
                        $pipe
                            ->forward()
                            ->delete()
                            ->spread()
                            ->handle($container(Services::delete))),
                    ),
            );
    }
};
```

The other advantage to grouping your routes this way is that when a request matches the path but no method is defined then the framework will automatically respond a `405 Method Not Allowed`.

## Executing code on any route

Sometimes you want to execute some code on every route (like verifying the request is authenticated).

```php
use Innmind\Framework\{
    Main\Http,
    Application,
};
use Innmind\Router\Component;
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Immutable\Attempt;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app
            ->mapRoute(
                static fn(Component $route) => Component::of(static function(
                    ServerRequest $request,
                    mixed $input,
                ) {
                    // use something stronger in a real app
                    if (!$request->headers()->contains('authorization')) {
                        return Attempt::error(new \RuntimeException('Missing authentication'));
                    }

                    return Attempt::result($input); #(1)
                })->pipe($route),
            )
            ->service(/* ... */)
            ->service(/* ... */)
            ->route(/* ... */)
            ->route(/* ... */);
    }
};
```

1. You can replace `#!php $input` with the authenticated user, this variable will be carried to the next route component.

This example will refuse any request that doesn't have an `Authorization` header. Assuming you use a service instead of an inline component, you can disable a behaviour across your entire app by removing the one line calling `mapRoute`.

You can have multiple calls to `mapRoute` to compose behaviours like an onion.

## Handling unknown routes

Sometimes a user can mispell a route or use an old route that no longer exist resulting in a `404 Not Found`. For APIs this can be enough but you may want to customize such response.

```php
use Innmind\Framework\{
    Main\Http,
    Application,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\File\Content;
use Innmind\Immutable\Attempt;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->routeNotFound(
            static fn(ServerRequest $request) => Attempt::result(Response::of(
                StatusCode::notFound,
                $request->protocolVersion(),
                null,
                Content::ofString('Page Not Found!'), //(1)
            )),
        );
    }
};
```

1. or return something more elaborated such as html
