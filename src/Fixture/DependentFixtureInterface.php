<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture;

interface DependentFixtureInterface extends FixtureInterface
{
    /**
     * @return list<FixtureInterface>
     */
    public function dependencies(): array;
}
