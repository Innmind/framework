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
    Service,
};
use Innmind\Immutable\{
    Sequence,
    Str,
    Attempt,
};

/**
 * @internal
 * @implements Implementation<CliEnv, CliEnv>
 */
final class Cli implements Implementation
{
    /**
     * @psalm-mutation-free
     *
     * @param \Closure(OperatingSystem, Environment): Builder $container
     * @param (\Closure(Container): Command)|Sequence<callable(Container): Command>|null $commands
     * @param \Closure(Command, Container): Command $mapCommand
     */
    private function __construct(
        private OperatingSystem $os,
        private Environment $env,
        private \Closure $container,
        private \Closure|Sequence|null $commands,
        private \Closure $mapCommand,
    ) {
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
            null,
            static fn(Command $command) => $command,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
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
    #[\Override]
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
    #[\Override]
    public function service(Service $name, callable $definition): self
    {
        $container = $this->container;

        return new self(
            $this->os,
            $this->env,
            static fn(OperatingSystem $os, Environment $env) => $container($os, $env)->add(
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
    #[\Override]
    public function command(callable $command): self
    {
        $commands = $this->commands;

        if (\is_null($commands)) {
            $commands = \Closure::fromCallable($command);
        } else if ($commands instanceof Sequence) {
            $commands = ($commands)($command);
        } else {
            $commands = Sequence::of($commands, $command);
        }

        return new self(
            $this->os,
            $this->env,
            $this->container,
            $commands,
            $this->mapCommand,
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapCommand(callable $map): self
    {
        $previous = $this->mapCommand;

        return new self(
            $this->os,
            $this->env,
            $this->container,
            $this->commands,
            static fn(
                Command $command,
                Container $service,
            ) => $map(
                $previous($command, $service),
                $service,
            ),
        );
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function route(callable $handle): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function mapRoute(callable $map): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function routeNotFound(callable $handle): self
    {
        return $this;
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function recoverRouteError(callable $recover): self
    {
        return $this;
    }

    #[\Override]
    public function run($input): Attempt
    {
        $container = ($this->container)($this->os, $this->env)->build();
        $mapCommand = $this->mapCommand;
        $os = $this->os;
        $env = $this->env;
        $mapCommand = static fn(Command $command): Command => $mapCommand(
            $command,
            $container,
        );

        if (\is_null($this->commands)) {
            return $input->output(Str::of("Hello world\n"));
        }

        if ($this->commands instanceof Sequence) {
            $commands = $this->commands->map(static fn($command) => new Defer(
                \Closure::fromCallable($command),
                $container,
                $mapCommand,
            ));

            return Commands::for($commands)($input);
        }

        return Commands::of($mapCommand(
            ($this->commands)($container),
        ))($input);
    }
}
