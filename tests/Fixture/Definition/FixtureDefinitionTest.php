<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Fixture\Definition;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;
use PhpSoftBox\TestUtils\Fixture\Definition\FixtureDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

final class FixtureDefinitionTest extends TestCase
{
    /**
     * Проверяет, что методы make/with/has в определении фикстуры работают иммутабельно и не изменяют исходный объект.
     */
    #[Test]
    public function makeAndRelationMethodsAreImmutable(): void
    {
        $base = OwnerFixtureDefinition::make(['name' => 'base']);
        $role = new RoleEntity(10);

        $next = $base->has('roles', $role, ['scope' => 'all']);

        self::assertSame(['name' => 'base'], $base->data());
        self::assertSame([], $base->hasRelations());
        self::assertCount(1, $next->hasRelations());
        self::assertSame('roles', $next->hasRelations()[0]->name);
        self::assertSame(['scope' => 'all'], $next->hasRelations()[0]->pivotData);
    }



    /**
     * Проверяет, что обращение к неизвестному relation в определении фикстуры вызывает исключение.
     */
    #[Test]
    public function unknownRelationThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown relation "unknown"');

        OwnerFixtureDefinition::make()->for('unknown', new ParentEntity(1));
    }



    /**
     * Проверяет, что метод has запрещает pivot-данные для связей, которые не являются many-to-many.
     */
    #[Test]
    public function hasRejectsPivotDataForNonPivotRelations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pivot data is supported only for belongs_to_many relation "parent".');

        OwnerFixtureDefinition::make()->has('parent', new ParentEntity(1), ['x' => 1]);
    }
}

final class OwnerFixtureDefinition extends FixtureDefinition
{
    public static function entityClass(): string
    {
        return OwnerEntity::class;
    }
}

#[Entity(table: 'owner_fixture')]
final class OwnerEntity implements EntityInterface
{
    #[ManyToOne(targetEntity: ParentEntity::class, joinColumn: 'parentId')]
    public ?ParentEntity $parent = null;

    #[BelongsToMany(
        targetEntity: RoleEntity::class,
        pivotTable: 'owner_roles',
        foreignPivotKey: 'owner_id',
        relatedPivotKey: 'role_id',
    )]
    public mixed $roles = null;

    public function __construct(
        public ?int $id = null,
        public ?int $parentId = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}

#[Entity(table: 'parent_fixture')]
final class ParentEntity implements EntityInterface
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

#[Entity(table: 'role_fixture')]
final class RoleEntity implements EntityInterface
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
