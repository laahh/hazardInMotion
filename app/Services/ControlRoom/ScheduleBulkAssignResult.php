<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

final class ScheduleBulkAssignResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly array $errors,
        public readonly array $warnings,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
