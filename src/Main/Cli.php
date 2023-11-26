<?php
declare(strict_types = 1);

namespace Innmind\Framework\Main;

use Innmind\Framework\{
    Application,
    Environment as AppEnv,
};
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
        return static::configure(Application::cli($os, AppEnv::of($env->variables())))->run($env);
    }

    /**
     * @param Application<Environment, Environment> $app
     *
     * @return Application<Environment, Environment>
     */
    abstract protected function configure(Application $app): Application;
}
