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

final class Cli
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
     * @param callable(Environment, OperatingSystem): Environment $map
     */
    public function mapEnvironment(callable $map): self
    {
        return new self(
            $this->os,
            $map($this->env, $this->os),
            $this->container,
            $this->commands,
            $this->mapCommand,
        );
    }

    /**
     * @param callable(OperatingSystem, Environment): OperatingSystem $map
     */
    public function mapOperatingSystem(callable $map): self
    {
        return new self(
            $map($this->os, $this->env),
            $this->env,
            $this->container,
            $this->commands,
            $this->mapCommand,
        );
    }

    /**
     * @param non-empty-string $name
     * @param callable(Container, OperatingSystem, Environment): object $definition
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
     * @param callable(Container, OperatingSystem, Environment): Command $command
     */
    public function command(callable $command): self
    {
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
     * @param callable(Command, Container, OperatingSystem, Environment): Command $map
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

    public function run(CliEnv $env): CliEnv
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
            static fn($first, $rest) => Commands::of($first, ...$rest->toList())($env),
            static fn() => $env->output(Str::of("Hello world\n")),
        );
    }
}
