<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture;

interface FixtureInterface
{
    public function load(FixtureContext $context): void;
}
