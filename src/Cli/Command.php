<?php
declare(strict_types = 1);

namespace Innmind\Framework\Cli;

use Innmind\CLI\{
    Command as CommandInterface,
    Command\Usage,
    Console,
};
use Innmind\DI\{
    Container,
    Service,
};
use Innmind\Immutable\Attempt;

final class Command implements CommandInterface
{
    private ?CommandInterface $command = null;

    /**
     * @param class-string<CommandInterface> $class
     * @param list<Service> $dependencies
     */
    private function __construct(
        private Container $get,
        private string $class,
        private array $dependencies,
    ) {
    }

    #[\Override]
    public function __invoke(Console $console): Attempt
    {
        return $this->load()($console);
    }

    /**
     * @psalm-pure
     * @no-named-arguments
     *
     * @param class-string<CommandInterface> $class
     */
    public static function of(
        string $class,
        Service ...$dependencies,
    ): callable {
        return static fn(Container $get) => new self($get, $class, $dependencies);
    }

    /**
     * @psalm-mutation-free
     */
    #[\Override]
    public function usage(): Usage
    {
        return Usage::for($this->class)->load(
            fn() => $this->load()->usage(),
        );
    }

    private function load(): CommandInterface
    {
        return $this->command ??= new ($this->class)(
            ...\array_map(
                $this->get,
                $this->dependencies,
            ),
        );
    }
}
