<?php

declare(strict_types=1);

namespace App\Models\PraOperasi;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * Hasil evaluasi harian per operator (Fase 3 — Pasca Operasi). Data lokal
 * aplikasi (bukan dari hse_automation) — "dibekukan" per tanggal oleh
 * app/Console/Commands/PraOperasi/EvaluateDayCommand.php.
 *
 * @property string $kode_sid
 * @property string|null $nama
 * @property string|null $perusahaan
 * @property \Illuminate\Support\Carbon $tanggal
 * @property string|null $shift
 * @property int|null $hari_ke
 * @property int|null $fatigue_score
 * @property string|null $fatigue_tier
 * @property string $pvt_status
 * @property int $alert_nyata_count
 * @property int $alert_palsu_count
 * @property int $alert_belum_count
 * @property int|null $durasi_kerja_menit
 * @property float|null $baseline_zscore
 * @property string $kategori_evaluasi
 * @property array|null $alasan
 */
final class PraOperasiEvaluasiHarian extends Model
{
    protected $table = 'pra_operasi_evaluasi_harian';

    protected $fillable = [
        'kode_sid', 'nama', 'perusahaan', 'tanggal', 'shift', 'hari_ke',
        'fatigue_score', 'fatigue_tier', 'pvt_status',
        'alert_nyata_count', 'alert_palsu_count', 'alert_belum_count',
        'durasi_kerja_menit', 'baseline_zscore', 'kategori_evaluasi', 'alasan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'alasan' => AsArrayObject::class,
        'baseline_zscore' => 'float',
    ];

    public const KATEGORI_BAIK = 'baik';

    public const KATEGORI_PERLU_PEMBINAAN = 'perlu_pembinaan';

    public const KATEGORI_KRITIS = 'kritis';
}
