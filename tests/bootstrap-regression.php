<?php declare(strict_types=1);

/**
 * @package Triangle Middleware Component
 * @link https://github.com/Triangle-org/Middleware
 * @author Ivan Zorin <creator@localzet.com>
 * @copyright Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Triangle\Engine\Config;
use Triangle\Middleware\Bootstrap;

final class GlobalMiddlewareOne
{
    public static function process(mixed $request, callable $handler): mixed {}
}

final class GlobalMiddlewareTwo
{
    public static function process(mixed $request, callable $handler): mixed {}
}

final class PluginGlobalMiddleware
{
    public static function process(mixed $request, callable $handler): mixed {}
}

final class PluginAppMiddlewareOne
{
    public static function process(mixed $request, callable $handler): mixed {}
}

final class PluginAppMiddlewareTwo
{
    public static function process(mixed $request, callable $handler): mixed {}
}

final class MiddlewareWithoutProcess {}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

function assertRuntimeException(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }

    throw new RuntimeException($message);
}

Config::set(['app' => ['plugin_alias' => 'plugin']]);

Bootstrap::load(['@' => [GlobalMiddlewareOne::class, GlobalMiddlewareTwo::class]]);
Bootstrap::load(['' => [PluginGlobalMiddleware::class]], 'acme');
Bootstrap::load(['plugin.acme.api' => [PluginAppMiddlewareOne::class, PluginAppMiddlewareTwo::class]]);

$middleware = Bootstrap::getMiddleware('acme', 'api', static fn () => null, null);
assertSameValue(
    [
        [PluginAppMiddlewareTwo::class, 'process'],
        [PluginAppMiddlewareOne::class, 'process'],
        [PluginGlobalMiddleware::class, 'process'],
        [GlobalMiddlewareTwo::class, 'process'],
        [GlobalMiddlewareOne::class, 'process'],
    ],
    $middleware,
    'Middleware must be scoped and returned in reverse registration order.'
);

assertSameValue(
    [[PluginAppMiddlewareTwo::class, 'process'], [PluginAppMiddlewareOne::class, 'process']],
    Bootstrap::getMiddleware('acme', 'api', static fn () => null, null, false),
    'withGlobal=false must keep app middleware and omit plugin/global middleware.'
);
assertSameValue(
    [[PluginGlobalMiddleware::class, 'process'], [GlobalMiddlewareTwo::class, 'process'], [GlobalMiddlewareOne::class, 'process']],
    Bootstrap::getMiddleware('acme', 'other', static fn () => null, null),
    'Plugin global middleware must apply to other apps within the same plugin.'
);
assertSameValue(
    [[GlobalMiddlewareTwo::class, 'process'], [GlobalMiddlewareOne::class, 'process']],
    Bootstrap::getMiddleware('other', 'api', static fn () => null, null),
    'Global middleware must apply outside the configured plugin.'
);

assertRuntimeException(
    static fn () => Bootstrap::load(['api' => 'invalid']),
    'A non-array middleware list must throw RuntimeException.'
);
assertRuntimeException(
    static fn () => Bootstrap::load(['api' => [MiddlewareWithoutProcess::class]]),
    'A class without process() must throw RuntimeException.'
);

fwrite(STDOUT, "Middleware bootstrap regressions passed.\n");
