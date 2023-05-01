# Framework

[![Build Status](https://github.com/Innmind/Framework/workflows/CI/badge.svg?branch=main)](https://github.com/Innmind/Framework/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/Innmind/Framework/branch/develop/graph/badge.svg)](https://codecov.io/gh/Innmind/Framework)
[![Type Coverage](https://shepherd.dev/github/Innmind/Framework/coverage.svg)](https://shepherd.dev/github/Innmind/Framework)

Minimalist HTTP/CLI framework that accomodate to simple applications to complex ones via middlewares.

The framework configuration is immutable and use a declarative approach.

**Important**: to correctly use this library you must validate your code with [`vimeo/psalm`](https://packagist.org/packages/vimeo/psalm)

## Installation

```sh
composer require innmind/framework
```

## Usage

Take a look at the [documentation](docs/) for a more in-depth understanding of the framework.

### Http

The first step is to create the index file that will be exposed via a webserver (for example `public/index.php`). Then you need to specify the routes you want to handle.

> **Note** if you don't configure any route it will respond with `404 Not Found` with an empty body.

```php
<?php
declare(strict_types = 1);

require 'path/to/composer/autoload.php';

use Innmind\Framework\{
    Main\Http,
    Application,
    Http\Routes,
};
use Innmind\Router\{
    Route,
    Route\Variables,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response\Response,
    StatusCode,
};
use Innmind\Filesystem\File\Content;

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app->appendRoutes(
            static fn(Routes $routes) => $routes
                ->add(Route::literal('GET /')->handle(
                    static fn(ServerRequest $request) => new Response(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        Content\Lines::ofContent('Hello world!'),
                    ),
                ))
                ->add(Route::literal('GET /{name}')->handle(
                    static fn(ServerRequest $request, Variables $variables) => new Response(
                        StatusCode::ok,
                        $request->protocolVersion(),
                        null,
                        Content\Lines::ofContent("Hello {$variables->get('name')}!"),
                    ),
                )),
        );
    }
};
```

You can run this script via `cd public && php -S localhost:8080`. If you open your web browser it will display `Hello world!` and if you go to `/John` it will display `Hello John!`.

### Cli

The entrypoint of your cli tools will look something like this.

> **Note** by default if you don't configure any command it will always display `Hello world`.

```php
<?php
declare(strict_types = 1);

require 'path/to/composer/autoload.php';

use Innmind\Framework\{
    Main\Cli,
    Application,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\TimeContinuum\{
    Clock,
    Earth\Format\ISO8601,
};
use Innmind\DI\Container;
use Innmind\CLI\{
    Console,
    Command,
};
use Innmind\Immutable\Str;

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app->command(
            static fn(Container $container, OperatingSystem $os) => new class($os->clock()) implements Command {
                public function __construct(
                    private Clock $clock,
                ) {
                }

                public function __invoke(Console $console): Console
                {
                    $today = $this->clock->now()->format(new ISO8601);

                    return $console->output(Str::of("We are the: $today\n"));
                }

                public function usage(): string
                {
                    return 'today';
                }
            },
        );
    }
};
```

We can execute our script via `php filename.php` (or `php filename.php today`) and it would output something like `We are the: 2022-12-30T14:04:50+00:00`.
