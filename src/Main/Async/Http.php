<?php
declare(strict_types = 1);

namespace Innmind\Framework\Main\Async;

use Innmind\Framework\Application;
use Innmind\CLI\{
    Main,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Immutable\Attempt;

/**
 * @experimental
 */
abstract class Http extends Main
{
    #[\Override]
    protected function main(Environment $env, OperatingSystem $os): Attempt
    {
        return static::configure(Application::asyncHttp($os))->run($env);
    }

    /**
     * @param Application<Environment, Environment> $app
     *
     * @return Application<Environment, Environment>
     */
    abstract protected function configure(Application $app): Application;
}
