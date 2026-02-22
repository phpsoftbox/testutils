<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture;

use InvalidArgumentException;
use Throwable;

use function array_map;
use function count;
use function implode;
use function is_string;
use function md5;
use function serialize;
use function spl_object_id;
use function trim;

final class FixtureRunner
{
    /**
     * @param array<string, mixed> $services
     */
    public function createContext(
        array $services = [],
        ?ReferenceStore $references = null,
    ): FixtureContext {
        return new FixtureContext(
            references: $references,
            services: $services,
        );
    }

    public function load(FixtureContext $context, FixtureInterface ...$fixtures): ReferenceStore
    {
        foreach ($this->resolveFixturesWithDependencies($fixtures) as $fixture) {
            $fixture->load($context);
        }

        return $context->refs();
    }

    public function loadOnce(
        string|FixtureInterface $keyOrFixture,
        FixtureContext $context,
        FixtureInterface ...$fixtures,
    ): ReferenceStore {
        [$key, $fixtures] = $this->resolveOnceKeyAndFixtures($keyOrFixture, $fixtures);

        if ($context->wasLoadedOnce($key)) {
            return $context->refs();
        }

        $result = $this->load($context, ...$fixtures);
        $context->markLoadedOnce($key);

        return $result;
    }

    /**
     * @param list<FixtureInterface> $fixtures
     * @return array{0: non-empty-string, 1: list<FixtureInterface>}
     */
    private function resolveOnceKeyAndFixtures(
        string|FixtureInterface $keyOrFixture,
        array $fixtures,
    ): array {
        if (is_string($keyOrFixture)) {
            $key = trim($keyOrFixture);
            if ($key === '') {
                throw new InvalidArgumentException('FixtureRunner::loadOnce key must not be empty.');
            }

            return [$key, $fixtures];
        }

        $resolvedFixtures = [$keyOrFixture, ...$fixtures];
        $classes          = array_map(
            static fn (FixtureInterface $fixture): string => $fixture::class,
            $resolvedFixtures,
        );
        $key = count($classes) === 1
            ? $classes[0]
            : implode('|', $classes);

        return [$key, $resolvedFixtures];
    }

    /**
     * @param list<FixtureInterface> $fixtures
     * @return list<FixtureInterface>
     */
    private function resolveFixturesWithDependencies(array $fixtures): array
    {
        $resolved = [];
        $visited  = [];
        $visiting = [];

        $visit = function (FixtureInterface $fixture) use (&$visit, &$resolved, &$visited, &$visiting): void {
            $fixtureId = $this->fixtureId($fixture);

            if (isset($visited[$fixtureId])) {
                return;
            }

            if (isset($visiting[$fixtureId])) {
                throw new InvalidArgumentException('Circular fixture dependency detected for ' . $fixture::class);
            }

            $visiting[$fixtureId] = true;

            if ($fixture instanceof DependentFixtureInterface) {
                foreach ($fixture->dependencies() as $dependency) {
                    if (!$dependency instanceof FixtureInterface) {
                        throw new InvalidArgumentException(
                            'Fixture dependency must implement ' . FixtureInterface::class . '.',
                        );
                    }

                    $visit($dependency);
                }
            }

            unset($visiting[$fixtureId]);
            $visited[$fixtureId] = true;
            $resolved[]          = $fixture;
        };

        foreach ($fixtures as $fixture) {
            $visit($fixture);
        }

        return $resolved;
    }

    private function fixtureId(FixtureInterface $fixture): string
    {
        try {
            return $fixture::class . ':' . md5(serialize($fixture));
        } catch (Throwable) {
            return $fixture::class . ':obj:' . spl_object_id($fixture);
        }
    }
}
