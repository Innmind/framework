<?php
declare(strict_types = 1);

namespace Innmind\Framework\Cli\Command;

use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\DI\Container;

/**
 * @internal
 */
final class Defer implements Command
{
    private string $service;
    private Container $locate;
    /** @var callable(Command): Command */
    private $map;
    private ?Command $command = null;

    /**
     * @param callable(Command): Command $map
     */
    public function __construct(
        string $service,
        Container $locate,
        callable $map,
    ) {
        $this->service = $service;
        $this->locate = $locate;
        $this->map = $map;
    }

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
        return $this->command ??= ($this->locate)($this->service);
    }
}
