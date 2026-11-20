# Triangle Middleware

[![Packagist version](https://img.shields.io/packagist/v/triangle/middleware)](https://packagist.org/packages/triangle/middleware)
[![PHP requirement](https://img.shields.io/packagist/dependency-v/triangle/middleware/php)](https://packagist.org/packages/triangle/middleware)

Промежуточные обработчики HTTP-запросов для приложений Triangle. Пакет предоставляет контракт middleware и загрузчик, который собирает обработчики из конфигурации приложения, маршрута и атрибутов контроллера. Вызов цепочки и преобразование результата в HTTP-ответ выполняет интегрирующее приложение, например Triangle HTTP.

[English documentation](README.en.md)

## Требования и совместимость

- PHP 8.1 или новее (`^8.1` в Composer).
- `triangle/router` версии `^2.0`.
- Текущая исходная ветка объявляет `triangle/engine` `^8.1 || ^9.0`.

Engine 9 пока не имеет стабильного тега. Ограничение в исходниках не подтверждает наличие опубликованного стабильного пакета с поддержкой Engine 9. Проверяйте разрешённые Composer версии командой `composer show triangle/middleware triangle/engine`.

## Установка

```sh
composer require triangle/middleware
```

## Контракт обработчика

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

Вызов `$handler($request)` передаёт запрос следующему обработчику. Middleware может вернуть ответ самостоятельно, не вызывая `$handler`, либо вернуть результат следующего обработчика. Тип запроса, тип результата и конкретный объект ответа определяются используемым приложением.

`MiddlewareInterface` не является строгой реализацией PSR-15: параметр запроса и возвращаемое значение не типизированы, а обработчик задан как `callable`, а не типизированный PSR-15 `RequestHandlerInterface`. Не предполагайте совместимость с PSR-7/PSR-15 без адаптера и проверки типов запросов/ответов вашего приложения.

## Конфигурация

Установщик компонента содержит пример файла `src/Config/middleware.php` с пустым списком для приложения `''`. Загрузчик `Triangle\Middleware\Bootstrap` принимает массив, где ключ — приложение, а значение — список классов middleware с методом `process`:

```php
<?php

return [
    '@' => [RequestLogMiddleware::class],
    'api' => [ApiAccessMiddleware::class],
];
```

`@` задаёт middleware общего уровня; имя приложения, например `api`, задаёт обработчики приложения. Записи конфигурации должны быть массивами. При загрузке проверяется наличие метода `process`; соответствие `MiddlewareInterface` не обязательно. Если метод отсутствует или структура неверна, загрузчик выбрасывает `RuntimeException`.

Bootstrap также считывает глобальную конфигурацию Engine, статическую конфигурацию, конфигурацию приложений и плагинов. `getMiddleware()` добавляет middleware маршрута, атрибута `Triangle\Annotation\Middleware` на классе/методе контроллера и публичного свойства контроллера `middleware`, если они заданы. Эти механизмы требуют соответствующих компонентов Triangle и не являются отдельным HTTP-сервером.

### Порядок и область действия

`getMiddleware()` возвращает подготовленный список для дальнейшего оборачивания обработчика. В Engine HTTP список применяется через вложенные вызовы: каждый middleware получает запрос и callable следующего обработчика. Фактическая последовательность зависит от уровня конфигурации и места объявления; проверьте порядок для используемой версии Engine HTTP, прежде чем полагаться на порядок побочных эффектов.

Глобальные обработчики могут отключаться для конкретного вызова параметром `withGlobal: false`. Регистрация кэшируется в статическом состоянии процесса; повторная загрузка конфигурации в том же процессе добавляет элементы, а не очищает ранее зарегистрированные.

## Проверки и ограничения тестирования

В этом репозитории нет каталога тестов, dev-зависимости PHPUnit или Composer script для тестирования. Workflow GitHub Actions проверяет манифест Composer, устанавливает зависимости и выполняет `php -l` для PHP-файлов; шаг тестов сообщает, что скрипт не объявлен. Эти проверки не подтверждают поведение middleware в полном HTTP-запросе.

Для локальной проверки тех же базовых условий используйте команды из CI:

```sh
composer validate --no-check-publish --no-interaction
composer install --prefer-dist --no-interaction --no-progress --no-scripts
find . -path './vendor' -prune -o -path './.git' -prune -o -name '*.php' -type f -print0 | xargs -0 -n1 php -l
```

Запускайте интеграционные проверки в приложении с теми версиями Engine и HTTP-компонента, которые использует проект.

## Лицензия

AGPL-3.0-or-later. См. [LICENSE](LICENSE).
