<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use Closure;
use PhpSoftBox\TestUtils\Database\Reset\ResetConnectionInterface;
use Throwable;

use function preg_replace;
use function str_contains;
use function trim;

final class FakeResetConnection implements ResetConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $queries = [];

    /**
     * @var list<array{sql: string, params: list<scalar|null>}>
     */
    public array $executed = [];

    /**
     * @var list<array{needle: string, response: list<array<string, mixed>>|Closure|Throwable}>
     */
    private array $responses = [];

    /**
     * Ответ на запрос, SQL которого содержит $needle; более поздний ответ перекрывает ранний.
     *
     * @param list<array<string, mixed>>|Closure|Throwable $response
     */
    public function respond(string $needle, array|Closure|Throwable $response): self
    {
        $this->responses = [['needle' => $needle, 'response' => $response], ...$this->responses];

        return $this;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $sql             = $this->normalize($sql);
        $this->queries[] = $sql;

        foreach ($this->responses as $response) {
            if (!str_contains($sql, $response['needle'])) {
                continue;
            }

            $value = $response['response'];
            if ($value instanceof Throwable) {
                throw $value;
            }

            return $value instanceof Closure ? $value($sql, $params) : $value;
        }

        return [];
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->executed[] = ['sql' => $this->normalize($sql), 'params' => $params];
    }

    /**
     * @return list<string>
     */
    public function executedSql(): array
    {
        $sql = [];
        foreach ($this->executed as $statement) {
            $sql[] = $statement['sql'];
        }

        return $sql;
    }

    private function normalize(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }
}
