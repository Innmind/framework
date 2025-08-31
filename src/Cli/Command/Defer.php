<?php
declare(strict_types = 1);

namespace Innmind\Framework\Cli\Command;

use Innmind\Framework\Environment;
use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\DI\Container;

/**
 * @internal
 */
final class Defer implements Command
{
    /** @var callable(Container, OperatingSystem, Environment): Command */
    private $build;
    private Container $locate;
    private OperatingSystem $os;
    private Environment $env;
    /** @var callable(Command): Command */
    private $map;
    private ?Command $command = null;

    /**
     * @param callable(Container, OperatingSystem, Environment): Command $build
     * @param callable(Command): Command $map
     */
    public function __construct(
        callable $build,
        Container $locate,
        OperatingSystem $os,
        Environment $env,
        callable $map,
    ) {
        $this->build = $build;
        $this->locate = $locate;
        $this->os = $os;
        $this->env = $env;
        $this->map = $map;
    }

    #[\Override]
    public function __invoke(Console $console): Console
    {
        // we map the command when running it instead of when loading it to
        // avoid loading the decorator multiple times for a same script as
        // multiple commands can be loaded before finding the one "usage" that
        // matches the expected one
        $command = ($this->map)($this->command());

        return $command($console);
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function usage(): string
    {
        /** @psalm-suppress ImpureMethodCall */
        return $this->command()->usage();
    }

    private function command(): Command
    {
        /**
         * @psalm-suppress PropertyTypeCoercion
         * @var Command
         */
        return $this->command ??= ($this->build)($this->locate, $this->os, $this->env);
    }
}
