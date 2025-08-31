# Testing

The best way to test your application is to move the whole configuration inside a [middleware](middlewares.md) that you can then reference in your tests.

If your whole is contained in a middleware called `Kernel` and you use PHPUnit your test could look like this:

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
        ));

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
        ));

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

Say we want to write functional tests but we have an route that deletes data in a database but we can't verify the data is deleted through our routes. We can add a call to `mapRequestHandler` so we are in the context of our app and we can inject the test case to write our assertions.

```php
use Innmind\Framework\{
    Application,
    Environment,
    Http\RequestHandler,
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
        ]))
            ->map(new Kernel)
            ->mapRequestHandler(
                fn($handler, $container) => new class($handler, $container(Services::pdo), $this) implements RequestHandler {
                    public function __construct(
                        private RequestHandler $handler,
                        private \PDO $pdo,
                        private TestCase $test,
                    ) {
                    }

                    public function __invoke(ServerRequest $request): Response
                    {
                        $response = ($this->handler)($request);

                        $this->test->assertSame(
                            [],
                            $this
                                ->pdo
                                ->query('SELECT * FROM some_column WHERE condition_that_should_return_nothing')
                                ->fetchAll(),
                        );

                        return $response;
                    }
                },
            );

        $response = $app->run(ServerRequest::of(
            Url::of('/some-route/some-id'),
            Method::delete,
            ProtocolVersion::v20,
        ));

        // $response is an instance of Response
        // write your assertions as usual
    }
}
```
