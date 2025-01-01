<?php declare(strict_types=1);

/**
 * @package     Triangle Middleware Component
 * @link        https://github.com/Triangle-org/Middleware
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2023-2024 Triangle Framework Team
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *              For any questions, please contact <triangle@localzet.com>
 */

namespace Triangle\Middleware;

use ReflectionAttribute;
use Closure;
use localzet\Server;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Triangle\Annotation\Middleware;
use Triangle\Engine\BootstrapInterface;
use Triangle\Engine\Plugin;
use Triangle\Router\RouteObject;
use function array_merge;
use function array_reverse;
use function is_array;
use function method_exists;

/**
 * Класс Middleware
 * Этот класс представляет собой контейнер для промежуточного ПО (Middleware).
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @var array Массив экземпляров промежуточного ПО
     */
    protected static array $instances = [];

    public static function start(?Server $server = null): void
    {
        if (!$server) {
            return;
        }

        $config = config();

        self::load($config['middleware'] ?? []);
        self::load(['__static__' => $config['static']['middleware'] ?? []]);

        Plugin::app_reduce(function ($plugin, $config) {
            self::load($config['middleware'] ?? [], $plugin);
            self::load(['__static__' => $config['static']['middleware'] ?? []], $plugin);
        });

        Plugin::plugin_reduce(function ($vendor, $plugins, $plugin, $config) {
            self::load($config['middleware'] ?? [], $plugin);
            self::load(['__static__' => $config['static']['middleware'] ?? []]);
        });
    }

    /**
     * Загружает промежуточное ПО.
     *
     * @param array $list Массив конфигурации промежуточного ПО
     * @param string $plugin Имя плагина (необязательно)
     * @return void
     * @throws RuntimeException Если конфигурация промежуточного ПО некорректна
     */
    public static function load(array $list, string $plugin = ''): void
    {
        foreach ($list as $app => $middlewares) {
            if (!is_array($middlewares)) {
                throw new RuntimeException('Некорректная конфигурация промежуточного ПО');
            }
            if ($app === '@') {
                $plugin = '';
            }
            if (str_contains($app, config('app.plugin_alias', 'plugin') . '.')) {
                $explode = explode('.', $app, 4);
                $plugin = $explode[1];
                $app = $explode[2] ?? '';
            }
            foreach ($middlewares as $class) {
                if (method_exists($class, 'process')) {
                    static::$instances[$plugin][$app][] = [$class, 'process'];
                } else {
                    throw new RuntimeException("Промежуточный $class::process не существует");
                }
            }
        }
    }

    /**
     * Возвращает промежуточное ПО для указанного плагина и приложения.
     *
     * @param string $plugin Имя плагина
     * @param string $app Имя приложения
     * @param bool $withGlobal Флаг, указывающий, включать ли глобальное промежуточное ПО
     * @return array Массив промежуточного ПО
     */
    public static function getMiddleware(string $plugin, string $app, string|array|Closure $controller, RouteObject|null $route, bool $withGlobal = true): array
    {
        $isController = is_array($controller) && is_string($controller[0]);

        // Глобальное промежуточное ПО
        $globalMiddleware = $withGlobal ? static::$instances['']['@'] ?? [] : [];
        $appGlobalMiddleware = $withGlobal && isset(static::$instances[$plugin]['']) ? static::$instances[$plugin][''] : [];

        $middlewares = $routeMiddlewares = [];
        if ($route) {
            foreach (array_reverse($route->getMiddleware()) as $className) {
                $routeMiddlewares[] = [$className, 'process'];
            }
        }
        if ($isController && $controller[0] && class_exists($controller[0])) {
            $reflectionClass = new ReflectionClass($controller[0]);
            self::prepareAttributeMiddlewares($middlewares, $reflectionClass);
            if ($reflectionClass->hasProperty('middleware')) {
                $defaultProperties = $reflectionClass->getDefaultProperties();
                $middlewaresClasses = $defaultProperties['middleware'];
                foreach ((array)$middlewaresClasses as $className) {
                    $middlewares[] = [$className, 'process'];
                }
            }

            $middlewares = array_merge($middlewares, $routeMiddlewares);
            if ($reflectionClass->hasMethod($controller[1])) {
                self::prepareAttributeMiddlewares($middlewares, $reflectionClass->getMethod($controller[1]));
            }
        } else {
            $middlewares = array_merge($middlewares, $routeMiddlewares);
        }

        if ($app === '') {
            return array_reverse(array_merge($globalMiddleware, $appGlobalMiddleware, $middlewares));
        }
        // Промежуточное ПО для приложения
        $appMiddleware = static::$instances[$plugin][$app] ?? [];
        return array_reverse(array_merge($globalMiddleware, $appGlobalMiddleware, $appMiddleware, $middlewares));
    }

    /**
     * @param array $middlewares
     * @param ReflectionClass|ReflectionMethod $reflection
     * @return void
     */
    private static function prepareAttributeMiddlewares(array &$middlewares, ReflectionClass|ReflectionMethod $reflection): void
    {
        $middlewareAttributes = $reflection->getAttributes(Middleware::class, ReflectionAttribute::IS_INSTANCEOF);
        foreach ($middlewareAttributes as $middlewareAttribute) {
            $middlewareAttributeInstance = $middlewareAttribute->newInstance();
            $middlewares = array_merge($middlewares, $middlewareAttributeInstance->getMiddlewares());
        }
    }
}
