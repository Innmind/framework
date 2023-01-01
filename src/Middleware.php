<?php
declare(strict_types = 1);

namespace Innmind\Framework;

interface Middleware
{
    public function __invoke(Application $app): Application;
}
