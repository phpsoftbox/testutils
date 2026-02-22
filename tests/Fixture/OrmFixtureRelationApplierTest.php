<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Fixture;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\TestUtils\Fixture\Definition\FixtureDefinition;
use PhpSoftBox\TestUtils\Fixture\OrmFixtureRelationApplier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

final class OrmFixtureRelationApplierTest extends TestCase
{
    /**
     * Проверяет, что applier выбрасывает исключение при несовпадении класса owner-entity с классом в definition.
     */
    #[Test]
    public function applyThrowsWhenOwnerClassDoesNotMatchDefinition(): void
    {
        $applier = new OrmFixtureRelationApplier(
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fixture definition entity mismatch');

        $applier->apply(new OtherEntity(1), OwnerDefinition::make());
    }
}

final class OwnerDefinition extends FixtureDefinition
{
    public static function entityClass(): string
    {
        return OwnerEntity::class;
    }
}

#[Entity(table: 'owner_fixture_applier')]
final class OwnerEntity implements EntityInterface
{
    public function __construct(
        public ?int $id = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}

#[Entity(table: 'other_fixture_applier')]
final class OtherEntity implements EntityInterface
{
    public function __construct(
        public ?int $id = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
