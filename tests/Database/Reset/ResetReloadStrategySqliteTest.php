<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderException;
use PhpSoftBox\TestUtils\Database\Reset\ResetStateRegistry;
use PhpSoftBox\TestUtils\Database\ResetReloadStrategy;
use PhpSoftBox\TestUtils\Tests\Database\FakeCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function is_file;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(ResetReloadStrategy::class)]
#[CoversMethod(ResetReloadStrategy::class, 'reload')]
final class ResetReloadStrategySqliteTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        ResetStateRegistry::clear();

        $this->directory = sys_get_temp_dir() . '/psb-test-utils-reset-sqlite-' . uniqid('', true);
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        ResetStateRegistry::clear();
    }

    /**
     * Проверим, что после изменений в тесте тестовый файл снова совпадает с копией основной БД.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function replacesTestFileWithMainDatabaseCopy(): void
    {
        file_put_contents($this->directory . '/app.sqlite', 'main');
        $connection = $this->connection();
        $strategy   = $this->strategy($connection);

        $strategy->reload($connection);
        // Тест изменил тестовую БД.
        file_put_contents($this->directory . '/app.sqlite_autotests', 'dirty');
        $strategy->reload($connection);

        self::assertSame('main', file_get_contents($this->directory . '/app.sqlite_autotests'));
    }

    /**
     * Проверим, что без основной БД шаблоном становится существующий тестовый файл, как в dump.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function usesExistingTestFileAsTemplateWhenMainMissing(): void
    {
        file_put_contents($this->directory . '/app.sqlite_autotests', 'prepared');
        $connection = $this->connection();
        $strategy   = $this->strategy($connection);

        $strategy->reload($connection);
        file_put_contents($this->directory . '/app.sqlite_autotests', 'dirty');
        $strategy->reload($connection);

        self::assertSame('prepared', file_get_contents($this->directory . '/app.sqlite_autotests'));
    }

    /**
     * Проверим, что файлы журнала -wal и -shm тестовой БД удаляются при сбросе.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function removesWalAndShmFiles(): void
    {
        file_put_contents($this->directory . '/app.sqlite', 'main');
        file_put_contents($this->directory . '/app.sqlite_autotests-wal', 'wal');
        file_put_contents($this->directory . '/app.sqlite_autotests-shm', 'shm');
        $connection = $this->connection();

        $this->strategy($connection)->reload($connection);

        self::assertFalse(is_file($this->directory . '/app.sqlite_autotests-wal'));
        self::assertFalse(is_file($this->directory . '/app.sqlite_autotests-shm'));
    }

    /**
     * Проверим, что для SQLite в памяти reset не поддерживается.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function rejectsMemoryDatabase(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'sqlite:///' . $this->directory . '/app.sqlite',
            'sqlite:///:memory:',
        );

        $this->expectException(DatabaseReloaderException::class);
        $this->expectExceptionMessage(':memory:');

        $this->strategy($connection)->reload($connection);
    }

    private function connection(): DatabaseReloaderConnection
    {
        return new DatabaseReloaderConnection(
            'default',
            'sqlite:///' . $this->directory . '/app.sqlite',
            'sqlite:///' . $this->directory . '/app.sqlite_autotests',
        );
    }

    private function strategy(DatabaseReloaderConnection $connection): ResetReloadStrategy
    {
        $config = new DatabaseReloaderConfig([$connection], $this->directory, keepDumpFiles: true);

        return new ResetReloadStrategy($config, new FakeCommandRunner(false), new FakeResetConnectionFactory());
    }
}
