<?php
declare(strict_types = 1);

namespace Tests\Innmind\Framework;

use Innmind\OperatingSystem\Factory;
use Innmind\Server\Control\Server\{
    Command,
    Signal,
};
use Innmind\HttpTransport\{
    Success,
    ClientError,
};
use Innmind\Http\{
    Request,
    Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    private $os;
    private $server;

    public function setUp(): void
    {
        $this->os = Factory::build();
        $this->server = $this
            ->os
            ->control()
            ->processes()
            ->execute(
                Command::foreground('php fixtures/server.php')
                    ->withEnvironment('PATH', \getenv('PATH')),
            );
    }

    public function tearDown(): void
    {
        $this->server->pid()->match(
            fn($pid) => $this->os->control()->processes()->kill(
                $pid,
                Signal::kill,
            ),
            static fn() => null,
        );
    }

    public function testAsyncHttpServer()
    {
        // let the server time to boot
        \usleep(500_000);

        $error = $this
            ->os
            ->remote()
            ->http()(Request::of(
                Url::of('http://127.0.0.1:8080/'),
                Method::get,
                ProtocolVersion::v10,
            ))
            ->match(
                static fn() => null,
                static fn($error) => $error,
            );

        $this->assertInstanceOf(ClientError::class, $error);
        $this->assertSame(404, $error->response()->statusCode()->toInt());

        $success = $this
            ->os
            ->remote()
            ->http()(Request::of(
                Url::of('http://127.0.0.1:8080/hello'),
                Method::get,
                ProtocolVersion::v10,
            ))
            ->match(
                static fn($success) => $success,
                static fn() => null,
            );

        $this->assertInstanceOf(Success::class, $success);
        $this->assertSame(200, $success->response()->statusCode()->toInt());
    }
}
