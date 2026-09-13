<?php

declare(strict_types=1);

namespace Crm;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly array $extra = [],
    ) {
        parent::__construct($message, $status);
    }
}
