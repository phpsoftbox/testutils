<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use function strtolower;
use function trim;

enum DatabaseReloaderModesEnum: string
{
    case DUMP        = 'dump';
    case TRANSACTION = 'transaction';

    public static function fromString(string $mode): self
    {
        $normalized = strtolower(trim($mode));
        $resolved   = self::tryFrom($normalized);
        if ($resolved instanceof self) {
            return $resolved;
        }

        throw new DatabaseReloaderException(
            'Unsupported database reload mode "' . $mode . '". Allowed modes: dump, transaction.',
        );
    }
}
