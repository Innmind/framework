# Changelog

## 2.3.1 - 2024-10-26

### Changed

- Use `static` closures as much as possible to reduce the probability of creating circular references by capturing `$this` as it can lead to memory root buffer exhaustion.

## 2.3.0 - 2024-08-01

### Added

- `Innmind\DI\Service` can now be used everywhere a service can be referenced

### Fixed

- `Innmind\Framework\Http\To` no longer raise Psalm errors when used as argument to `Application::route()`

## 2.2.0 - 2024-03-24

### Added

- Support for using enums as a service name

## 2.1.0 - 2024-03-10

### Added

- Support for `innmind/operating-system:~5.0`
- Support for `innmind/async-http-server:~3.0`

## 2.0.0 - 2023-11-26

### Added

- `Innmind\Framework\Http\Routes::append()`
- `Innmind\Framework\Http\Routes::add()` now also accepts `Innmind\Router\Under`

### Changed

- Requires `innmind/operating-system:~4.1`
- Requires `innmind/immutable:~5.2`
- Requires `innmind/filesystem:~7.0`
- Requires `innmind/http-server:~4.0`
- Requires `innmind/router:~4.1`
- Requires `innmind/innmind/async-http-server:~2.0`

### Fixed

- All routes are no longer kept in memory when no longer used

## 1.4.0 - 2023-09-24

### Added

- Support for `innmind/immutable:~5.0`

### Removed

- Support for PHP `8.1`

## 1.3.0 - 2023-05-01

### Added

- `Innmind\Framework\Application::route()`
- `Innmind\Framework\Http\To`

## 1.2.0 - 2023-04-29

### Added

- `Innmind\Framework\Environment::all()`

## 1.1.0 - 2023-02-26

### Added

- `Innmind\Framework\Main\Async\Http` as an (optional) experimental feature

## 1.0.0 - 2023-01-01

### Added

- `Innmind\Framework\Application`
- `Innmind\Framework\Main\Cli`
- `Innmind\Framework\Main\Http`
- `Innmind\Framework\Middleware`
- `Innmind\Framework\Middleware\Optional`
- `Innmind\Framework\Middleware\LoadDotEnv`
- `Innmind\Framework\Environment`
- `Innmind\Framework\Http\RequestHandler`
