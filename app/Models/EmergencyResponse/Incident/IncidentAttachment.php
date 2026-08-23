<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncidentAttachment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'er_incident_attachments';

    protected $fillable = ['incident_id', 'type', 'original_name', 'file_path', 'uploaded_by', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
