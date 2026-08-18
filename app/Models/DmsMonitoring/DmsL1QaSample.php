<?php

declare(strict_types=1);

namespace App\Models\DmsMonitoring;

use Illuminate\Database\Eloquent\Model;

/**
 * Sampel audit ulang QA false negative L1 — lihat migration
 * 2026_08_18_100000_create_dms_l1_qa_samples_table. Data lokal aplikasi.
 *
 * @property string $id_alert
 * @property string|null $kode_sid
 * @property string|null $nama_pelanggaran
 * @property string|null $unit
 * @property string|null $site
 * @property \Illuminate\Support\Carbon|null $waktu_deteksi
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property float $margin_of_error
 * @property string|null $verdict benar_dismiss|false_negative|tidak_jelas
 * @property string|null $catatan
 * @property int|null $audited_by
 * @property \Illuminate\Support\Carbon|null $audited_at
 */
final class DmsL1QaSample extends Model
{
    public const VERDICT_BENAR_DISMISS = 'benar_dismiss';

    public const VERDICT_FALSE_NEGATIVE = 'false_negative';

    public const VERDICT_TIDAK_JELAS = 'tidak_jelas';

    protected $table = 'dms_l1_qa_samples';

    protected $fillable = [
        'id_alert', 'kode_sid', 'nama_pelanggaran', 'unit', 'site', 'waktu_deteksi',
        'period_start', 'period_end', 'margin_of_error',
        'verdict', 'catatan', 'audited_by', 'audited_at',
    ];

    protected $casts = [
        'waktu_deteksi' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'margin_of_error' => 'float',
        'audited_at' => 'datetime',
    ];
}
