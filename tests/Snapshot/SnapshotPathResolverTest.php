<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Snapshot;

use PhpSoftBox\TestUtils\Snapshot\SnapshotConfig;
use PhpSoftBox\TestUtils\Snapshot\SnapshotPathResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotPathResolver::class)]
#[CoversMethod(SnapshotPathResolver::class, 'resolve')]
final class SnapshotPathResolverTest extends TestCase
{
    /**
     * Проверяет, что SnapshotPathResolver формирует путь снапшота с учетом имени тестового класса.
     */
    #[Test]
    public function resolveBuildsPathWithTestClass(): void
    {
        $config = SnapshotConfig::forTestClass('/tmp/snapshots', self::class);

        $resolver = new SnapshotPathResolver();

        $path = $resolver->resolve($config, 'login-success');

        $this->assertSame(
            '/tmp/snapshots/PhpSoftBox/TestUtils/Tests/Snapshot/SnapshotPathResolverTest/login-success.json',
            $path,
        );
    }
}
