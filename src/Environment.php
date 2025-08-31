<?php
declare(strict_types = 1);

namespace Innmind\Framework;

use Innmind\Framework\Exception\LogicException;
use Innmind\Http\ServerRequest\Environment as HttpEnvironment;
use Innmind\Immutable\{
    Map,
    Maybe,
};

/**
 * @psalm-immutable
 */
final class Environment
{
    /**
     * @param Map<string, string> $variables
     */
    private function __construct(private Map $variables)
    {
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

    /**
     * @psalm-pure
     *
     * @param list<array{string, string}> $variables
     */
    public static function test(array $variables): self
    {
        return self::of(Map::of(...$variables));
    }

    /**
     * @psalm-pure
     */
    public static function http(HttpEnvironment $env): self
    {
        return $env->reduce(
            new self(Map::of()),
            static fn(self $env, $key, $value) => $env->with($key, $value),
        );
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

    /**
     * @return Map<string, string>
     */
    public function all(): Map
    {
        return $this->variables;
    }
}
