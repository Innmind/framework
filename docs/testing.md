# Testing

The best way to test your application is to move the whole configuration inside a [middleware](middlewares.md) that you can then reference in your tests.

If your whole app is contained in a middleware called `Kernel` and you use PHPUnit your test could look like this:

## For HTTP

```php
use Innmind\Framework\{
    Application,
    Environment,
};
use Innmind\OperatingSystem\Factory;
use Innmind\Http\{
    ServerRequest,
    Response,
    Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    public function testIndex()
    {
        $os = Factory::build(); // or use mocks
        $app = Application::http($os, Environment::test([
            'AMQP_URL' => 'amqp://guest:guest@localhost:5672/',
        ]))->map(new Kernel);

        $response = $app->run(ServerRequest::of(
            Url::of('/'),
            Method::get,
            ProtocolVersion::v20,
        ))->unwrap();

        // $response is an instance of Response
        // write your assertions as usual
    }
}
```

## For CLI

```php
use Innmind\Framework\{
    Application,
    Environment,
};
use Innmind\OperatingSystem\Factory;
use Innmind\CLI\Environment\InMemory;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    public function testIndex()
    {
        $variables = [
            ['AMQP_URL', 'amqp://guest:guest@localhost:5672/'],
        ];
        $os = Factory::build(); // or use mocks
        $app = Application::cli($os, Environment::test($variables))->map(
            new Kernel,
        );

        $environment = $app->run(InMemory::of(
            [], // input chunks
            false, // interactive
            ['entrypoint.php'], // arguments
            $variables,
            '/somewhere/', // working directory
        ))->unwrap();

        $this->assertSame(
            [],
            $environment->outputs()
        );
        $this->assertNull($environment->exitCode()->match(
            static fn($exitCode) => $exitCode->toInt(),
            static fn() => null,
        ));
    }
}
```

## Extending behaviour

Since we use a declarative approach and that `Application` is _immutable_ we can extend the behaviour of our app in our tests.

Say we want to write functional tests but we have a route that deletes data in a database but we can't verify the data is deleted through our routes. We can add a call to `mapRoute` so we are in the context of our app and we can inject the test case to write our assertions.

```php
use Innmind\Framework\{
    Application,
    Environment,
};
use Innmind\OperatingSystem\Factory;
use Innmind\Router\Component;
use Innmind\Http\{
    ServerRequest,
    Response,
    Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\Immutable\Attempt;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    public function testIndex()
    {
        $os = Factory::build(); // or use mocks
        $app = Application::http($os, Environment::test([
            'AMQP_URL' => 'amqp://guest:guest@localhost:5672/',
        ]))
            ->map(new Kernel)
            ->mapRoute(static fn($route, $container) => $route->pipe(
                Component::of(function($request, $response) use ($container) {
                    $this->assertSame(
                        [],
                        $container(Services::pdo)
                            ->query('SELECT * FROM some_column WHERE condition_that_should_return_nothing')
                            ->fetchAll(),
                    );

                    return Attempt::result($response);
                }),
            ));

        $response = $app->run(ServerRequest::of(
            Url::of('/some-route/some-id'),
            Method::delete,
            ProtocolVersion::v20,
        ))->unwrap();

        // $response is an instance of Response
        // write your assertions as usual
    }
}
```
