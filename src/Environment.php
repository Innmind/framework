<?php
declare(strict_types = 1);

namespace Innmind\Framework;

use Innmind\Framework\Exception\LogicException;
use Innmind\Immutable\{
    Map,
    Maybe,
};

/**
 * @psalm-immutable
 */
final class Environment
{
    /** @var Map<string, string> */
    private Map $variables;

    /**
     * @param Map<string, string> $variables
     */
    private function __construct(Map $variables)
    {
        $this->variables = $variables;
    }

    /**
     * @psalm-pure
     *
     * @param Map<string, string> $variables
     */
    public static function of(Map $variables): self
    {
        return new self($variables);
    }

    public function with(string $key, string $value): self
    {
        return new self(($this->variables)($key, $value));
    }

    /**
     * @param literal-string $key
     *
     * @throws LogicException If the variable doesn't exist
     */
    public function get(string $key): string
    {
        return $this->maybe($key)->match(
            static fn($value) => $value,
            static fn() => throw new LogicException("Unknown variable $key"),
        );
    }

    /**
     * @return Maybe<string>
     */
    public function maybe(string $key): Maybe
    {
        return $this->variables->get($key);
    }
}
