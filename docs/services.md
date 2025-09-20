# Services

For both [HTTP](http.md) and [CLI](cli.md) applications a service is an object referenced by a name in a [`Container`](https://github.com/Innmind/DI).

## Defining a service

```php
use Innmind\DI\Service;
use Innmind\AMQP\Client;

/**
 * @template S of object
 * @implements Service<S>
 */
enum Services implements Service
{
    case amqpClient;
    case producerClient;
    case consumerClient;

    /**
     * @return self<Client>
     */
    public static function amqpClient(): self
    {
        /** @var self<Client> */
        return self::amqpClient;
    }

    /**
     * @return self<Client>
     */
    public static function producerClient(): self
    {
        /** @var self<Client> */
        return self::producerClient;
    }

    /**
     * @return self<Client>
     */
    public static function consumerClient(): self
    {
        /** @var self<Client> */
        return self::consumerClient;
    }
}
```

!!! tip ""
    If you publish a package you can add an `@internal` flag on the static methods to tell your users to not use the service. And when you plan to remove a service you can use the `@deprecated` flag.

```php
use Innmind\Framework\{
    Main\Http,
    Main\Cli,
    Application,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\AMQP\Factory;
use Innmind\IO\Sockets\Internet\Transport;
use Innmind\TimeContinuum\Period;
use Innmind\Url\Url;

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->service(
            Services::amqpClient(),
            static fn($_, OperatingSystem $os) => Factory::of($os)->make(
                Transport::tcp(),
                Url::of('amqp://guest:guest@localhost:5672/'),
                Period::second(1),
            ),
        );
    }
};
```

This example defines a single service named `amqpClient` that relies on the `OperatingSystem` in order to work.

!!! note ""
    This example uses [`innmind/amqp`](https://github.com/innmind/amqp).

## Configure via environment variables

In the previous example we defined an AMQP client with a hardcoded url for the server and timeout, but you may want to configure those with environment variables.

```php
use Innmind\Framework\{
    Main\Http,
    Main\Cli,
    Application,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\AMQP\Factory;
use Innmind\IO\Sockets\Internet\Transport;
use Innmind\TimeContinuum\Period;
use Innmind\Url\Url;

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->service(
            Services::amqpClient(),
            static fn(
                $_,
                OperatingSystem $os,
                Environment $env
            ) => Factory::of($os)->make(
                Transport::tcp(),
                Url::of($env->get('AMQP_URL')), //(1)
                Period::second($env->maybe('AMQP_TIMEOUT')->match( //(2)
                    static fn($timeout) => (int) $timeout,
                    static fn() => 1,
                )),
            ),
        );
    }
};
```

1. this will throw if the variable is not defined
2. in case the variable is not defined it will fallback to a `1s` timeout

## Services relying on services

If we continue with our AMQP example, in an application that both produces and consumes messages (with high throughput) we'll want 2 different clients. One basic client (like previous examples) for producing messages and one client where we configure the number of messages to prefetch.

To do this we'll reuse our previous service definition and define a new one that calls the first one. (This operation is safe in this case as the client is immutable)

```php
use Innmind\Framework\{
    Main\Http,
    Main\Cli,
    Application,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\Container;
use Innmind\AMQP\{
    Factory,
    Command\Qos,
};

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app
            ->service(
                Services::producerClient(),
                static fn($_, OperatingSystem $os) => Factory::of($os)->make(/* like above */),
            )
            ->service(
                Services::consumerClient(),
                static fn(Container $container) => $container(Services::producerClient)->with(
                    Qos::of(10), // prefetch 10 messages
                ),
            );
    }
};
```

Now every other service that relies on `consumerClient` will always have a configuration to prefetch 10 messages.
