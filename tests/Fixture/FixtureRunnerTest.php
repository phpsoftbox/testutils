<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Fixture;

use PhpSoftBox\TestUtils\Fixture\DependentFixtureInterface;
use PhpSoftBox\TestUtils\Fixture\FixtureContext;
use PhpSoftBox\TestUtils\Fixture\FixtureInterface;
use PhpSoftBox\TestUtils\Fixture\FixtureRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixtureRunnerTest extends TestCase
{
    /**
     * Проверяет, что FixtureRunner выполняет переданные фикстуры и сохраняет результаты в ReferenceStore.
     */
    #[Test]
    public function loadExecutesFixturesAndStoresReferences(): void
    {
        $runner = new FixtureRunner();

        $context = $runner->createContext();

        $refs = $runner->load(
            $context,
            new class () implements FixtureInterface {
                public function load(FixtureContext $context): void
                {
                    $context->refs()->set('alpha', 1);
                }
            },
            new class () implements FixtureInterface {
                public function load(FixtureContext $context): void
                {
                    $context->refs()->set('beta', 2);
                }
            },
        );

        self::assertSame(1, $refs->get('alpha'));
        self::assertSame(2, $refs->get('beta'));
    }



    /**
     * Проверяет, что loadOnce не выполняет фикстуру повторно при повторном вызове с тем же ключом.
     */
    #[Test]
    public function loadOnceSkipsSecondExecutionWithSameKey(): void
    {
        $runner = new FixtureRunner();

        $context = $runner->createContext();

        $counter = new class () {
            public int $value = 0;
        };

        $fixture = new class ($counter) implements FixtureInterface {
            public function __construct(
                private object $counter,
            ) {
            }

            public function load(FixtureContext $context): void
            {
                $this->counter->value++;
                $context->refs()->set('runs', $this->counter->value);
            }
        };

        $runner->loadOnce('auth-seed', $context, $fixture);
        $runner->loadOnce('auth-seed', $context, $fixture);

        self::assertSame(1, $counter->value);
        self::assertSame(1, $context->refs()->get('runs'));
    }



    /**
     * Проверяет, что loadOnce без явного ключа использует имя класса фикстуры как авто-ключ.
     */
    #[Test]
    public function loadOnceWithoutKeyUsesFixtureClassAsAutoKey(): void
    {
        $runner = new FixtureRunner();

        $context = $runner->createContext();

        $counter = new class () {
            public int $value = 0;
        };

        $fixture = new class ($counter) implements FixtureInterface {
            public function __construct(
                private object $counter,
            ) {
            }

            public function load(FixtureContext $context): void
            {
                $this->counter->value++;
            }
        };

        $runner->loadOnce($fixture, $context);
        $runner->loadOnce($fixture, $context);

        self::assertSame(1, $counter->value);
    }



    /**
     * Проверяет, что зависимости DependentFixture выполняются раньше основной фикстуры и не дублируются.
     */
    #[Test]
    public function loadResolvesDependenciesBeforeFixture(): void
    {
        $runner = new FixtureRunner();

        $context = $runner->createContext();

        $record = static function (FixtureContext $fixtureContext, string $value): void {
            $sequence = $fixtureContext->refs()->has('sequence')
                ? (array) $fixtureContext->refs()->get('sequence')
                : [];
            $sequence[] = $value;
            $fixtureContext->refs()->set('sequence', $sequence);
        };

        $sharedDependency = new class ($record) implements FixtureInterface {
            public function __construct(
                private $record,
            ) {
            }

            public function load(FixtureContext $context): void
            {
                ($this->record)($context, 'shared');
            }
        };

        $left = new class ($sharedDependency, $record) implements DependentFixtureInterface {
            public function __construct(
                private FixtureInterface $dependency,
                private $record,
            ) {
            }

            public function dependencies(): array
            {
                return [$this->dependency];
            }

            public function load(FixtureContext $context): void
            {
                ($this->record)($context, 'left');
            }
        };

        $right = new class ($sharedDependency, $record) implements DependentFixtureInterface {
            public function __construct(
                private FixtureInterface $dependency,
                private $record,
            ) {
            }

            public function dependencies(): array
            {
                return [$this->dependency];
            }

            public function load(FixtureContext $context): void
            {
                ($this->record)($context, 'right');
            }
        };

        $root = new class ($left, $right, $record) implements DependentFixtureInterface {
            public function __construct(
                private FixtureInterface $left,
                private FixtureInterface $right,
                private $record,
            ) {
            }

            public function dependencies(): array
            {
                return [$this->left, $this->right];
            }

            public function load(FixtureContext $context): void
            {
                ($this->record)($context, 'root');
            }
        };

        $runner->load($context, $root);

        self::assertSame(['shared', 'left', 'right', 'root'], $context->refs()->get('sequence'));
    }
}
