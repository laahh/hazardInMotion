<?php

declare(strict_types=1);

namespace App\Exceptions\ControlRoom;

use RuntimeException;

/**
 * Dilempar saat kode mencoba menulis ke model yang memakai trait ReadOnlyModel
 * (App\Models\Concerns\ReadOnlyModel) — model-model ini merepresentasikan data
 * milik HSE Database yang read-only secara aturan (plan-OCR.md rule #4:
 * "Jangan pernah menulis ke HSE Database").
 */
final class ReadOnlyModelException extends RuntimeException
{
    public static function forModel(string $modelClass, string $operation): self
    {
        return new self("Model read-only [{$modelClass}] tidak boleh di-{$operation}(). Data ini milik HSE Database, aplikasi ini hanya boleh baca.");
    }
}
