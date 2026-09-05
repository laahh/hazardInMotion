<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\ControlRoom\ReadOnlyModelException;

/**
 * Pasang di model yang merepresentasikan data sumber read-only (HSE Database).
 * Melempar exception pada percobaan tulis apapun, alih-alih diam-diam gagal
 * atau (lebih buruk) berhasil menulis ke sumber yang seharusnya read-only.
 *
 * plan-OCR.md rule #4: "Jangan pernah menulis ke HSE Database. Read-only di
 * level kredensial, bukan hanya disiplin kode." — trait ini adalah lapisan
 * disiplin kode-nya; kredensial DB read-only tetap wajib di level lain.
 */
trait ReadOnlyModel
{
    public function save(array $options = [])
    {
        throw ReadOnlyModelException::forModel(static::class, 'save');
    }

    public function delete()
    {
        throw ReadOnlyModelException::forModel(static::class, 'delete');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw ReadOnlyModelException::forModel(static::class, 'update');
    }

    public static function create(array $attributes = [])
    {
        throw ReadOnlyModelException::forModel(static::class, 'create');
    }

    public static function insert($values)
    {
        throw ReadOnlyModelException::forModel(static::class, 'insert');
    }
}
