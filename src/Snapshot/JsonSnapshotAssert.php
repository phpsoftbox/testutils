<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Snapshot;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Comparator\ComparisonFailure;

use function json_encode;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

final readonly class JsonSnapshotAssert
{
    public function __construct(
        private JsonSnapshotStore $store = new JsonSnapshotStore(),
        private SnapshotPathResolver $pathResolver = new SnapshotPathResolver(),
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function assertMatchesSnapshot(array $payload, string $snapshotName, SnapshotConfig $config): void
    {
        $filteredPayload = ArrayPath::forget($payload, $config->excludedKeys());
        $actual          = json_encode($filteredPayload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $path = $this->pathResolver->resolve($config, $snapshotName);

        if (!$this->store->exists($path)) {
            $this->store->write($path, $actual . "\n");

            if ($config->autoCreate()) {
                Assert::markTestSkipped("Snapshot '{$snapshotName}' not found. A new snapshot was created.");
            }

            Assert::fail("Snapshot '{$snapshotName}' not found.");
        }

        $expected = $this->store->read($path);

        if (trim($expected) !== trim($actual)) {
            if ($config->autoUpdateOnMismatch()) {
                $this->store->write($path, $actual . "\n");
            }

            $message           = "Snapshot '{$snapshotName}' does not match. Snapshot was updated.";
            $comparisonFailure = new ComparisonFailure(
                $expected,
                $actual,
                $expected,
                $actual,
                $message,
            );

            throw new ExpectationFailedException($message, $comparisonFailure);
        }

        Assert::assertTrue(true, 'Snapshot matches expected payload.');
    }
}
