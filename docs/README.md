# Framework

The philosophy behind this framework is to have a minimalist foundation to be able to build simple apps but can also accomodate for more complex applications through composition (of the configuration, commands, request handlers and more).

Another important design is to expose to you the input to handle and an abstraction of the operating system it runs on so you only need to focus on WHAT your app needs to do and NOT HOW.

These topics will guide you through the simplest cases to more complex ones:
- [Build an HTTP app](http.md)
- [Build a CLI app](cli.md)
- [Services](services.md)
- [Middlewares](middlewares.md)
- [Build an app that runs through HTTP and CLI](http-and-cli.md)
- [Testing](testing.md)
- [Add variables to the environment](environment.md)
- [Decorate the operating system](operating-system.md)

Experimental features:
- [Using an async http server](experiment/async-server.md)
