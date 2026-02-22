<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use PDO;
use PDOStatement;

use function array_values;

final readonly class PdoResetConnection implements ResetConnectionInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $rows = $this->statement($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return array_values($rows);
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->statement($sql, $params)->closeCursor();
    }

    /**
     * @param list<scalar|null> $params
     */
    private function statement(string $sql, array $params): PDOStatement
    {
        if ($params === []) {
            return $this->pdo->query($sql);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
