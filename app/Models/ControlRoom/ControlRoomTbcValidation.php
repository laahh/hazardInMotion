<?php

declare(strict_types=1);

namespace App\Models\ControlRoom;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ControlRoomTbcValidation extends Model
{
    protected $table = 'control_room_tbc_validations';

    /**
     * @var list<string>
     */
    public const FILLABLE_FIELDS = [
        'no_alert',
        'validator',
        'tasklist',
        'to_be_concerned_hazard',
        'gr',
        'catatan',
        'nomor_gr_valid',
        'kategori_gr_valid_kpi',
        'blindspot_terlapor_bc',
        'kronologi_singkat',
        'rootcause_aktual',
        'detail_rootcause_aktual',
        'sid_pekerja_terlibat',
        'sid_pengawas_aktual',
        'tindakan_perbaikan_aktual',
        'no_item_pspp',
        'kategori_gr',
    ];

    protected $fillable = [
        ...self::FILLABLE_FIELDS,
        'created_by',
        'updated_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
