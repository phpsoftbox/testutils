# PhpSoftBox TestUtils Docs

Документация компонента `phpsoftbox/test-utils`.

## Содержание

1. [Установка и Bootstrap](01-installation-and-bootstrap.md)
2. [Базовые TestCase и HTTP-клиент](02-test-cases-and-http.md)
3. [Перезагрузка БД](03-database-reloader.md)
4. [Inertia и Snapshot-тестирование](04-snapshots-and-inertia.md)
5. [Fixture API (подробно)](05-fixtures-overview.md)
6. [Интеграция Fixture в приложение](06-fixtures-integration.md)

## Что важно знать про фикстуры

- `FixtureRunner` и `FixtureContext` не зависят от `ContainerInterface`.
- Контекст фикстур stateful (`refs`, `loadOnce`) и обычно создается на тест/тест-класс.
- Area/domain-специфика остается в приложении (`tests/Utils/...`), а не в `TestUtils`.
