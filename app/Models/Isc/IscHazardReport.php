<?php

declare(strict_types=1);

namespace App\Models\Isc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IscHazardReport extends Model
{
    protected $table = 'isc_hazard_reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'intervention_id',
        'created_by',
        'username',
        'password',
        'perusahaan',
        'pic_sid',
        'pic_npk',
        'pic_nama',
        'pic_jabatan',
        'tools_pengamatan',
        'site',
        'lokasi',
        'detail_lokasi',
        'keterangan_lokasi',
        'sid_pelapor',
        'npk_pelapor',
        'nama_pelapor',
        'jabatan_pelapor',
        'area_pja_bc',
        'area_pja_mitra',
        'foto_path',
        'is_observasi_area_kritis',
        'ketidaksesuaian',
        'sub_ketidaksesuaian',
        'quick_action',
        'deskripsi_temuan',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_observasi_area_kritis' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(IscBoundaryEvent::class, 'event_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(IscIntervention::class, 'intervention_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
