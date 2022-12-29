<?php
declare(strict_types = 1);

namespace Innmind\Framework\Main;

use Innmind\Framework\Application;
use Innmind\CLI\{
    Main,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;

abstract class Cli extends Main
{
    protected function main(Environment $env, OperatingSystem $os): Environment
    {
        /**
         * @psalm-suppress InvalidReturnStatement Let the app crash in case of a misuse
         * @var Environment
         */
        return self::configure(Application::cli($os, $env->variables()))->run($env);
    }

    abstract protected function configure(Application $app): Application;
}
