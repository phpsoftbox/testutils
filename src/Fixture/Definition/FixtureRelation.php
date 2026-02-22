<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture\Definition;

use PhpSoftBox\Orm\Contracts\EntityInterface;

final readonly class FixtureRelation
{
    /**
     * @param non-empty-string $name
     * @param array<string, mixed> $pivotData
     */
    public function __construct(
        public string $name,
        public EntityInterface $entity,
        public array $pivotData = [],
    ) {
    }
}
