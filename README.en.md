# Triangle Middleware

[![Packagist version](https://img.shields.io/packagist/v/triangle/middleware)](https://packagist.org/packages/triangle/middleware)
[![PHP requirement](https://img.shields.io/packagist/dependency-v/triangle/middleware/php)](https://packagist.org/packages/triangle/middleware)

HTTP request middleware support for Triangle applications. This package provides a middleware contract and a loader that collects handlers from application configuration, routes, and controller attributes. The integrating application, such as Triangle HTTP, invokes the chain and converts its result into an HTTP response.

[Документация на русском](README.md)

## Requirements and compatibility

- PHP 8.1 or later (`^8.1` in Composer).
- `triangle/router` `^2.0`.
- The current source branch declares `triangle/engine` `^8.1 || ^9.0`.

Triangle Engine 9 has a stable `9.0.0` release, published on November 20, 2026. Middleware's `^8.1 || ^9.0` constraint allows Engine 8.1 up to 9.0 and Engine 9.x. Check the versions actually selected by Composer with `composer show triangle/middleware triangle/engine`.

## Installation

```sh
composer require triangle/middleware
```

## Middleware contract

```php
<?php

use Triangle\Middleware\MiddlewareInterface;

final class RequestLogMiddleware implements MiddlewareInterface
{
    public function process($request, callable $handler)
    {
        // Inspect or modify the request before passing it on.
        $response = $handler($request);

        // Inspect or modify the downstream response.
        return $response;
    }
}
```

Calling `$handler($request)` passes the request to the next handler. Middleware may return a response without invoking the handler, or return the downstream result. The request and response objects are defined by the integrating application.

`MiddlewareInterface` is not a strict PSR-15 implementation: the request parameter and return value are untyped, and the handler is a `callable` rather than a typed PSR-15 `RequestHandlerInterface`. Do not assume PSR-7/PSR-15 compatibility without an adapter and verification of your application's request and response types.

## Configuration

The component ships `src/Config/middleware.php` with an empty list for the `''` application. `Triangle\Middleware\Bootstrap` accepts a map from application names to lists of middleware classes with a `process` method:

```php
<?php

return [
    '@' => [RequestLogMiddleware::class],
    'api' => [ApiAccessMiddleware::class],
];
```

`@` denotes the shared middleware scope; an application name such as `api` denotes middleware for that application. Configuration entries must be arrays. The loader checks for a `process` method, but does not require the class to implement `MiddlewareInterface`. Invalid structure or a missing method raises `RuntimeException`.

The bootstrap also reads Engine global configuration, static configuration, application configuration, and plugin configuration. `getMiddleware()` includes route middleware, `Triangle\Annotation\Middleware` attributes on controller classes or methods, and a controller's public `middleware` property when present. These mechanisms require the corresponding Triangle components; this package is not a standalone HTTP server.

### Ordering and scope

`getMiddleware()` returns a prepared list for wrapping a handler. Triangle HTTP applies the list through nested calls: each middleware receives the request and a callable for the next handler. The effective sequence depends on configuration scope and declaration site; verify ordering against the Engine HTTP version in use before relying on side-effect order.

Global middleware can be omitted for an individual lookup with `withGlobal: false`. Registration is stored in process-wide static state. Loading configuration repeatedly in the same process appends entries; it does not clear prior registrations.

## Tests and verification limits

`composer test` runs the middleware bootstrap regression suite. It covers registration order and scope for global, plugin and app middleware, `withGlobal: false`, and errors for invalid configuration. `composer lint` checks PHP syntax in source and test files.

GitHub Actions runs both checks on PHP 8.1 and 8.5. This library does not track `composer.lock`, so each CI run resolves versions within the constraints in `composer.json`. The tests cover bootstrap behavior and the prepared handler list; they do not exercise a complete HTTP request through Triangle HTTP.

Run the local checks with:

```sh
composer update --prefer-dist --no-interaction --no-progress
composer validate --no-check-publish --no-interaction
composer lint
composer test
```

For HTTP-level behavior, use integration tests in an application with its installed Engine and HTTP component versions.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
