<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

/**
 * Hasil baca Excel jadwal mingguan sebelum masuk ScheduleBulkAssignService.
 */
final class ControlRoomScheduleExcelParseResult
{
    /**
     * @param  list<array{date: string, shift_code: string, personnel_source_key: string}>  $assignments
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
