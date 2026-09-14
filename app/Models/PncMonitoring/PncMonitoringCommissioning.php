<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringCommissioning extends Model
{
    protected $table = 'pnc_monitoring_commissionings';

    /**
     * @var list<string>
     */
    public const FILLABLE_FIELDS = [
        'site',
        'no_register_spip',
        'detail_jenis_spip',
        'keterangan_sko',
        'nama_pengawas_teknis',
        'permohonan_dokumen_1',
        'week',
        'tahun',
        'pemilik_spip',
        'pengelola_spip',
        'temuan_komisioning',
        'status_komisioning',
        'keterangan',
        'alasan_reject',
        'status',
        'performance_sko',
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
            'permohonan_dokumen_1' => 'date',
            'week' => 'integer',
            'tahun' => 'integer',
            'temuan_komisioning' => 'integer',
            'performance_sko' => 'float',
        ];
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
