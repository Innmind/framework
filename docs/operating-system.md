# Decorate the operating system

The framework exposes an instance of [`OperatingSystem`](https://github.com/Innmind/OperatingSystem) in various methods of `Application` offering you a wide range of abstractions. You can enhance its capabilities by adding a decorator on top of it.

For example `innmind/operating-system` comes with a decorator that use an exponential backoff strategy for the http client.

```php
use Innmind\Framework\{
    Main\Cli,
    Main\Http,
    Application,
};
use Innmind\OperatingSystem\{
    OperatingSystem,
    Config\Resilient,
};

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->mapOperatingSystem(
            static fn(OperatingSystem $os) => $os->map(Resilient::new()),
        );
    }
};
```

Now everything that relies on `$os->remote()->http()` will use this exponential backoff strategy.
