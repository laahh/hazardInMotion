<?php

declare(strict_types=1);

namespace App\Models\PraOperasi;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan tindak lanjut supervisor (Fase 2 — Saat Operasi). Data lokal
 * aplikasi — lihat migration 2026_08_16_110000_create_pra_operasi_tindak_lanjut_table.
 *
 * @property string $kode_sid
 * @property \Illuminate\Support\Carbon $tanggal
 * @property string|null $status_saat_ditandai
 * @property string|null $catatan
 * @property int|null $user_id
 */
final class PraOperasiTindakLanjut extends Model
{
    protected $table = 'pra_operasi_tindak_lanjut';

    protected $fillable = ['kode_sid', 'tanggal', 'status_saat_ditandai', 'catatan', 'user_id'];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
