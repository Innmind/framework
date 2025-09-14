<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http\Route;

use Innmind\DI\Container;
use Innmind\Router\{
    Component,
    Pipe,
};
use Innmind\Http\Response;
use Innmind\Immutable\SideEffect;

/**
 * @psalm-immutable
 */
interface Reference extends \UnitEnum
{
    /**
     * @return callable(Pipe, Container): Component<SideEffect, Response>
     */
    public function route(): callable;
}
