# Build an HTTP app

The first of any HTTP app is to create an `index.php` that will be exposed via a web server.

```php
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
    Http\Routes,
};
use Innmind\Router\Route;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->appendRoutes(
            static fn(Routes $routes) => $routes
                ->add(Route::literal('GET /'))
                ->add(Route::literal('GET /{name}')),
        );
    }
};
```

This example defines 2 routes both accessible via a `GET` method. But this doesn't do much as we didn't specify what to do when they're called (the default behaviour is `200 Ok` with an empty response body).

To specify a behaviour you need to attach a handler on each route.

```php
use Innmind\Framework\{
    Main\Http,
    Application,
    Http\Routes,
};
use Innmind\Router\{
    Route,
    Route\Variables,
};
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\File\Content;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->appendRoutes(
            static fn(Routes $routes) => $routes
                ->add(Route::literal('GET /')->handle(
                    static fn(ServerRequest $request) => Response::of(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        Content::ofString('Hello world!'),
                    ),
                ))
                ->add(Route::literal('GET /{name}')->handle(
                    static fn(
                        ServerRequest $request,
                        Variables $variables,
                    ) => Response::of(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        Content::ofString("Hello {$variables->get('name')}!"),
                    ),
                )),
        );
    }
};
```

For simple apps having the whole behaviour next to the route can be ok. But like in this case it can be repetitive, for such case we can specify our behaviours elsewhere: [services](#services).

## Multiple methods for the same path

For REST apis it is common to implements differents methods for the same path in a CRUD like fashion. To avoid duplicating te template for each route you can regroup your routes like this:

```php
use Innmind\Framework\{
    Main\Http,
    Application,
    Http\Routes,
};
use Innmind\Router\Under;
use Innmind\Http\{
    ServerRequest,
    Method,
    Response,
    Response\StatusCode,
};
use Innmind\UrlTemplate\Template;
use Innmind\Filesystem\File\Content;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->appendRoutes(
            static fn(Routes $routes) => $routes->add(
                Under::of(Template::of('/some/resource/{id}'))
                    ->route(Method::get, static fn($route) => $route->handle(
                        static fn(ServerRequest $request) => Response::of(
                            StatusCode::ok,
                            $request->protocolVersion(),
                            null,
                            Content::ofString('{"id": 42, "name": "resource"}'),
                        ),
                    ))
                    ->route(Method::delete, static fn($route) => $route->handle(
                        static fn(ServerRequest $request) => Response::of(
                            StatusCode::noContent,
                            $request->protocolVersion(),
                        ),
                    ))
            ),
        );
    }
};
```

The other advantage to grouping your routes this way is that when a request matches the path but no method is defined then the framework will automatically respond a `405 Method Not Allowed`.

## Short syntax

The previous shows the default way to declare routes, but for very simple apps it can be a bit verbose. The framework provides a shorter syntax to handle routes:

```php
use Innmind\Framework\{
    Main\Http,
    Application,
};
use Innmind\Router\Route\Variables;
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\File\Content;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app
            ->route(
                'GET /',
                static fn(ServerRequest $request) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    Content::ofString('Hello world!'),
                ),
            )
            ->route(
                'GET /{name}',
                static fn(
                    ServerRequest $request,
                    Variables $variables,
                ) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    Content::ofString("Hello {$variables->get('name')}!"),
                ),
            );
    }
};
```

## Services

Services are any object that are referenced by a string in a [`Container`](https://github.com/Innmind/DI). For example let's take the route handler from the previous section and move them inside services.

```php
use Innmind\Framework\{
    Main\Http,
    Application,
    Http\Routes,
    Http\Service,
    Http\To,
};
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\Router\{
    Route,
    Route\Variables,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\File\Content;

enum Services implements Service
{
    case helloWorld;
    case helloName;
}

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app
            ->service(
                Services::helloWorld,
                static fn() => new class {
                    public function __invoke(ServerRequest $request): Response
                    {
                        return Response::of(
                            StatusCode::ok,
                            $request->protocolVersion(),
                            null,
                            Content::ofString('Hello world!'),
                        );
                    }
                }
            )
            ->service(
                Services::helloName,
                static fn() => new class {
                    public function __invoke(
                        ServerRequest $request,
                        Variables $variables,
                    ): Response {
                        return Response::of(
                            StatusCode::ok,
                            $request->protocolVersion(),
                            null,
                            Content::ofString("Hello {$variables->get('name')}!"),
                        );
                    }
                }
            )
            ->appendRoutes(
                static fn(Routes $routes, Container $container) => $routes->add(
                    Route::literal('GET /')->handle(
                        Service::of($container, Services::helloWorld),
                    ),
                ),
            )
            ->route('GET /{name}', To::service(Services::helloName));
    }
};
```

Here the services are invokable anonymous classes to conform to the callable expected for a `Route` but you can create dedicated classes for each one.

!!! note ""
    Head to the [services topic](services.md) for a more in-depth look of what's possible.

## Executing code on any route

Sometimes you want to execute some code on every route (like verifying the request is authenticated). So far your only approach would be to use inheritance on each route handler but this leads to bloated code.

Fortunately there is better approach: composition of `RequestHandler`s.

```php
use Innmind\Framework\{
    Main\Http,
    Application,
    Http\RequestHandler,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Response\StatusCode,
};

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app
            ->mapRequestHandler(
                static fn(RequestHandler $handler) => new class($handler) implements RequestHandler {
                    public function __construct(
                        private RequestMatcher $inner,
                    ) {
                    }

                    public function __invoke(ServerRequest $request): Response
                    {
                        // use something stronger in a real app
                        if (!$request->headers()->contains('authorization')) {
                            return Response::of(
                                StatusCode::unauthorized,
                                $request->protocolVersion(),
                            );
                        }

                        return ($this->inner)($request);
                    }
                }
            )
            ->service(/* ... */)
            ->service(/* ... */)
            ->appendRoutes(/* ... */);
    }
};
```

This example will refuse any request that doesn't have an `Authorization` header. Assuming you use a class instead of an anonymous one, you can disable a behaviour across your entire app by removing the one line calling `mapRequestHandler`.

You can have multiple calls to `mapRequestHandler` to compose behaviours like an onion.

!!! note ""
    The default request handler is the inner router of the framework, this means that you can completely change the default behaviour of the framework by returning a new request handler that never uses the default one.

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

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->notFoundRequestHandler(
            static fn(ServerRequest $request) => Response::of(
                StatusCode::notFound,
                $request->protocolVersion(),
                null,
                Content::ofString('Page Not Found!'), //(1)
            ),
        );
    }
};
```

1. or return something more elaborated such as html
