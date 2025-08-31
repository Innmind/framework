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
        /**
         * @psalm-suppress InvalidReturnStatement Let the app crash in case of a misuse
         * @var Attempt<Environment>
         */
        return static::configure(Application::asyncHttp($os))->run($env);
    }

    /**
     * @param Application<Environment, Attempt<Environment>> $app
     *
     * @return Application<Environment, Attempt<Environment>>
     */
    abstract protected function configure(Application $app): Application;
}
