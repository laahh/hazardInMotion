<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

/**
 * Hasil baca Excel Validasi TBC sebelum upsert.
 */
final class ControlRoomTbcExcelParseResult
{
    /**
     * @param  list<array<string, ?string>>  $rows
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly array $warnings = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
