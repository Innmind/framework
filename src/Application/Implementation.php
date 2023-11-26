<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application;

use Innmind\Framework\{
    Environment,
    Http\Routes,
    Http\RequestHandler,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\{
    Environment as CliEnv,
    Command,
};
use Innmind\DI\Container;
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Router\{
    Route,
    Route\Variables,
};

/**
 * @internal
 * @template I of ServerRequest|CliEnv
 * @template O of Response|CliEnv
 */
interface Implementation
{
    /**
     * @psalm-mutation-free
     *
     * @param callable(Environment, OperatingSystem): Environment $map
     *
     * @return self<I, O>
     */
    public function mapEnvironment(callable $map): self;

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     *
     * @return self<I, O>
     */
    public function mapOperatingSystem(callable $map): self;

    /**
     * @psalm-mutation-free
     *
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
     *
     * @return self<I, O>
     */
    public function service(string $name, callable $definition): self;

    /**
     * @psalm-mutation-free
     *
     * @param callable(Container, OperatingSystem, Environment): Command $command
     *
     * @return self<I, O>
     */
    public function command(callable $command): self;

    /**
     * @psalm-mutation-free
     *
     * @param callable(Command, Container, OperatingSystem, Environment): Command $map
     *
     * @return self<I, O>
     */
    public function mapCommand(callable $map): self;

    /**
     * @psalm-mutation-free
     *
     * @param literal-string $pattern
     * @param callable(ServerRequest, Variables, Container, OperatingSystem, Environment): Response $handle
     *
     * @return self<I, O>
     */
    public function route(string $pattern, callable $handle): self;

    /**
     * @psalm-mutation-free
     *
     * @param callable(Routes, Container, OperatingSystem, Environment): Routes $append
     *
     * @return self<I, O>
     */
    public function appendRoutes(callable $append): self;

    /**
     * @psalm-mutation-free
     *
     * @param callable(RequestHandler, Container, OperatingSystem, Environment): RequestHandler $map
     *
     * @return self<I, O>
     */
    public function mapRequestHandler(callable $map): self;

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, Container, OperatingSystem, Environment): Response $handle
     *
     * @return self<I, O>
     */
    public function notFoundRequestHandler(callable $handle): self;

    /**
     * @param I $input
     *
     * @return O
     */
    public function run($input);
}
