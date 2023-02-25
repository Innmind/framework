<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application\Async;

use Innmind\Framework\{
    Environment,
    Http\Routes,
    Http\Router,
    Http\RequestHandler,
};
use Innmind\CLI\Environment as CliEnv;
use Innmind\OperatingSystem\{
    OperatingSystem,
    OperatingSystem\Unix,
};
use Innmind\Async\HttpServer\{
    Server,
    InjectEnvironment,
    Open,
};
use Innmind\TimeContinuum\{
    Earth\ElapsedPeriod,
};
use Innmind\DI\{
    Container,
    Builder,
};
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Environment as HttpEnv,
};
use Innmind\Url\Authority\Port;
use Innmind\IP\IP;
use Innmind\Socket\{
    Server as SocketServer,
    Internet\Transport,
};
use Innmind\Stream\Streams;
use Innmind\Mantle\{
    Forerunner,
    Source,
    Suspend\TimeFrame,
};
use Innmind\Immutable\{
    Sequence,
    Str,
    Maybe,
};

/**
 * @experimental
 */
final class Http
{
    private OperatingSystem $os;
    /** @var Maybe<Open> */
    private Maybe $open;
    /** @var callable(OperatingSystem, Environment): array{OperatingSystem, Environment} */
    private $map;
    /** @var callable(OperatingSystem, Environment): Builder */
    private $container;
    /** @var callable(Routes, Container, OperatingSystem, Environment): Routes */
    private $routes;
    /** @var callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler */
    private $mapRequestHandler;
    /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
    private Maybe $notFound;

    /**
     * @psalm-mutation-free
     *
     * @param Maybe<Open> $open
     * @param callable(OperatingSystem, Environment): array{OperatingSystem, Environment} $map
     * @param callable(OperatingSystem, Environment): Builder $container
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $routes
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $mapRequestHandler
     * @param Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> $notFound
     */
    private function __construct(
        OperatingSystem $os,
        Maybe $open,
        callable $map,
        callable $container,
        callable $routes,
        callable $mapRequestHandler,
        Maybe $notFound,
    ) {
        $this->os = $os;
        $this->open = $open;
        $this->map = $map;
        $this->container = $container;
        $this->routes = $routes;
        $this->mapRequestHandler = $mapRequestHandler;
        $this->notFound = $notFound;
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os): self
    {
        /** @var Maybe<Open> */
        $open = Maybe::nothing();
        /** @var Maybe<callable(ServerRequest, Container, OperatingSystem, Environment): Response> */
        $notFound = Maybe::nothing();

        return new self(
            $os,
            $open,
            static fn(OperatingSystem $os, Environment $env) => [$os, $env],
            static fn() => Builder::new(),
            static fn(Routes $routes) => $routes,
            static fn(RequestHandler $handler) => $handler,
            $notFound,
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function open(Port $port, IP $ip = null, Transport $transport = null): self
    {
        return new self(
            $this->os,
            $this
                ->open
                ->map(static fn($open) => $open->and($port, $ip, $transport))
                ->otherwise(static fn() => Maybe::just(Open::of(
                    $port,
                    $ip,
                    $transport,
                ))),
            $this->map,
            $this->container,
            $this->routes,
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Environment, OperatingSystem): Environment $map
     */
    public function mapEnvironment(callable $map): self
    {
        return new self(
            $this->os,
            $this->open,
            function(OperatingSystem $os, Environment $env) use ($map): array {
                [$os, $env] = ($this->map)($os, $env);
                $env = $map($env, $os);

                return [$os, $env];
            },
            $this->container,
            $this->routes,
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     */
    public function mapOperatingSystem(callable $map): self
    {
        return new self(
            $this->os,
            $this->open,
            function(OperatingSystem $os, Environment $env) use ($map): array {
                [$os, $env] = ($this->map)($os, $env);
                $os = $map($os, $env);

                return [$os, $env];
            },
            $this->container,
            $this->routes,
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
     */
    public function service(string $name, callable $definition): self
    {
        return new self(
            $this->os,
            $this->open,
            $this->map,
            fn(OperatingSystem $os, Environment $env) => ($this->container)($os, $env)->add(
                $name,
                static fn($service) => $definition($service, $os, $env),
            ),
            $this->routes,
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     */
    public function appendRoutes(callable $append): self
    {
        return new self(
            $this->os,
            $this->open,
            $this->map,
            $this->container,
            fn(
                Routes $routes,
                Container $container,
                OperatingSystem $os,
                Environment $env,
            ) => $append(
                ($this->routes)($routes, $container, $os, $env),
                $container,
                $os,
                $env,
            ),
            $this->mapRequestHandler,
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
     */
    public function mapRequestHandler(callable $map): self
    {
        return new self(
            $this->os,
            $this->open,
            $this->map,
            $this->container,
            $this->routes,
            fn(
                RequestHandler $handler,
                Container $container,
                OperatingSystem $os,
                Environment $env,
            ) => $map(
                ($this->mapRequestHandler)($handler, $container, $os, $env),
                $container,
                $os,
                $env,
            ),
            $this->notFound,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
     */
    public function notFoundRequestHandler(callable $handle): self
    {
        return new self(
            $this->os,
            $this->open,
            $this->map,
            $this->container,
            $this->routes,
            $this->mapRequestHandler,
            Maybe::just($handle),
        );
    }

    public function run(CliEnv $env): CliEnv
    {
        $open = $this->open->match(
            static fn($open) => $open,
            static fn() => Open::of(Port::of(8080)),
        );

        return $open($this->os)->match(
            fn($servers) => $this->serve($env, $servers),
            static fn() => $env
                ->error(Str::of("Failed to open sockets\n"))
                ->exit(1),
        );
    }

    /**
     * @param Sequence<SocketServer> $servers
     */
    private function serve(CliEnv $env, Sequence $servers): CliEnv
    {
        $source = new Server(
            $this->os,
            match ($this->os instanceof Unix) {
                true => $this->os->config()->streamCapabilities(),
                false => Streams::fromAmbientAuthority(),
            },
            $servers,
            ElapsedPeriod::of(1_000),
            InjectEnvironment::of(new HttpEnv($env->variables())),
            function(ServerRequest $request, OperatingSystem $os): Response {
                $env = Environment::http($request->environment());
                [$os, $env] = ($this->map)($os, $env);
                $container = ($this->container)($os, $env)->build();
                $routes = ($this->routes)(
                    Routes::lazy(),
                    $container,
                    $os,
                    $env,
                );
                $router = new Router(
                    $routes,
                    $this->notFound->map(
                        static fn($handle) => static fn(ServerRequest $request) => $handle(
                            $request,
                            $container,
                            $os,
                            $env,
                        ),
                    ),
                );
                $handle = ($this->mapRequestHandler)($router, $container, $os, $env);

                return $handle($request);
            },
        );
        $forerunner = Forerunner::of(
            $this->os->clock(),
            TimeFrame::of($this->os->clock(), ElapsedPeriod::of(100)),
        );

        return $forerunner($env, $source);
    }
}
