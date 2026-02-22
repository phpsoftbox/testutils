<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Dsn\Dsn;
use PhpSoftBox\Database\Dsn\DsnParser;

use function sprintf;

final readonly class DatabaseReloaderConnection
{
    public Dsn $main;
    public Dsn $test;

    public function __construct(
        public string $name,
        public string $mainDsn,
        public string $testDsn,
    ) {
        $parser = new DsnParser();

        $this->main = $parser->parse($mainDsn);
        $this->test = $parser->parse($testDsn);
    }

    public function driver(): string
    {
        return $this->main->driver;
    }

    public function assertDifferentDatabases(): void
    {
        if ($this->main->driver !== $this->test->driver) {
            throw new DatabaseReloaderException(sprintf(
                'Driver mismatch for connection "%s" (%s vs %s).',
                $this->name,
                $this->main->driver,
                $this->test->driver,
            ));
        }

        if ($this->main->driver === 'sqlite') {
            if ($this->main->path === $this->test->path) {
                throw new DatabaseReloaderException(sprintf(
                    'SQLite test path must differ from main path for connection "%s".',
                    $this->name,
                ));
            }

            return;
        }

        if ($this->main->database === $this->test->database) {
            throw new DatabaseReloaderException(sprintf(
                'Test database must differ from main database for connection "%s".',
                $this->name,
            ));
        }
    }
}
