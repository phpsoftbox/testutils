<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Snapshot;

use PhpSoftBox\TestUtils\Snapshot\JsonSnapshotAssert;
use PhpSoftBox\TestUtils\Snapshot\SnapshotConfig;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[CoversClass(JsonSnapshotAssert::class)]
#[CoversMethod(JsonSnapshotAssert::class, 'assertMatchesSnapshot')]
final class JsonSnapshotAssertTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/psb-test-utils-snapshot-' . uniqid('', true);
        $this->path      = $this->directory . '/users.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * Проверим, что при autoCreate=false отсутствующий snapshot не создаётся, а тест падает.
     *
     * @see JsonSnapshotAssert::assertMatchesSnapshot()
     */
    #[Test]
    public function missingSnapshotIsNotWrittenWithoutAutoCreate(): void
    {
        try {
            new JsonSnapshotAssert()->assertMatchesSnapshot(['id' => 1], 'users', $this->config()->withAutoCreate(false));
            self::fail('Missing snapshot must fail the test.');
        } catch (AssertionFailedError $failure) {
            self::assertSame("Snapshot 'users' not found.", $failure->getMessage());
        }

        self::assertFileDoesNotExist($this->path);
    }

    /**
     * Проверим, что при autoCreate=true отсутствующий snapshot создаётся, а тест помечается пропущенным.
     *
     * @see JsonSnapshotAssert::assertMatchesSnapshot()
     */
    #[Test]
    public function missingSnapshotIsCreatedWithAutoCreate(): void
    {
        try {
            new JsonSnapshotAssert()->assertMatchesSnapshot(['id' => 1], 'users', $this->config());
            self::fail('New snapshot must skip the test.');
        } catch (SkippedWithMessageException $skipped) {
            self::assertStringContainsString('A new snapshot was created.', $skipped->getMessage());
        }

        self::assertFileExists($this->path);
    }

    /**
     * Проверим, что без autoUpdateOnMismatch файл не меняется и сообщение не говорит об обновлении.
     *
     * @see JsonSnapshotAssert::assertMatchesSnapshot()
     */
    #[Test]
    public function mismatchWithoutAutoUpdateKeepsSnapshot(): void
    {
        $this->writeSnapshot("{\n    \"id\": 1\n}\n");

        try {
            new JsonSnapshotAssert()->assertMatchesSnapshot(['id' => 2], 'users', $this->config()->withAutoUpdateOnMismatch(false));
            self::fail('Mismatch must fail the test.');
        } catch (ExpectationFailedException $failure) {
            self::assertSame("Snapshot 'users' does not match.", $failure->getMessage());
        }

        self::assertSame("{\n    \"id\": 1\n}\n", file_get_contents($this->path));
    }

    /**
     * Проверим, что с autoUpdateOnMismatch файл перезаписывается и сообщение сообщает об обновлении.
     *
     * @see JsonSnapshotAssert::assertMatchesSnapshot()
     */
    #[Test]
    public function mismatchWithAutoUpdateRewritesSnapshot(): void
    {
        $this->writeSnapshot("{\n    \"id\": 1\n}\n");

        try {
            new JsonSnapshotAssert()->assertMatchesSnapshot(['id' => 2], 'users', $this->config());
            self::fail('Mismatch must fail the test.');
        } catch (ExpectationFailedException $failure) {
            self::assertSame("Snapshot 'users' does not match. Snapshot was updated.", $failure->getMessage());
        }

        self::assertSame("{\n    \"id\": 2\n}\n", file_get_contents($this->path));
    }

    private function config(): SnapshotConfig
    {
        return new SnapshotConfig($this->directory, 'Tests', classPrefixesToStrip: ['Tests']);
    }

    private function writeSnapshot(string $content): void
    {
        mkdir($this->directory, 0775, true);
        file_put_contents($this->path, $content);
    }
}
