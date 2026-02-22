<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\Reset\DumpSchemaHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DumpSchemaHasher::class)]
#[CoversMethod(DumpSchemaHasher::class, 'hash')]
#[CoversMethod(DumpSchemaHasher::class, 'containsData')]
final class DumpSchemaHasherTest extends TestCase
{
    /**
     * Проверим, что комментарии и пустые строки (например, дата генерации дампа) не меняют хеш.
     *
     * @see DumpSchemaHasher::hash()
     */
    #[Test]
    public function hashIgnoresCommentsAndEmptyLines(): void
    {
        $hasher = new DumpSchemaHasher();

        self::assertSame(
            $hasher->hash("-- Dump completed on 2026-09-21\nCREATE TABLE a (id int);\n", 'mariadb', '1'),
            $hasher->hash("CREATE TABLE a (id int);\n\n-- Dump completed on 2026-09-22\n", 'mariadb', '1'),
        );
    }

    /**
     * Проверим, что изменение схемы меняет хеш.
     *
     * @see DumpSchemaHasher::hash()
     */
    #[Test]
    public function hashChangesWithSchema(): void
    {
        $hasher = new DumpSchemaHasher();

        self::assertNotSame(
            $hasher->hash("CREATE TABLE a (id int);\n", 'mariadb', '1'),
            $hasher->hash("CREATE TABLE a (id int) AUTO_INCREMENT=5;\n", 'mariadb', '1'),
        );
    }

    /**
     * Проверим, что драйвер и версия стратегии входят в хеш.
     *
     * @see DumpSchemaHasher::hash()
     */
    #[Test]
    public function hashChangesWithDriverAndVersion(): void
    {
        $hasher = new DumpSchemaHasher();

        $base = $hasher->hash("CREATE TABLE a (id int);\n", 'mariadb', '1');

        self::assertNotSame($base, $hasher->hash("CREATE TABLE a (id int);\n", 'postgres', '1'));
        self::assertNotSame($base, $hasher->hash("CREATE TABLE a (id int);\n", 'mariadb', '2'));
    }

    /**
     * Проверим, что INSERT в дампе распознаётся как данные.
     *
     * @see DumpSchemaHasher::containsData()
     */
    #[Test]
    public function containsDataDetectsInsert(): void
    {
        self::assertTrue(new DumpSchemaHasher()->containsData("CREATE TABLE a (id int);\nINSERT INTO `a` VALUES (1);\n"));
    }

    /**
     * Проверим, что COPY ... FROM stdin (данные pg_dump) распознаётся как данные.
     *
     * @see DumpSchemaHasher::containsData()
     */
    #[Test]
    public function containsDataDetectsPostgresCopy(): void
    {
        self::assertTrue(new DumpSchemaHasher()->containsData("COPY public.a (id) FROM stdin;\n1\n\\.\n"));
    }

    /**
     * Проверим, что дамп только со схемой данных не содержит.
     *
     * @see DumpSchemaHasher::containsData()
     */
    #[Test]
    public function schemaOnlyDumpHasNoData(): void
    {
        self::assertFalse(new DumpSchemaHasher()->containsData("CREATE TABLE a (id int COMMENT 'insert into later');\n"));
    }
}
