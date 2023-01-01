# Add variables to the environment

The framework exposes an [`Environment`](../src/Environment.php) object with values coming from `\getenv()`. If you want to add variables and make them available to the rest of your application you can do it like this:

```php
use Innmind\Framework\{
    Main\Cli,
    Main\Http,
    Application,
    Environment,
};

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->mapEnvironment(static fn(Environment $env) => $env->with(
            'MY_VARIABLE_NAME',
            "and it's value",
        ));
    }
};
```

## Loading a `.env` file

A common pattern is to put all your environment variables inside a `.env` file. The framework comes with a [middleware](middlewares.md) to do just that.

```php
use Innmind\Framework\{
    Main\Cli,
    Main\Http,
    Application,
    Middleware\LoadDotEnv,
};
use Innmind\Url\Path;

new class extends Http|Cli {
    protected function configure(Application $app): Application
    {
        return $app->map(LoadDotEnv::at(Path::of('somewhere/in/your/app/')));
    }
};
```

The path represents the folder containing the `.env` file (and the path must end with `/`).

The `.env` file itself must contain one variable per line with the format `NAME=value`. Everything after `=` is considered the value to avoid complex parsing. Empty lines or lines starting with `#` are ignored.
