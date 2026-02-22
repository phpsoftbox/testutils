<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Metadata\RelationMetadata;
use PhpSoftBox\TestUtils\Fixture\Definition\FixtureDefinition;
use PhpSoftBox\TestUtils\Fixture\Definition\FixtureRelation;
use Ramsey\Uuid\UuidInterface;

use function in_array;
use function is_a;
use function property_exists;
use function trim;

final readonly class OrmFixtureRelationApplier
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function apply(EntityInterface $owner, FixtureDefinition $definition): void
    {
        $expectedClass = $definition::entityClass();
        if (!is_a($owner, $expectedClass)) {
            throw new InvalidArgumentException(
                'Fixture definition entity mismatch: expected owner ' . $expectedClass . ', got ' . $owner::class . '.',
            );
        }

        $touchOwner = false;
        foreach ($definition->forRelations() as $relation) {
            $touchOwner = $this->applyForRelation($owner, $relation) || $touchOwner;
        }

        if ($touchOwner) {
            $this->em->persist($owner);
        }

        $needsFlush = $touchOwner;
        foreach ($definition->hasRelations() as $relation) {
            $needsFlush = $this->applyHasRelation($owner, $relation) || $needsFlush;
        }

        if ($needsFlush) {
            $this->em->flush();
        }
    }

    private function applyForRelation(EntityInterface $owner, FixtureRelation $relation): bool
    {
        $metadata = $this->relationMetadata($owner::class, $relation->name);
        if ($metadata->type !== 'many_to_one') {
            throw new InvalidArgumentException(
                'Relation "' . $relation->name . '" must be many_to_one for for().',
            );
        }

        $joinColumn = trim((string) ($metadata->joinColumn ?? ''));
        if ($joinColumn === '') {
            throw new InvalidArgumentException(
                'Relation "' . $relation->name . '" has empty joinColumn in metadata.',
            );
        }

        if (!property_exists($owner, $joinColumn)) {
            throw new InvalidArgumentException(
                'Property "' . $joinColumn . '" was not found on owner entity ' . $owner::class . '.',
            );
        }

        $owner->{$joinColumn} = $this->requireEntityId($relation->entity, $relation->name);
        if (property_exists($owner, $relation->name)) {
            $owner->{$relation->name} = $relation->entity;
        }

        return true;
    }

    private function applyHasRelation(EntityInterface $owner, FixtureRelation $relation): bool
    {
        $metadata = $this->relationMetadata($owner::class, $relation->name);

        if ($metadata->type === 'belongs_to_many') {
            $this->em
                ->pivot($owner, $relation->name)
                ->attach(
                    $this->requireEntityId($relation->entity, $relation->name),
                    $relation->pivotData,
                );

            return false;
        }

        if (!in_array($metadata->type, ['has_many', 'has_one'], true)) {
            throw new InvalidArgumentException(
                'Relation "' . $relation->name . '" must be has_many/has_one/belongs_to_many for has().',
            );
        }

        $foreignKey = trim((string) ($metadata->foreignKey ?? ''));
        if ($foreignKey === '') {
            throw new InvalidArgumentException(
                'Relation "' . $relation->name . '" has empty foreignKey in metadata.',
            );
        }

        $localKey = trim($metadata->localKey);
        if ($localKey === '') {
            $localKey = 'id';
        }

        if (!property_exists($owner, $localKey)) {
            throw new InvalidArgumentException(
                'Property "' . $localKey . '" was not found on owner entity ' . $owner::class . '.',
            );
        }

        if (!property_exists($relation->entity, $foreignKey)) {
            throw new InvalidArgumentException(
                'Property "' . $foreignKey . '" was not found on related entity ' . $relation->entity::class . '.',
            );
        }

        $ownerKey = $owner->{$localKey};
        if ($ownerKey === null) {
            throw new InvalidArgumentException(
                'Owner local key "' . $localKey . '" is null for relation "' . $relation->name . '".',
            );
        }

        $relation->entity->{$foreignKey} = $ownerKey;
        $this->em->persist($relation->entity);

        return true;
    }

    /**
     * @param class-string $ownerClass
     */
    private function relationMetadata(string $ownerClass, string $relation): RelationMetadata
    {
        $metadata         = $this->em->metadataProvider()->for($ownerClass);
        $relationMetadata = $metadata->relations[$relation] ?? null;
        if ($relationMetadata === null) {
            throw new InvalidArgumentException(
                'Unknown relation "' . $relation . '" for entity ' . $ownerClass . '.',
            );
        }

        return $relationMetadata;
    }

    private function requireEntityId(EntityInterface $entity, string $relation): int|string|UuidInterface
    {
        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException(
                'Relation "' . $relation . '" expects persisted entity with non-null id.',
            );
        }

        return $id;
    }
}
