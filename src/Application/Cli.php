<?php
declare(strict_types = 1);

namespace Innmind\Framework\Application;

use Innmind\Framework\Environment;
use Innmind\CLI\{
    Command,
    Commands,
    Environment as CliEnv,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\{
    Container,
    ServiceLocator,
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
    /** @var callable(OperatingSystem, Environment): Container */
    private $container;
    /** @var Sequence<string> */
    private Sequence $commands;

    /**
     * @param callable(OperatingSystem, Environment): Container $container
     * @param Sequence<string> $commands
     */
    private function __construct(
        OperatingSystem $os,
        Environment $env,
        callable $container,
        Sequence $commands,
    ) {
        $this->os = $os;
        $this->env = $env;
        $this->container = $container;
        $this->commands = $commands;
    }

    public static function of(OperatingSystem $os, Environment $env): self
    {
        return new self(
            $os,
            $env,
            static fn() => new Container,
            Sequence::strings(),
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
        );
    }

    /**
     * @param non-empty-string $name
     * @param callable(ServiceLocator, OperatingSystem, Environment): object $definition
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
        );
    }

    /**
     * @param callable(ServiceLocator, OperatingSystem, Environment): Command $command
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
        );
    }

    public function run(CliEnv $env): CliEnv
    {
        $container = ($this->container)($this->os, $this->env);

        /** @var Sequence<Command> */
        $commands = $this->commands->map($container);

        return $commands->match(
            static fn($first, $rest) => Commands::of($first, ...$rest->toList())($env),
            static fn() => $env->output(Str::of("Hello world\n")),
        );
    }
}
