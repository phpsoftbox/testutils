<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Snapshot;

use PhpSoftBox\TestUtils\Snapshot\ArrayPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayPath::class)]
#[CoversMethod(ArrayPath::class, 'forget')]
final class ArrayPathTest extends TestCase
{
    /**
     * Проверяет удаление вложенного ключа по dot-пути.
     *
     * @see ArrayPath::forget()
     */
    #[Test]
    public function forgetRemovesNestedKey(): void
    {
        $data = [
            'meta' => [
                'timestamp' => 123,
                'version'   => 'v1',
            ],
            'payload' => [
                'name' => 'Test',
            ],
        ];

        $result = ArrayPath::forget($data, ['meta.timestamp']);

        $this->assertArrayNotHasKey('timestamp', $result['meta']);
        $this->assertSame('v1', $result['meta']['version']);
        $this->assertSame('Test', $result['payload']['name']);
    }
}
