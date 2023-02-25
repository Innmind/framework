<?php
declare(strict_types = 1);

namespace Innmind\Framework\Main\Async;

use Innmind\Framework\Application;
use Innmind\CLI\{
    Main,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;

/**
 * @experimental
 */
abstract class Http extends Main
{
    protected function main(Environment $env, OperatingSystem $os): Environment
    {
        /**
         * @psalm-suppress InvalidReturnStatement Let the app crash in case of a misuse
         * @var Environment
         */
        return static::configure(Application::asyncHttp($os))->run($env);
    }

    abstract protected function configure(Application $app): Application;
}
