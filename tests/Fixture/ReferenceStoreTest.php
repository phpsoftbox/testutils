<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Fixture;

use PhpSoftBox\TestUtils\Fixture\ReferenceStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReferenceStoreTest extends TestCase
{
    /**
     * Проверяет, что ReferenceStore сохраняет и возвращает значения по ключу.
     */
    #[Test]
    public function storesAndReturnsReferencesByKey(): void
    {
        $store = new ReferenceStore();

        $store->set('user', ['id' => 10]);

        self::assertTrue($store->has('user'));
        self::assertSame(['id' => 10], $store->get('user'));
    }



    /**
     * Проверяет, что запрос отсутствующей ссылки в ReferenceStore приводит к исключению.
     */
    #[Test]
    public function throwsWhenReferenceDoesNotExist(): void
    {
        $store = new ReferenceStore();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fixture reference not found: missing');

        $store->get('missing');
    }
}
