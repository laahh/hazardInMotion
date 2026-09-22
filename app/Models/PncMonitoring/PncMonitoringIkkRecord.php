<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringIkkRecord extends Model
{
    protected $table = 'pnc_monitoring_ikk_records';

    /**
     * @var list<string>
     */
    public const FILLABLE_FIELDS = [
        'upsert_key',
        'jenis',
        'nomor',
        'pekerjaan',
        'tanggal',
        'minggu',
        'bulan',
        'tahun',
        'site',
        'mine_contractor',
        'perusahaan',
        'finding_ia',
        'finding_verlap',
        'ia',
        'ipk',
        'plan_okk',
        'okk_1',
        'okk_2',
        'okk_3',
        'okk_layer_2',
        'okk_layer_3',
        'okk_layer_4',
    ];

    protected $fillable = [
        ...self::FILLABLE_FIELDS,
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'minggu' => 'integer',
            'bulan' => 'integer',
            'tahun' => 'integer',
            'finding_ia' => 'integer',
            'finding_verlap' => 'integer',
            'ia' => 'integer',
            'ipk' => 'integer',
            'plan_okk' => 'integer',
            'okk_1' => 'integer',
            'okk_2' => 'integer',
            'okk_3' => 'integer',
            'okk_layer_2' => 'integer',
            'okk_layer_3' => 'integer',
            'okk_layer_4' => 'integer',
        ];
    }

    public static function buildUpsertKey(string $nomor, ?string $tanggalYmd, ?int $minggu, ?int $tahun): string
    {
        $nomor = trim($nomor);
        if ($tanggalYmd !== null && $tanggalYmd !== '') {
            return $nomor.'|'.$tanggalYmd;
        }

        return $nomor.'|W'.(string) ($minggu ?? 0).'|Y'.(string) ($tahun ?? 0);
    }

    public function okkPerformance(): ?float
    {
        $plan = (int) $this->plan_okk;
        if ($plan <= 0) {
            return null;
        }

        return ((int) $this->okk_1 + (int) $this->okk_2 + (int) $this->okk_3) / $plan;
    }

    public function layer2UpPerformance(): ?float
    {
        $plan = (int) $this->plan_okk;
        if ($plan <= 0) {
            return null;
        }

        return ((int) $this->okk_layer_2 + (int) $this->okk_layer_3 + (int) $this->okk_layer_4) / $plan;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
