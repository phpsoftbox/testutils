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
     * Проверяет формирование пути снимка с учетом базового пути и имени теста.
     *
     * @see SnapshotPathResolver::resolve()
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
