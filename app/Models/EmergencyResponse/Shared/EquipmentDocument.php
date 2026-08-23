<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EquipmentDocument extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_equipment_documents';

    protected $fillable = [
        'documentable_type', 'documentable_id', 'type', 'original_name', 'file_path', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
