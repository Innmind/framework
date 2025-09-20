# Framework

[![Build Status](https://github.com/Innmind/Framework/workflows/CI/badge.svg?branch=main)](https://github.com/Innmind/Framework/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/Innmind/Framework/branch/develop/graph/badge.svg)](https://codecov.io/gh/Innmind/Framework)
[![Type Coverage](https://shepherd.dev/github/Innmind/Framework/coverage.svg)](https://shepherd.dev/github/Innmind/Framework)

Minimalist HTTP/CLI framework that accomodate to simple applications to complex ones via middlewares.

The framework configuration is immutable and use a declarative approach.

> [!IMPORTANT]
> to correctly use this library you must validate your code with [`vimeo/psalm`](https://packagist.org/packages/vimeo/psalm)

## Installation

```sh
composer require innmind/framework
```

## Usage

Take a look at the [documentation](docs/) for a more in-depth understanding of the framework.

### Http

The first step is to create the index file that will be exposed via a webserver (for example `public/index.php`). Then you need to specify the routes you want to handle.

> [!NOTE]
> if you don't configure any route it will respond with `404 Not Found` with an empty body.

```php
<?php
declare(strict_types = 1);

require 'path/to/composer/autoload.php';

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

You can run this script via `cd public && php -S localhost:8080`. If you open your web browser it will display `Hello world!` and if you go to `/John` it will display `Hello John!`.

### Cli

The entrypoint of your cli tools will look something like this.

> [!NOTE]
> by default if you don't configure any command it will always display `Hello world`.

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
    Format,
};
use Innmind\DI\Container;
use Innmind\CLI\{
    Console,
    Command,
    Command\Usage,
};
use Innmind\Immutable\{
    Attempt,
    Str,
};

enum Services implements Service
{
    case clock;
}

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app
            ->service(Services::clock, static fn($_, OperatingSystem $os) => $os->clock())
            ->command(
                static fn(Container $container) => new class($container(Services::clock)) implements Command {
                    public function __construct(
                        private Clock $clock,
                    ) {
                    }

                    public function __invoke(Console $console): Attempt
                    {
                        $today = $this->clock->now()->format(Format::iso8601());

                        return $console->output(Str::of("We are the: $today\n"));
                    }

                    public function usage(): Usage
                    {
                        return Usage::of('today');
                    }
                },
            );
    }
};
```

We can execute our script via `php filename.php` (or `php filename.php today`) and it would output something like `We are the: 2022-12-30T14:04:50+00:00`.
