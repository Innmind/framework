# Async HTTP Server

The framework comes with an HTTP server entirely built in PHP allowing you to serve your app without extra dependencies in ther earlist stages of your project.

> **Note** This feature is optional, to use it you must before run `composer require innmind/async-http-server`.

To use it is similar to the standard [http](../http.md) handler, the first difference is the namespace of the main entrypoint:

```php
<?php
declare(strict_types = 1);

require 'path/to/composer/autoload.php';

use Innmind\Framework\{
    Main\Async\Http,
    Application,
};

new class extends Http {
    protected function configure(Application $app): Application
    {
        return $app;
    }
};
```

Note the namespace is `Main\Async\Http` instead of `Main\Http`. The other difference is instead of pointing your HTTP Server to the folder containing the php file you run the server via `php index.php`.

All the configuration of the `Application` object is identical to the other contexts.

> **Note** The server currently does have limitations, streamed requests (via `Transfer-Encoding`) are not supported and multipart requests are not parsed.
