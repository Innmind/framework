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
    Http\Route,
};
use Innmind\OperatingSystem\Factory;
use Innmind\CLI\{
    Environment\InMemory,
    Command,
    Command\Usage,
    Console,
};
use Innmind\DI\Service;
use Innmind\Http\{
    ServerRequest,
    Response,
    Method,
    Response\StatusCode,
    ProtocolVersion,
    Header\ContentType,
};
use Innmind\MediaType\MediaType;
use Innmind\Url\{
    Url,
    Path,
};
use Innmind\Immutable\{
    Str,
    Attempt,
};
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
    PHPUnit\Framework\TestCase,
};
use Fixtures\Innmind\Url\Url as FUrl;

enum Services implements Service
{
    case responseHandler;
    case service;
    case serviceA;
    case serviceB;
}

class ApplicationTest extends TestCase
{
    use BlackBox;

    public function testCliApplicationReturnsHelloWorldByDefault(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(Set::strings())->between(0, 10),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $arguments, $variables) {
                $app = Application::cli(Factory::build(), $env = Environment::test($variables));

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    $arguments,
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(["Hello world\n"], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testOrderOfMappingEnvironmentAndOperatingSystemIsKept(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(Set::strings())->between(0, 10),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $arguments, $variables) {
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
                ))->unwrap();

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
                ))->unwrap();

                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testRunDefaultCommand(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command output'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command');
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['my command output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testRunSpecificCommand(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command A output'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command-a');
                        }
                    })
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command B output'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command-b');
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name', 'my-command-a'],
                    $variables,
                    '/',
                ))->unwrap();

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
                ))->unwrap();

                $this->assertSame(['my command B output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testServicesAreNotLoadedIfNotUsed(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(Set::strings())->between(0, 10),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $arguments, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->service(Services::service, static fn() => throw new \Exception);

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    $arguments,
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(["Hello world\n"], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testServicesAreAccessibleToCommands(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn($get) => new class($get(Services::service)) implements Command {
                        public function __construct(
                            private Str $output,
                        ) {
                        }

                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output($this->output);
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command');
                        }
                    })
                    ->service(Services::service, static fn() => Str::of('my command output'));

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['my command output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testServiceDependencies(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn($get) => new class($get(Services::serviceA)) implements Command {
                        public function __construct(
                            private Str $output,
                        ) {
                        }

                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output($this->output);
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command');
                        }
                    })
                    ->service(Services::serviceA, static fn($get) => Str::of('my command output')->append($get(Services::serviceB)->toString()))
                    ->service(Services::serviceB, static fn() => Str::of(' twice'));

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['my command output twice'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testUnusedCommandIsNotLoaded(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command A output'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command-a');
                        }
                    })
                    ->command(static fn() => new class implements Command {
                        public function __construct()
                        {
                            throw new \Exception;
                        }

                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command B output'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command-b');
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name', 'my-command-a'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['my command A output'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testDecoratingCommands(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command output'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command');
                        }
                    })
                    ->mapCommand(static fn($command) => new class($command) implements Command {
                        public function __construct(
                            private Command $inner,
                        ) {
                        }

                        public function __invoke(Console $console): Attempt
                        {
                            return ($this->inner)($console)->flatMap(
                                static fn($console) => $console->output(Str::of('decorated')),
                            );
                        }

                        public function usage(): Usage
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
                ))->unwrap();

                $this->assertSame(['my command output', 'decorated'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testCommandDecoratorIsAppliedOnlyOnTheWishedOne(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                static $testRuns = 0;
                ++$testRuns;
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command output A'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command-a');
                        }
                    })
                    ->command(static fn() => new class implements Command {
                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of('my command output B'));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('my-command-b');
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

                        public function __invoke(Console $console): Attempt
                        {
                            return ($this->inner)($console)->flatMap(
                                fn($console) => $console->output(Str::of((string) $this->instances)),
                            );
                        }

                        public function usage(): Usage
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
                ))->unwrap();

                $this->assertSame(['my command output B', (string) $testRuns], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testMiddleware(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
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

                        public function __invoke(Console $console): Attempt
                        {
                            return $console
                                ->output(Str::of($this->env->get('foo')))
                                ->flatMap(fn($console) => $console->output(Str::of($this->env->get('bar'))))
                                ->flatMap(fn($console) => $console->output(Str::of($this->env->get('baz'))));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('watev');
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['bar', 'baz', 'foo'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testOptionalMiddleware(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
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

                        public function __invoke(Console $console): Attempt
                        {
                            return $console->output(Str::of($this->env->get('foo')));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('watev');
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['bar'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testLoadDotEnv(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::sequence(Set::strings())->between(0, 10),
                Set::of(true, false),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($inputs, $interactive, $variables) {
                $app = Application::cli(Factory::build(), Environment::test($variables))
                    ->map(LoadDotEnv::at(Path::of(__DIR__.'/../fixtures/')))
                    ->command(static fn($_, $__, $env) => new class($env) implements Command {
                        public function __construct(
                            private $env,
                        ) {
                        }

                        public function __invoke(Console $console): Attempt
                        {
                            return $console
                                ->output(Str::of($this->env->get('FOO')))
                                ->flatMap(fn($console) => $console->output(Str::of($this->env->get('PASSWORD'))));
                        }

                        public function usage(): Usage
                        {
                            return Usage::parse('watev');
                        }
                    });

                $env = $app->run(InMemory::of(
                    $inputs,
                    $interactive,
                    ['script-name'],
                    $variables,
                    '/',
                ))->unwrap();

                $this->assertSame(['bar', 'foo=" \n watev; bar!'], $env->outputs());
                $this->assertNull($env->exitCode()->match(
                    static fn($exit) => $exit,
                    static fn() => null,
                ));
            });
    }

    public function testHttpApplicationReturnsNotFoundByDefault(): BlackBox\Proof
    {
        return $this
            ->forAll(
                FUrl::any(),
                Set::of(...Method::cases()),
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($url, $method, $protocol, $variables) {
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

    public function testMatchRoutes(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($protocol, $variables) {
                $responseA = Response::of(StatusCode::ok, $protocol);
                $responseB = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route(
                        fn($pipe) => $pipe
                            ->get()
                            ->endpoint('/foo')
                            ->handle(function($request) use ($protocol, $responseA) {
                                $this->assertSame($protocol, $request->protocolVersion());

                                return Attempt::result($responseA);
                            })
                            ->or(
                                $pipe
                                    ->get()
                                    ->endpoint('/bar')
                                    ->handle(function($request) use ($protocol, $responseB) {
                                        $this->assertSame($protocol, $request->protocolVersion());

                                        return Attempt::result($responseB);
                                    }),
                            ),
                    );

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

    public function testRouteShortDeclaration(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($protocol, $variables) {
                $responseA = Response::of(StatusCode::ok, $protocol);
                $responseB = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route(
                        fn($pipe) => $pipe
                            ->get()
                            ->endpoint('/foo')
                            ->handle(function($request) use ($protocol, $responseA) {
                                $this->assertSame($protocol, $request->protocolVersion());

                                return Attempt::result($responseA);
                            }),
                    )
                    ->route(
                        fn($pipe) => $pipe
                            ->post()
                            ->endpoint('/bar')
                            ->handle(function($request) use ($protocol, $responseB) {
                                $this->assertSame($protocol, $request->protocolVersion());

                                return Attempt::result($responseB);
                            }),
                    );

                $response = $app->run(ServerRequest::of(
                    Url::of('/foo'),
                    Method::get,
                    $protocol,
                ));

                $this->assertSame($responseA, $response);

                $response = $app->run(ServerRequest::of(
                    Url::of('/bar'),
                    Method::post,
                    $protocol,
                ));

                $this->assertSame($responseB, $response);
            });
    }

    public function testRouteToService(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($protocol, $variables) {
                $expected = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route(
                        static fn($pipe, $container) => $pipe
                            ->get()
                            ->endpoint('/foo')
                            ->handle($container(Services::responseHandler)),
                    )
                    ->service(Services::responseHandler, static fn() => new class($expected) {
                        public function __construct(private $response)
                        {
                        }

                        public function __invoke()
                        {
                            return Attempt::result($this->response);
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

    public function testRouteToServiceShortcut(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($protocol, $variables) {
                $expected = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route(Route::get(
                        '/foo',
                        Services::responseHandler,
                    ))
                    ->service(Services::responseHandler, static fn() => new class($expected) {
                        public function __construct(private $response)
                        {
                        }

                        public function __invoke()
                        {
                            return Attempt::result($this->response);
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

    public function testMapRequestHandler(): BlackBox\Proof
    {
        return $this
            ->forAll(
                FUrl::any(),
                Set::of(...Method::cases()),
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($url, $method, $protocol, $variables) {
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
                                $response->headers()(ContentType::of(new MediaType(
                                    'application',
                                    'octet-stream',
                                ))),
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

    public function testAllowToSpecifyHttpNotFoundRequestHandler(): BlackBox\Proof
    {
        return $this
            ->forAll(
                FUrl::any(),
                Set::of(...Method::cases()),
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($url, $method, $protocol, $variables) {
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

    public function testMatchMethodAllowed(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($protocol, $variables) {
                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route(
                        static fn($pipe) => $pipe
                            ->endpoint('/foo')
                            ->any(
                                $pipe
                                    ->get()
                                    ->handle(static fn($request) => Attempt::result(
                                        Response::of(
                                            StatusCode::ok,
                                            $request->protocolVersion(),
                                        ),
                                    )),
                            ),
                    );

                $response = $app->run(ServerRequest::of(
                    Url::of('/foo'),
                    Method::head,
                    $protocol,
                ));

                $this->assertSame(405, $response->statusCode()->toInt());
                $this->assertSame($protocol, $response->protocolVersion());
            });
    }

    public function testMapRoute(): BlackBox\Proof
    {
        return $this
            ->forAll(
                Set::of(...ProtocolVersion::cases()),
                Set::sequence(
                    Set::compose(
                        static fn($key, $value) => [$key, $value],
                        Set::strings()->randomize(),
                        Set::strings(),
                    ),
                )->between(0, 10),
            )
            ->prove(function($protocol, $variables) {
                $response = Response::of(StatusCode::ok, $protocol);
                $expected = Response::of(StatusCode::ok, $protocol);

                $app = Application::http(Factory::build(), Environment::test($variables))
                    ->route(Route::get(
                        '/foo',
                        Services::responseHandler,
                    ))
                    ->mapRoute(fn($component) => $component->map(
                        function($out) use ($response, $expected) {
                            $this->assertSame($out, $response);

                            return $expected;
                        },
                    ))
                    ->service(Services::responseHandler, static fn() => new class($response) {
                        public function __construct(private $response)
                        {
                        }

                        public function __invoke()
                        {
                            return Attempt::result($this->response);
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
}
