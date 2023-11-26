<?php
declare(strict_types = 1);

namespace Tests\Innmind\Framework;

use Innmind\Framework\{
    Application,
    Middleware,
    Environment,
    Middleware\Optional,
    Middleware\LoadDotEnv,
    Http\RequestHandler,
    Http\To,
};
use Innmind\OperatingSystem\Factory;
use Innmind\CLI\{
    Environment\InMemory,
    Command,
    Console,
};
use Innmind\Router\{
    Route,
    Under,
};
use Innmind\Http\{
    ServerRequest,
    Response,
    Method,
    Response\StatusCode,
    ProtocolVersion,
    Header\ContentType,
};
use Innmind\Url\{
    Url,
    Path,
};
use Innmind\UrlTemplate\Template;
use Innmind\Immutable\{
    Map,
    Str,
};
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
    PHPUnit\Framework\TestCase,
};
use Fixtures\Innmind\Url\Url as FUrl;

class ApplicationTest extends TestCase
{
    use BlackBox;

    public function testCliApplicationReturnsHelloWorldByDefault()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $arguments, $variables) {
                $app = Application::cli(Factory::build(), $env = Environment::test($variables));

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $arguments, $variables) {
                $os = Factory::build();
                $app = Application::cli($os, Environment::test($variables))
                    ->mapEnvironment(static fn($env) => $env->with('foo', 'bar'))
                    ->mapOperatingSystem(function($in, $env) use ($os) {
                        $this->assertSame($os, $in);
                        $this->assertSame('bar', $env->get('foo'));

                        return $in;
                    });

                $env = $app->run(InMemory::of(
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
                $app = Application::cli($os, Environment::test($variables))
                    ->mapOperatingSystem(function($in, $env) use ($os, $otherOs) {
                        $this->assertSame($os, $in);

                        return $otherOs;
                    })
                    ->mapEnvironment(function($env, $os) use ($otherOs) {
                        $this->assertSame($otherOs, $os);

                        return $env;
                    });

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
                Set\Strings::atLeast(1),
            )
            ->then(function($inputs, $interactive, $arguments, $variables, $service) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->service($service, static fn() => throw new \Exception);

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
                Set\Strings::atLeast(1),
            )
            ->then(function($inputs, $interactive, $variables, $service) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
                Set\Strings::atLeast(1),
                Set\Strings::atLeast(1),
            )
            ->then(function($inputs, $interactive, $variables, $serviceA, $serviceB) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                static $testRuns = 0;
                ++$testRuns;
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
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

                $env = $app->run(InMemory::of(
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

    public function testOptionalMiddleware()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $middleware = new class implements Middleware {
                    public function __invoke(Application $app): Application
                    {
                        return $app->mapEnvironment(static fn($env) => $env->with('foo', 'bar'));
                    }
                };

                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->map(Optional::of(Unknown::class, static fn() => throw new \Exception))
                    ->map(Optional::of($middleware::class, static fn() => $middleware))
                    ->command(static fn($_, $__, $env) => new class($env) implements Command {
                        public function __construct(
                            private $env,
                        ) {
                        }

                        public function __invoke(Console $console): Console
                        {
                            return $console->output(Str::of($this->env->get('foo')));
                        }

                        public function usage(): string
                        {
                            return 'watev';
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['bar'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testLoadDotEnv()
    {
        $this
            ->forAll(
                Set\Sequence::of(Set\Strings::any())->between(0, 10),
                Set\Elements::of(true, false),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->map(LoadDotEnv::at(Path::of(__DIR__.'/../fixtures/')))
                    ->command(static fn($_, $__, $env) => new class($env) implements Command {
                        public function __construct(
                            private $env,
                        ) {
                        }

                        public function __invoke(Console $console): Console
                        {
                            return $console
                                ->output(Str::of($this->env->get('FOO')))
                                ->output(Str::of($this->env->get('PASSWORD')));
                        }

                        public function usage(): string
                        {
                            return 'watev';
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ));

                $this->assertSame(['bar', 'foo=" \n watev; bar!'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testHttpApplicationReturnsNotFoundByDefault()
    {
        $this
            ->forAll(
                FUrl::any(),
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($url, $method, $protocol, $variables) {
                $app = Application::http(Factory::build(), Environment::test($variables));

                $response = $app->run(ServerRequest::of(
                    $url,
                    $method,
                    $protocol,
                ));

                $this->assertSame(StatusCode::notFound, $response->statusCode());
                $this->assertSame($protocol, $response->protocolVersion());
            });
    }

    public function testMatchRoutes()
    {
        $this
            ->forAll(
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($protocol, $variables) {
                $responseA = Response::of(StatusCode::ok, $protocol);
                $responseB = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->appendRoutes(fn($routes) => $routes->add(
                        Route::of(Method::get, Template::of('/foo'))->handle(function($request) use ($protocol, $responseA) {
                            $this->assertSame($protocol, $request->protocolVersion());

                            return $responseA;
                        }),
                    ))
                    ->appendRoutes(fn($routes) => $routes->add(
                        Route::of(Method::get, Template::of('/bar'))->handle(function($request) use ($protocol, $responseB) {
                            $this->assertSame($protocol, $request->protocolVersion());

                            return $responseB;
                        }),
                    ));

                $response = $app->run(ServerRequest::of(
                    Url::of('/foo'),
                    Method::get,
                    $protocol,
                ));

                $this->assertSame($responseA, $response);

                $response = $app->run(ServerRequest::of(
                    Url::of('/bar'),
                    Method::get,
                    $protocol,
                ));

                $this->assertSame($responseB, $response);
            });
    }

    public function testRouteShortDeclaration()
    {
        $this
            ->forAll(
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($protocol, $variables) {
                $responseA = Response::of(StatusCode::ok, $protocol);
                $responseB = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route('GET /foo', function($request) use ($protocol, $responseA) {
                        $this->assertSame($protocol, $request->protocolVersion());

                        return $responseA;
                    })
                    ->route('GET /bar', function($request) use ($protocol, $responseB) {
                        $this->assertSame($protocol, $request->protocolVersion());

                        return $responseB;
                    });

                $response = $app->run(ServerRequest::of(
                    Url::of('/foo'),
                    Method::get,
                    $protocol,
                ));

                $this->assertSame($responseA, $response);

                $response = $app->run(ServerRequest::of(
                    Url::of('/bar'),
                    Method::get,
                    $protocol,
                ));

                $this->assertSame($responseB, $response);
            });
    }

    public function testRouteToService()
    {
        $this
            ->forAll(
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($protocol, $variables) {
                $expected = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route('GET /foo', To::service('response-handler'))
                    ->service('response-handler', static fn() => new class($expected) {
                        public function __construct(private $response)
                        {
                        }

                        public function __invoke()
                        {
                            return $this->response;
                        }
                    });

                $response = $app->run(ServerRequest::of(
                    Url::of('/foo'),
                    Method::get,
                    $protocol,
                ));

                $this->assertSame($expected, $response);
            });
    }

    public function testMapRequestHandler()
    {
        $this
            ->forAll(
                FUrl::any(),
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($url, $method, $protocol, $variables) {
                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->mapRequestHandler(static fn($inner) => new class($inner) implements RequestHandler {
                        public function __construct(
                            private $inner,
                        ) {
                        }

                        public function __invoke(ServerRequest $request): Response
                        {
                            $response = ($this->inner)($request);

                            return Response::of(
                                $response->statusCode(),
                                $response->protocolVersion(),
                                $response->headers()(ContentType::of('application', 'octet-stream')),
                            );
                        }
                    });

                $response = $app->run(ServerRequest::of(
                    $url,
                    $method,
                    $protocol,
                ));

                $this->assertSame(StatusCode::notFound, $response->statusCode());
                $this->assertSame($protocol, $response->protocolVersion());
                $this->assertSame(
                    'Content-Type: application/octet-stream',
                    $response->headers()->get('content-type')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
            });
    }

    public function testAllowToSpecifyHttpNotFoundRequestHandler()
    {
        $this
            ->forAll(
                FUrl::any(),
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($url, $method, $protocol, $variables) {
                $expected = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->notFoundRequestHandler(function($request) use ($protocol, $expected) {
                        $this->assertSame($protocol, $request->protocolVersion());

                        return $expected;
                    });

                $response = $app->run(ServerRequest::of(
                    $url,
                    $method,
                    $protocol,
                ));

                $this->assertSame($expected, $response);
            });
    }

    public function testMatchMethodAllowed()
    {
        $this
            ->forAll(
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Sequence::of(
                    Set\Composite::immutable(
                        static fn($key, $value) => [$key, $value],
                        Set\Randomize::of(Set\Strings::any()),
                        Set\Strings::any(),
                    ),
                )->between(0, 10),
            )
            ->then(function($protocol, $variables) {
                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->appendRoutes(static fn($routes) => $routes->add(
                        Under::of(Template::of('/foo'))->route(Method::get),
                    ));

                $response = $app->run(ServerRequest::of(
                    Url::of('/foo'),
                    Method::head,
                    $protocol,
                ));

                $this->assertSame(405, $response->statusCode()->toInt());
                $this->assertSame($protocol, $response->protocolVersion());
            });
    }
}
