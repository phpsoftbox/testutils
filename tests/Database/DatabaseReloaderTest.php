<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function ltrim;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(DatabaseReloader::class)]
#[CoversMethod(DatabaseReloader::class, 'reload')]
#[CoversMethod(DatabaseReloader::class, 'withMode')]
#[CoversMethod(DatabaseReloader::class, 'withConnections')]
#[CoversMethod(DatabaseReloader::class, 'mode')]
final class DatabaseReloaderTest extends TestCase
{
    private function mariadbDsn(string $database): string
    {
        return sprintf('mariadb://user:pass@localhost:3306/%s', ltrim($database, '/'));
    }

    /**
     * Проверяет, что дамп создается, если его нет.
     */
    #[Test]
    public function createsDumpWhenMissing(): void
    {
        $tmpDir = sys_get_temp_dir() . '/psb-test-utils-tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $dumpFile = $tmpDir . '/default-mariadb.sql';
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }

        $connection = new DatabaseReloaderConnection(
            'default',
            $this->mariadbDsn('main'),
            $this->mariadbDsn('test'),
        );

        $config = new DatabaseReloaderConfig([$connection], $tmpDir, keepDumpFiles: true);
        $runner = new FakeCommandRunner();

        $reloader = new DatabaseReloader($config, $runner);

        $reloader->reload($connection);

        $this->assertTrue(file_exists($dumpFile));

        $dumpCommands = array_filter(
            $runner->commands,
            static fn ($command): bool => $command->stdoutFile === $dumpFile,
        );

        $this->assertCount(1, $dumpCommands);
    }

    /**
     * Проверяет, что повторный запуск не пересоздает дамп.
     */
    #[Test]
    public function skipsDumpWhenFileExists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/psb-test-utils-tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $dumpFile = $tmpDir . '/default-mariadb.sql';
        file_put_contents($dumpFile, "-- existing --\n");

        $connection = new DatabaseReloaderConnection(
            'default',
            $this->mariadbDsn('main'),
            $this->mariadbDsn('test'),
        );

        $config = new DatabaseReloaderConfig([$connection], $tmpDir, keepDumpFiles: true);
        $runner = new FakeCommandRunner();

        $reloader = new DatabaseReloader($config, $runner);

        $reloader->reload($connection);

        $dumpCommands = array_filter(
            $runner->commands,
            static fn ($command): bool => $command->stdoutFile === $dumpFile,
        );

        $this->assertCount(0, $dumpCommands);
    }

    /**
     * Проверяет, что временный дамп удаляется без dumpDirectory.
     */
    #[Test]
    public function removesTempDumpWhenNoDumpDirectoryProvided(): void
    {
        $baseDir = sys_get_temp_dir() . '/psb-test-utils';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $dumpFile = $baseDir . '/default-mariadb.sql';
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }

        $connection = new DatabaseReloaderConnection(
            'default',
            $this->mariadbDsn('main'),
            $this->mariadbDsn('test'),
        );

        $config = new DatabaseReloaderConfig([$connection], '');
        $runner = new FakeCommandRunner();

        $reloader = new DatabaseReloader($config, $runner);

        $reloader->reload($connection);

        $this->assertFalse(file_exists($dumpFile));
    }

    /**
     * Проверяет переключение режима и фильтрацию подключений.
     */
    #[Test]
    public function withModeAndConnectionsReturnsNewInstance(): void
    {
        $tmpDir = sys_get_temp_dir() . '/psb-test-utils-tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $defaultDump = $tmpDir . '/default-mariadb.sql';
        $searchDump  = $tmpDir . '/search-mariadb.sql';
        if (file_exists($defaultDump)) {
            unlink($defaultDump);
        }
        if (file_exists($searchDump)) {
            unlink($searchDump);
        }

        $default = new DatabaseReloaderConnection(
            'default',
            $this->mariadbDsn('main'),
            $this->mariadbDsn('test'),
        );

        $search = new DatabaseReloaderConnection(
            'search',
            $this->mariadbDsn('search'),
            $this->mariadbDsn('search_test'),
        );

        $config = new DatabaseReloaderConfig([$default, $search], $tmpDir, keepDumpFiles: true);
        $runner = new FakeCommandRunner();

        $reloader = new DatabaseReloader($config, $runner);

        $filtered = $reloader
            ->withMode('transaction')
            ->withConnections(['search']);

        $this->assertSame('dump', $reloader->mode());
        $this->assertSame('transaction', $filtered->mode());

        $filtered->withMode('dump')->reloadAll();

        $this->assertFalse(file_exists($defaultDump));
        $this->assertTrue(file_exists($searchDump));
    }
}
