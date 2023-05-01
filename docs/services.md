# Services

For both [HTTP](http.md) and [CLI](cli.md) applications a service is an object referenced by a name in a [`Container`](https://github.com/Innmind/DI).

> **Note** since a container only deals with objects Psalm will complain of type mismatches, so you'll have to suppress those errors (for now).

## Defining a service

```php
use Innmind\Framework\{
    Main\Http,
    Main\Cli,
    Application,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\AMQP\Factory;
use Innmind\Socket\Internet\Transport;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Url\Url;

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->service(
            'amqp-client',
            static fn($_, OperatingSystem $os) => Factory::of($os)->make(
                Transport::tcp(),
                Url::of('amqp://guest:guest@localhost:5672/'),
                ElapsedPeriod::of(1000),
            ),
        );
    }
};
```

This example defines a single service named `amqp-client` that relies on the `OperatingSystem` in order to work.

> **Note** this example uses [`innmind/amqp`](https://github.com/innmind/amqp)

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
use Innmind\Socket\Internet\Transport;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Url\Url;

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->service(
            'amqp-client',
            static fn($_, OperatingSystem $os, Environment $env) => Factory::of($os)->make(
                Transport::tcp(),
                Url::of($env->get('AMQP_URL')), // this will throw if the variable is not defined
                ElapsedPeriod::of($env->maybe('AMQP_TIMEOUT')->match( // in case the variable is not defined it will fallback to a 1000ms timeout
                    static fn($timeout) => (int) $timeout,
                    static fn() => 1000,
                )),
            ),
        );
    }
};
```

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
                'producer-client',
                static fn($_, OperatingSystem $os) => Factory::of($os)->make(/* like above */),
            )
            ->service(
                'consumer-client',
                static fn(Container $container) => $container('producer-client')->with(
                    Qos::of(10), // prefetch 10 messages
                ),
            );
    }
};
```

Now every other service that relies on `consumer-client` will always have a configuration to prefetch 10 messages.
