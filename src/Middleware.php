<?php
declare(strict_types = 1);

namespace Innmind\Framework;

use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\CLI\Environment as CliEnv;

interface Middleware
{
    /**
     * @template I of ServerRequest|CliEnv
     * @template O of Response|CliEnv
     *
     * @param Application<I, O> $app
     *
     * @return Application<I, O>
     */
    public function __invoke(Application $app): Application;
}
