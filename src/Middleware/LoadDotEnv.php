<?php
declare(strict_types = 1);

namespace Innmind\Framework\Middleware;

use Innmind\Framework\{
    Application,
    Middleware,
    Environment,
};
use Innmind\Filesystem\{
    File,
    Name,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Sequence,
    Str,
};

final class LoadDotEnv implements Middleware
{
    private Path $folder;

    private function __construct(Path $folder)
    {
        $this->folder = $folder;
    }

    public function __invoke(Application $app): Application
    {
        return $app->mapEnvironment(
            fn($env, $os) => $os
                ->filesystem()
                ->mount($this->folder)
                ->get(Name::of('.env'))
                ->match(
                    fn($file) => $this->add($env, $file),
                    static fn() => $env,
                ),
        );
    }

    public static function at(Path $folder): self
    {
        return new self($folder);
    }

    private function add(Environment $env, File $file): Environment
    {
        /** @psalm-suppress InvalidArgument Due to the empty sequence in the flatMap */
        return $file
            ->content()
            ->lines()
            ->map(static fn($line) => $line->str()->trim())
            ->filter(static fn($line) => !$line->empty())
            ->filter(static fn($line) => !$line->startsWith('#'))
            ->map(
                static fn($line) => $line
                    ->split('=')
                    ->map(static fn($chunk) => $chunk->toString()),
            )
            ->flatMap(static fn($chunks) => $chunks->match(
                static fn($key, $rest) => Sequence::of([
                    $key,
                    Str::of('=')->join($rest)->toString(),
                ]),
                static fn() => Sequence::of(),
            ))
            ->reduce(
                $env,
                static function(Environment $env, array $pair): Environment {
                    [$key, $value] = $pair;

                    return $env->with($key, $value);
                },
            );
    }
}
