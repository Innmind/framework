<?php
declare(strict_types = 1);

namespace Innmind\Framework\Cli\Command;

use Innmind\CLI\{
    Command,
    Command\Usage,
    Console,
};
use Innmind\DI\Container;
use Innmind\Immutable\Attempt;

/**
 * @internal
 */
final class Defer implements Command
{
    private ?Command $command = null;

    /**
     * @param \Closure(Container): Command $build
     * @param \Closure(Command): Command $map
     */
    public function __construct(
        private \Closure $build,
        private Container $locate,
        private \Closure $map,
    ) {
    }

    #[\Override]
    public function __invoke(Console $console): Attempt
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
    public function usage(): Usage
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
        return $this->command ??= ($this->build)($this->locate);
    }
}
