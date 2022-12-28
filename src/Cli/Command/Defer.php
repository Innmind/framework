<?php
declare(strict_types = 1);

namespace Innmind\Framework\Cli\Command;

use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\DI\ServiceLocator;

/**
 * @internal
 */
final class Defer implements Command
{
    private string $service;
    private ServiceLocator $locate;
    private ?Command $command = null;

    public function __construct(
        string $service,
        ServiceLocator $locate,
    ) {
        $this->service = $service;
        $this->locate = $locate;
    }

    public function __invoke(Console $console): Console
    {
        return $this->command()($console);
    }

    /**
     * @psalm-pure
     */
    public function usage(): string
    {
        /**
         * @psalm-suppress ImpureVariable
         * @psalm-suppress ImpureMethodCall
         */
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
