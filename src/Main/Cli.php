<?php
declare(strict_types = 1);

namespace Innmind\Framework\Main;

use Innmind\Framework\Application;
use Innmind\CLI\{
    Main as MainCli,
    Environment,
};
use Innmind\OperatingSystem\OperatingSystem;

abstract class Main extends MainCli
{
    protected function main(Environment $env, OperatingSystem $os): Environment
    {
        return self::configure(Application::cli($os, $env->variables()))->runCli($env);
    }

    abstract protected function configure(Application $app): Application;
}
