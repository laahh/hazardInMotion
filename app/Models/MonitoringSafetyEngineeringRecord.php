<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LenientBackedEnumCast;
use App\Enums\MonitoringSafetyEngineeringPelaksanaRekayasa;
use App\Enums\MonitoringSafetyEngineeringPhaseStatus;
use App\Enums\MonitoringSafetyEngineeringStatusCompliance;
use App\Enums\MonitoringSafetyEngineeringSumberRekayasa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonitoringSafetyEngineeringRecord extends Model
{
    use SoftDeletes;

    protected $table = 'monitoring_safety_engineering_records';

    protected $fillable = [
        'row_no',
        'site',
        'perusahaan',
        'aktivitas',
        'sumber_rekayasa',
        'pelaksana_rekayasa',
        'pengendalian_rekayasa',
        'tanggal_ideation',
        'kajian_teknis_due_date',
        'kajian_teknis_status',
        'kajian_teknis_status_changed_at',
        'kajian_teknis_status_compliance',
        'pengadaan_due_date',
        'pengadaan_status',
        'pengadaan_status_changed_at',
        'pengadaan_status_compliance',
        'uji_coba_due_date',
        'uji_coba_status',
        'uji_coba_status_changed_at',
        'uji_coba_status_compliance',
        'standardisasi_due_date',
        'standardisasi_status',
        'standardisasi_status_changed_at',
        'standardisasi_status_compliance',
        'replikasi_due_date',
        'replikasi_total_populasi',
        'replikasi_satuan',
        'replikasi_target_komitmen',
        'replikasi_diusulkan_pjo',
        'replikasi_ditinjau',
        'replikasi_disetujui',
        'replikasi_aktual',
        'deteksi_deviasi',
        'intervensi_deviasi',
        'prediksi_penurunan_tangga_risiko',
        'terkait_hazard',
        'terkait_insiden',
        'efektivitas_rekayasa',
        'brief_analysis_challenge',
        'next_to_do',
        'potensi_peningkatan_efektivitas',
        'pengendalian_peningkatan_efektivitas',
        'total_risiko_signifikan',
        'link_list_risiko_signifikan',
        'jumlah_risiko_signifikan_tercover_rekayasa',
        'link_risiko_signifikan_tercover_rekayasa',
        'period_year',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'row_no' => 'integer',
            'sumber_rekayasa' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringSumberRekayasa::class . ',sumber_rekayasa',
            'pelaksana_rekayasa' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringPelaksanaRekayasa::class . ',pelaksana_rekayasa',
            'tanggal_ideation' => 'date',
            'kajian_teknis_due_date' => 'date',
            'kajian_teknis_status' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringPhaseStatus::class . ',phase_status',
            'kajian_teknis_status_changed_at' => 'datetime',
            'kajian_teknis_status_compliance' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringStatusCompliance::class,
            'pengadaan_due_date' => 'date',
            'pengadaan_status' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringPhaseStatus::class . ',phase_status',
            'pengadaan_status_changed_at' => 'datetime',
            'pengadaan_status_compliance' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringStatusCompliance::class,
            'uji_coba_due_date' => 'date',
            'uji_coba_status' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringPhaseStatus::class . ',phase_status',
            'uji_coba_status_changed_at' => 'datetime',
            'uji_coba_status_compliance' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringStatusCompliance::class,
            'standardisasi_due_date' => 'date',
            'standardisasi_status' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringPhaseStatus::class . ',phase_status',
            'standardisasi_status_changed_at' => 'datetime',
            'standardisasi_status_compliance' => LenientBackedEnumCast::class . ':' . MonitoringSafetyEngineeringStatusCompliance::class,
            'replikasi_due_date' => 'date',
            'replikasi_total_populasi' => 'integer',
            'replikasi_target_komitmen' => 'integer',
            'replikasi_aktual' => 'integer',
            'prediksi_penurunan_tangga_risiko' => 'integer',
            'terkait_hazard' => 'boolean',
            'terkait_insiden' => 'boolean',
            'potensi_peningkatan_efektivitas' => 'boolean',
            'total_risiko_signifikan' => 'integer',
            'jumlah_risiko_signifikan_tercover_rekayasa' => 'integer',
            'period_year' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(MonitoringSafetyEngineeringEvidence::class, 'record_id');
    }

    public function phaseStatusLogs(): HasMany
    {
        return $this->hasMany(MonitoringSafetyEngineeringPhaseStatusLog::class, 'record_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(MonitoringSafetyEngineeringRecordChangeLog::class, 'record_id');
    }

    public function getReplikasiPersentaseAttribute(): int
    {
        if ($this->replikasi_target_komitmen <= 0) {
            return 0;
        }

        return (int) round(($this->replikasi_aktual / $this->replikasi_target_komitmen) * 100);
    }
}
