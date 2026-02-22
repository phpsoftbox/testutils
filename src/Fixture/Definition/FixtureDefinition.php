<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture\Definition;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsTo;
use PhpSoftBox\Orm\Metadata\Attributes\BelongsToMany;
use PhpSoftBox\Orm\Metadata\Attributes\HasMany;
use PhpSoftBox\Orm\Metadata\Attributes\HasOne;
use PhpSoftBox\Orm\Metadata\Attributes\ManyToOne;
use ReflectionClass;
use ReflectionProperty;

use function array_key_exists;
use function array_replace;
use function array_values;
use function get_debug_type;
use function in_array;
use function is_a;
use function sprintf;
use function trim;

abstract class FixtureDefinition
{
    /**
     * @var array<class-string, array<string, array{type: string, target: class-string}>>
     */
    private static array $relationMapCache = [];

    /**
     * @param array<string, mixed> $data
     * @param array<non-empty-string, FixtureRelation> $forRelations
     * @param array<non-empty-string, list<FixtureRelation>> $hasRelations
     */
    final protected function __construct(
        private array $data = [],
        private array $forRelations = [],
        private array $hasRelations = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    final public static function make(array $data = []): static
    {
        return new static($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    final public function with(array $data): static
    {
        $clone       = clone $this;
        $clone->data = array_replace($clone->data, $data);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    final public function data(): array
    {
        return $this->data;
    }

    final public function for(string $relation, EntityInterface $entity): static
    {
        $relation = $this->normalizeRelation($relation);
        $metadata = $this->relationMetadata($relation);

        if ($metadata['type'] !== 'many_to_one') {
            throw new InvalidArgumentException(
                sprintf(
                    'Relation "%s" in %s does not support for(); use has() for type "%s".',
                    $relation,
                    static::entityClass(),
                    $metadata['type'],
                ),
            );
        }

        $this->assertTargetType($relation, $entity, $metadata['target']);

        $clone                          = clone $this;
        $clone->forRelations[$relation] = new FixtureRelation($relation, $entity);

        return $clone;
    }

    /**
     * @param array<string, mixed> $pivotData
     */
    final public function has(string $relation, EntityInterface $entity, array $pivotData = []): static
    {
        $relation = $this->normalizeRelation($relation);
        $metadata = $this->relationMetadata($relation);

        if ($metadata['type'] !== 'belongs_to_many' && $pivotData !== []) {
            throw new InvalidArgumentException(
                sprintf('Pivot data is supported only for belongs_to_many relation "%s".', $relation),
            );
        }

        if (!in_array($metadata['type'], ['belongs_to_many', 'has_many', 'has_one'], true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Relation "%s" in %s does not support has(); use for() for type "%s".',
                    $relation,
                    static::entityClass(),
                    $metadata['type'],
                ),
            );
        }

        $this->assertTargetType($relation, $entity, $metadata['target']);

        $clone = clone $this;
        $clone->hasRelations[$relation] ??= [];
        $clone->hasRelations[$relation][] = new FixtureRelation(
            name: $relation,
            entity: $entity,
            pivotData: $pivotData,
        );

        return $clone;
    }

    /**
     * @return list<FixtureRelation>
     */
    final public function forRelations(): array
    {
        return array_values($this->forRelations);
    }

    /**
     * @return list<FixtureRelation>
     */
    final public function hasRelations(): array
    {
        $relations = [];
        foreach ($this->hasRelations as $group) {
            foreach ($group as $relation) {
                $relations[] = $relation;
            }
        }

        return $relations;
    }

    /**
     * @return class-string
     */
    abstract public static function entityClass(): string;

    /**
     * @return array{type: string, target: class-string}
     */
    private function relationMetadata(string $relation): array
    {
        $map = self::relationMap(static::entityClass());
        if (!array_key_exists($relation, $map)) {
            throw new InvalidArgumentException(
                sprintf('Unknown relation "%s" for fixture entity %s.', $relation, static::entityClass()),
            );
        }

        return $map[$relation];
    }

    private function normalizeRelation(string $relation): string
    {
        $normalized = trim($relation);
        if ($normalized === '') {
            throw new InvalidArgumentException('Relation name must not be empty.');
        }

        return $normalized;
    }

    /**
     * @param class-string $targetClass
     */
    private function assertTargetType(string $relation, EntityInterface $entity, string $targetClass): void
    {
        if (!is_a($entity, $targetClass)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid entity for relation "%s": expected %s, got %s.',
                    $relation,
                    $targetClass,
                    get_debug_type($entity),
                ),
            );
        }
    }

    /**
     * @param class-string $entityClass
     * @return array<string, array{type: string, target: class-string}>
     */
    private static function relationMap(string $entityClass): array
    {
        if (array_key_exists($entityClass, self::$relationMapCache)) {
            return self::$relationMapCache[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);
        $map        = [];

        foreach ($reflection->getProperties() as $property) {
            $relation = self::propertyRelation($property);
            if ($relation === null) {
                continue;
            }

            $map[$property->getName()] = $relation;
        }

        self::$relationMapCache[$entityClass] = $map;

        return $map;
    }

    /**
     * @return array{type: string, target: class-string}|null
     */
    private static function propertyRelation(ReflectionProperty $property): ?array
    {
        $belongsTo = $property->getAttributes(BelongsTo::class);
        if ($belongsTo !== []) {
            /** @var BelongsTo $attribute */
            $attribute = $belongsTo[0]->newInstance();

            return ['type' => 'many_to_one', 'target' => $attribute->targetEntity];
        }

        $manyToOne = $property->getAttributes(ManyToOne::class);
        if ($manyToOne !== []) {
            /** @var ManyToOne $attribute */
            $attribute = $manyToOne[0]->newInstance();

            return ['type' => 'many_to_one', 'target' => $attribute->targetEntity];
        }

        $belongsToMany = $property->getAttributes(BelongsToMany::class);
        if ($belongsToMany !== []) {
            /** @var BelongsToMany $attribute */
            $attribute = $belongsToMany[0]->newInstance();

            return ['type' => 'belongs_to_many', 'target' => $attribute->targetEntity];
        }

        $hasMany = $property->getAttributes(HasMany::class);
        if ($hasMany !== []) {
            /** @var HasMany $attribute */
            $attribute = $hasMany[0]->newInstance();

            return ['type' => 'has_many', 'target' => $attribute->targetEntity];
        }

        $hasOne = $property->getAttributes(HasOne::class);
        if ($hasOne !== []) {
            /** @var HasOne $attribute */
            $attribute = $hasOne[0]->newInstance();

            return ['type' => 'has_one', 'target' => $attribute->targetEntity];
        }

        return null;
    }
}
