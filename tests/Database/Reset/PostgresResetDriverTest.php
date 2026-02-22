<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\Reset\PostgresResetDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgresResetDriver::class)]
#[CoversMethod(PostgresResetDriver::class, 'clean')]
#[CoversMethod(PostgresResetDriver::class, 'counters')]
final class PostgresResetDriverTest extends TestCase
{
    /**
     * Проверим, что таблицы очищаются одним TRUNCATE ... CASCADE, а использованная последовательность — через setval.
     *
     * @see PostgresResetDriver::clean()
     */
    #[Test]
    public function cleanTruncatesTablesAndSetsUsedSequence(): void
    {
        $db = new FakeResetConnection();

        new PostgresResetDriver()->clean($db, ['"public"."a"', '"billing"."b"'], ['"public"."a_id_seq"' => 100]);

        self::assertSame([
            ['sql' => 'TRUNCATE TABLE "public"."a", "billing"."b" CASCADE', 'params' => []],
            ['sql' => 'SELECT setval(?::regclass, ?, true)', 'params' => ['"public"."a_id_seq"', 100]],
        ], $db->executed);
    }

    /**
     * Проверим, что неиспользованная в эталоне последовательность возвращается через RESTART.
     *
     * @see PostgresResetDriver::clean()
     */
    #[Test]
    public function cleanRestartsSequenceUnusedInBaseline(): void
    {
        $db = new FakeResetConnection();

        new PostgresResetDriver()->clean($db, [], ['"public"."a_id_seq"' => null]);

        self::assertSame(['ALTER SEQUENCE "public"."a_id_seq" RESTART'], $db->executedSql());
    }

    /**
     * Проверим, что счётчики читаются по последовательностям с экранированными именами, NULL сохраняется.
     *
     * @see PostgresResetDriver::counters()
     */
    #[Test]
    public function countersReadSequencesWithQuotedNames(): void
    {
        $db = new FakeResetConnection()->respond('FROM pg_sequences', [
            ['schemaname' => 'public', 'sequencename' => 'a_id_seq', 'last_value' => 7],
            ['schemaname' => 'billing', 'sequencename' => 'b_id_seq', 'last_value' => null],
        ]);

        self::assertSame(
            ['"public"."a_id_seq"' => 7, '"billing"."b_id_seq"' => null],
            new PostgresResetDriver()->counters($db, 'app_autotests'),
        );
    }
}
