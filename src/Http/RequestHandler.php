<?php
declare(strict_types = 1);

namespace Innmind\Framework\Http;

use Innmind\Http\Message\{
    ServerRequest,
    Response,
};

interface RequestHandler
{
    public function __invoke(ServerRequest $request): Response;
}
