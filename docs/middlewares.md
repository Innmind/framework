# Middlewares

Middlewares are a way to regroup all the configuration you've seen in other topics under a name. This means that you can either group part of your own application undeer a middleware or expose a package for other to use via Packagist.

> [!NOTE]
> you can search for [`innmind/framework-middlewares` on Packagist](https://packagist.org/providers/innmind/framework-middlewares) for middlewares published by others.

Let's say you have an application that sends emails you could have a middleware that looks like this:

```php
use Innmind\Framework\{
    Middleware,
    Environment,
};
use Innmind\DI\Container;
use Innmind\CLI\{
    Console,
    Command,
};
use Innmind\Url\Url;

final class Emails implements Middleware
{
    public function __construct(
        private string $service,
    ){
    }

    public function __invoke(Application $app): Application
    {
        return $app
            ->service(
                'email-server'
                static fn($_, $__, Environment $env) => Url::of($env->get('EMAIL_SERVER'))
            ),
            ->service(
                $this->service,
                static fn(Container $container) => new EmailClient( // imaginary class
                    $container('email-server'),
                ),
            )
            ->command(
                static fn(Container $container) => new class($container($this->service)) implements Command {
                    public function __construct(
                        private EmailClient $client,
                    ) {
                    }

                    public function __invoke(Console $console): Console
                    {
                        // send a test email here for example

                        return $console;
                    }

                    public function usage(): string
                    {
                        return 'email:test';
                    }
                }
            );
    }
}
```

And you would use it like this:

```php
use Innmind\Framework\{
    Main\Cli,
    Application,
};

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app->map(new Emails('email-client-service-name'));
    }
};
```

This example defines 2 services and a command and let the end users choose the name of the email client service so they can reuse it in their applications.

## Optional middleware

In some cases, like in development, you'll have a middleware that is not always existing. The framework deals with this case via composition with the `Optional` middleware.

```php
use Innmind\Framework\{
    Main\Cli,
    Application,
    Middleware\Optional,
};

new class extends Cli {
    protected function configure(Application $app): Application
    {
        return $app->map(Optional::of(MyMiddleware::class));
    }
};
```

If the `MyMiddleware` class doesn't exist it will do nothing and if it exists it will instanciate it and call it.

If the middleware constructor is private or you want to specify arguments you can pass a factory as a second argument like this:

```php
Optional::of(
    MyMiddleware::class,
    static fn() => new MyMiddleware('some argument'),
);
```
