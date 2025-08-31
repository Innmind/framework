# Build a CLI app

The first of any CLI app is to create an `entrypoint.php` that you'll call with the `php` command.

```php title="entrypoint.php"
<?php
declare(strict_types = 1);

require 'path/to/composer/autoload.php';

use Innmind\Framework\{
    Main\Cli,
    Application,
};

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app;
    }
};
```

By default this application will write `Hello world` when you call `php entrypoint.php`.

## Handle commands

This example reuses the AMQP clients defined in the [services topic](services.md).

```php
use Innmind\Framework\{
    Main\Cli,
    Application,
};
use Innmind\CLI\{
    Console,
    Command,
};
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\AMQP\{
    Client,
    Command\Publish,
    Command\Get,
    Model\Basic\Message,
};
use Innmind\Immutable\Str;

enum Services implements Service
{
    case producerClient;
    case consumerClient;
}

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app
            ->service(Services::producerClient, /* see services topic */)
            ->service(Services::consumerClient, /* see services topic */)
            ->command(static fn(Container $container) => new class($container(Services::producerClient)) implements Command {
                public function __construct(
                    private Client $amqp,
                ) {
                }

                public function __invoke(Console $console): Console
                {
                    $message = Message::of(Str::of(
                        $console->arguments()->get('url'),
                    ));

                    return $this
                        ->client
                        ->with(Publish::one($message)->to('some-exchange'))
                        ->run($console)
                        ->match(
                            static fn($console) => $console->output(
                                Str::of("Message published\n"),
                            ),
                            static fn() => $console->error(
                                Str::of("Something went wrong\n"),
                            ),
                        );
                }

                public function usage(): string
                {
                    return 'publish url';
                }
            })
            ->command(static fn(Container $container) => new class($container(Services::consumerClient)) implements Command {
                public function __construct(
                    private Client $amqp,
                ) {
                }

                public function __invoke(Console $console): Console
                {
                    return $this
                        ->client
                        ->with(Get::of('some-queue'))
                        ->run($console)
                        ->match(
                            static fn($console) => $console->output(
                                Str::of("One message pulled from queue\n"),
                            ),
                            static fn() => $console->error(
                                Str::of("Something went wrong\n"),
                            ),
                        );
                }

                public function usage(): string
                {
                    return 'consume';
                }
            });
    }
};
```

This example creates 2 commands `publish` (that expect one argument) and `consume`. Each command relies on a service to access the AMQP client.

You can call `php entrypoint.php publish https://github.com` that will call the first command and `php entrypoint.php consume` will call the second one.

## Execute code on any command

Sometimes you want to execute some code on every command. So far your only approach would be to use inheritance on each `Command` but this leads to bloated code.

Fortunately there is better approach: composition of `Command`s.

```php
use Innmind\Framework\{
    Main\Cli,
    Application,
};
use Innmind\CLI\{
    Console,
    Command,
};

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app
            ->mapCommand(
                static fn(Command $command) => new class($command) implements Command {
                    public function __construct(
                        private Command $inner,
                    ) {
                    }

                    public function __invoke(Console $console): Console
                    {
                        // do something before the real command

                        return ($this->inner)($console);
                    }

                    public function usage(): string
                    {
                        return $this->inner->usage();
                    }
                }
            )
            ->service(/* ... */)
            ->service(/* ... */)
            ->command(/* ... */)
            ->command(/* ... */);
    }
};
```
