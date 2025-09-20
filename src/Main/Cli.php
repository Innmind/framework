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
use Innmind\Immutable\Attempt;

abstract class Cli extends Main
{
    #[\Override]
    protected function main(Environment $env, OperatingSystem $os): Attempt
    {
        return static::configure(Application::cli($os, AppEnv::of($env->variables())))->run($env);
    }

    /**
     * @param Application<Environment, Environment> $app
     *
     * @return Application<Environment, Environment>
     */
    abstract protected function configure(Application $app): Application;
}
