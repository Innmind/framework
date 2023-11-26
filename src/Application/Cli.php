<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application;

use Innmind\Framework\{
    Environment,
    Cli\Command\Defer,
};
use Innmind\CLI\{
    Command,
    Commands,
    Environment as CliEnv,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\{
    Builder,
    Container,
};
use Innmind\Immutable\{
    Sequence,
    Str,
};
use Ramsey\Uuid\Uuid;

/**
 * @internal
 * @implements Implementation<CliEnv, CliEnv>
 */
final class Cli implements Implementation
{
    private OperatingSystem $os;
    private Environment $env;
    /** @var callable(OperatingSystem, Environment): Builder */
    private $container;
    /** @var Sequence<string> */
    private Sequence $commands;
    /** @var callable(Command, Container, OperatingSystem, Environment): Command */
    private $mapCommand;

    /**
     * @psalm-mutation-free
     *
     * @param callable(OperatingSystem, Environment): Builder $container
     * @param Sequence<string> $commands
     * @param callable(Command, Container, OperatingSystem, Environment): Command $mapCommand
     */
    private function __construct(
        OperatingSystem $os,
        Environment $env,
        callable $container,
        Sequence $commands,
        callable $mapCommand,
    ) {
        $this->os = $os;
        $this->env = $env;
        $this->container = $container;
        $this->commands = $commands;
        $this->mapCommand = $mapCommand;
    }

    /**
     * @psalm-pure
     */
    public static function of(OperatingSystem $os, Environment $env): self
    {
        return new self(
            $os,
            $env,
            static fn() => Builder::new(),
            Sequence::strings(),
            static fn(Command $command) => $command,
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function mapEnvironment(callable $map): self
    {
        /** @psalm-suppress ImpureFunctionCall Mutation free to force the user to use the returned object */
        return new self(
            $this->os,
            $map($this->env, $this->os),
            $this->container,
            $this->commands,
            $this->mapCommand,
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function mapOperatingSystem(callable $map): self
    {
        /** @psalm-suppress ImpureFunctionCall Mutation free to force the user to use the returned object */
        return new self(
            $map($this->os, $this->env),
            $this->env,
            $this->container,
            $this->commands,
            $this->mapCommand,
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function service(string $name, callable $definition): self
    {
        return new self(
            $this->os,
            $this->env,
            fn(OperatingSystem $os, Environment $env) => ($this->container)($os, $env)->add(
                $name,
                static fn($service) => $definition($service, $os, $env),
            ),
            $this->commands,
            $this->mapCommand,
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function command(callable $command): self
    {
        /** @psalm-suppress ImpureMethodCall Mutation free to force the user to use the returned object */
        $reference = Uuid::uuid4()->toString();
        $self = $this->service($reference, $command);

        return new self(
            $self->os,
            $self->env,
            $self->container,
            ($self->commands)($reference),
            $self->mapCommand,
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function mapCommand(callable $map): self
    {
        return new self(
            $this->os,
            $this->env,
            $this->container,
            $this->commands,
            fn(
                Command $command,
                Container $service,
                OperatingSystem $os,
                Environment $env,
            ) => $map(
                ($this->mapCommand)($command, $service, $os, $env),
                $service,
                $os,
                $env,
            ),
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function route(string $pattern, callable $handle): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    public function appendRoutes(callable $append): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    public function mapRequestHandler(callable $map): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    public function notFoundRequestHandler(callable $handle): self
    {
        return $this;
    }

    public function run($input)
    {
        $container = ($this->container)($this->os, $this->env)->build();
        $mapCommand = fn(Command $command): Command => ($this->mapCommand)(
            $command,
            $container,
            $this->os,
            $this->env,
        );
        $commands = $this->commands->map(static fn($service) => new Defer(
            $service,
            $container,
            $mapCommand,
        ));

        return $commands->match(
            static fn($first, $rest) => Commands::of($first, ...$rest->toList())($input),
            static fn() => $input->output(Str::of("Hello world\n")),
        );
    }
}
