<?php
declare(strict_types = 1);

namespace Tests\Innmind\Framework;

use Innmind\Framework\{
    Application,
    Middleware,
};
use Innmind\OperatingSystem\Factory;
use Innmind\CLI\{
    Environment\InMemory,
    Command,
    Console,
};
use Innmind\Immutable\{
    Map,
    Str,
};
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
};

class ApplicationTest extends TestCase
{
    use BlackBox;

    public function testCliApplicationReturnsHelloWorldByDefault()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $arguments, $variables) {
                $app = Application::cli(Factory::build(), Map::of(...$variables));

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    $arguments,
                    $variables,
                    '/',
                ));

                $this->assertSame(["Hello world\n"], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testOrderOfMappingEnvironmentAndOperatingSystemIsKept()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $arguments, $variables) {
                $os = Factory::build();
                $app = Application::cli($os, Map::of(...$variables))
                    ->mapEnvironment(static fn($env) => $env->with('foo', 'bar'))
                    ->mapOperatingSystem(function($in, $env) use ($os) {
                        $this->assertSame($os, $in);
                        $this->assertSame('bar', $env->get('foo'));

                        return $in;
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    $arguments,
                    $variables,
                    '/',
                ));

                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));

                $otherOs = Factory::build();
                $app = Application::cli($os, Map::of(...$variables))
                    ->mapOperatingSystem(function($in, $env) use ($os, $otherOs) {
                        $this->assertSame($os, $in);

                        return $otherOs;
                    })
                    ->mapEnvironment(function($env, $os) use ($otherOs) {
                        $this->assertSame($otherOs, $os);

                        return $env;
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    $arguments,
                    $variables,
                    '/',
                ));

                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testRunDefaultCommand()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command output'));
                        }

                        public function usage(): string
                        {
                            return 'my-command';
                        }
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testRunSpecificCommand()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command A output'));
                        }

                        public function usage(): string
                        {
                            return 'my-command-a';
                        }
                    })
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command B output'));
                        }

                        public function usage(): string
                        {
                            return 'my-command-b';
                        }
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name', 'my-command-a'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command A output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name', 'my-command-b'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command B output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testServicesAreNotLoadedIfNotUsed()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
                Set\Strings::atLeast(1),
            )
            ->then(function($inputs, $interactive, $arguments, $variables, $service) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->service($service, static fn() => throw new \Exception);

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    $arguments,
                    $variables,
                    '/',
                ));

                $this->assertSame(["Hello world\n"], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testServicesAreAccessibleToCommands()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
                Set\Strings::atLeast(1),
            )
            ->then(function($inputs, $interactive, $variables, $service) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn($get) => new class($get($service)) implements Command {
                        public function __construct(
                            private Str $output,
                        ) {
                        }

                        public function __invoke(Console $console): Console
                        {
                            return $console->output($this->output);
                        }

                        public function usage(): string
                        {
                            return 'my-command';
                        }
                    })
                    ->service($service, static fn() => Str::of('my command output'));

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testServiceDependencies()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
                Set\Strings::atLeast(1),
                Set\Strings::atLeast(1),
            )
            ->then(function($inputs, $interactive, $variables, $serviceA, $serviceB) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn($get) => new class($get($serviceA)) implements Command {
                        public function __construct(
                            private Str $output,
                        ) {
                        }

                        public function __invoke(Console $console): Console
                        {
                            return $console->output($this->output);
                        }

                        public function usage(): string
                        {
                            return 'my-command';
                        }
                    })
                    ->service($serviceA, static fn($get) => Str::of('my command output')->append($get($serviceB)->toString()))
                    ->service($serviceB, static fn() => Str::of(' twice'));

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command output twice'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testUnusedCommandIsNotLoaded()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command A output'));
                        }

                        public function usage(): string
                        {
                            return 'my-command-a';
                        }
                    })
                    ->command(static fn() => new class implements Command {
                        public function __construct()
                        {
                            throw new \Exception;
                        }

                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command B output'));
                        }

                        public function usage(): string
                        {
                            return 'my-command-b';
                        }
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name', 'my-command-a'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command A output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testDecoratingCommands()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command output'));
                        }

                        public function usage(): string
                        {
                            return 'my-command';
                        }
                    })
                    ->mapCommand(static fn($command) => new class($command) implements Command {
                        public function __construct(
                            private Command $inner,
                        ) {
                        }

                        public function __invoke(Console $console): Console
                        {
                            return ($this->inner)($console)->output(Str::of('decorated'));
                        }

                        public function usage(): string
                        {
                            return $this->inner->usage();
                        }
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command output', 'decorated'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testCommandDecoratorIsAppliedOnlyOnTheWishedOne()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $variables) {
                static $testRuns = 0;
                ++$testRuns;
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command output A'));
                        }

                        public function usage(): string
                        {
                            return 'my-command-a';
                        }
                    })
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of('my command output B'));
                        }

                        public function usage(): string
                        {
                            return 'my-command-b';
                        }
                    })
                    ->mapCommand(static fn($command) => new class($command) implements Command {
                        private int $instances;
                        public function __construct(
                            private Command $inner,
                        ) {
                            static $instances = 0;
                            $this->instances = ++$instances;
                        }

                        public function __invoke(Console $console): Console
                        {
                            return ($this->inner)($console)->output(Str::of((string) $this->instances));
                        }

                        public function usage(): string
                        {
                            return $this->inner->usage();
                        }
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name', 'my-command-b'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['my command output B', (string) $testRuns], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testMiddleware()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any(), Set\Integers::between(0, 10)),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        new Set\Randomize(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                    Set\Integers::between(0, 10),
                ),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Map::of(...$variables))
                    ->map(new class implements Middleware {
                        public function __invoke(Application $app): Application
                        {
                            return $app->mapEnvironment(static fn($env) => $env->with('foo', 'bar'));
                        }
                    })
                    ->map(new class implements Middleware {
                        public function __invoke(Application $app): Application
                        {
                            return $app->mapEnvironment(static function($env) {
                                if ($env->get('foo') !== 'bar') {
                                    throw new \Exception;
                                }

                                return $env->with('bar', 'baz');
                            });
                        }
                    })
                    ->map(new class implements Middleware {
                        public function __invoke(Application $app): Application
                        {
                            return $app->mapEnvironment(static function($env) {
                                if ($env->get('bar') !== 'baz') {
                                    throw new \Exception;
                                }

                                return $env->with('baz', 'foo');
                            });
                        }
                    })
                    ->command(static fn($_, $__, $env) => new class($env) implements Command {
                        public function __construct(
                            private $env,
                        ) {
                        }

                        public function __invoke(Console $console): Console
                        {
                            return $console
                                ->output(Str::of($this->env->get('foo')))
                                ->output(Str::of($this->env->get('bar')))
                                ->output(Str::of($this->env->get('baz')));
                        }

                        public function usage(): string
                        {
                            return 'watev';
                        }
                    });

                $env = $app->runCli(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['bar', 'baz', 'foo'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }
}
