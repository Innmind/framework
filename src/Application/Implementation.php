<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application;

use Innmind\Framework\{
    Environment,
    Http\RequestHandler,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\CLI\{
    Environment as CliEnv,
    Command,
};
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\Router\{
    Component,
    Pipe,
};
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Immutable\{
    Attempt,
    SideEffect,
};

/**
 * @internal
 * @template I of ServerRequest|CliEnv
 * @template O of Response|Attempt<CliEnv>
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
     * @param callable(Container, OperatingSystem, Environment): object $definition
     *
     * @return self<I, O>
     */
    public function service(Service $name, callable $definition): self;

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
     * @param callable(Pipe, Container, OperatingSystem, Environment): Component<SideEffect, Response> $handle
     *
     * @return self<I, O>
     */
    public function route(callable $handle): self;

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
