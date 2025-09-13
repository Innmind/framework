<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Router\{
    Pipe,
    Route as Alias,
    Component,
    Handle\Proxy,
};
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\Http\Response;
use Innmind\UrlTemplate\Template;
use Innmind\Immutable\Attempt;

final class Route
{
    private function __construct()
    {
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function get(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->get()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function post(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->post()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function put(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->put()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function patch(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->patch()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function delete(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->delete()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function options(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->options()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function trace(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->trace()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function connect(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->connect()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function head(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->head()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function link(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->link()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }

    /**
     * @psalm-pure
     *
     * @param literal-string|Template|Alias $endpoint
     * @param Service<object&(callable(mixed...): Attempt<Response>)> $handler
     *
     * @return callable(Pipe, Container): Component<mixed, Response>
     */
    public static function unlink(
        string|Template|Alias $endpoint,
        Service $handler,
    ): callable {
        return static fn(Pipe $pipe, Container $get) => $pipe
            ->endpoint($endpoint)
            ->unlink()
            ->spread()
            ->handle(Proxy::of(
                static fn() => $get($handler),
            ));
    }
}
