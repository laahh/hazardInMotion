<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Incident;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncidentVictim extends Model
{
    use HasUuids;

    protected $table = 'er_incident_victims';

    public const CONDITIONS = [
        'selamat' => 'Selamat',
        'luka_ringan' => 'Luka Ringan',
        'luka_berat' => 'Luka Berat',
        'meninggal' => 'Meninggal',
    ];

    protected $fillable = ['incident_id', 'name', 'condition', 'details', 'created_by'];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }
}
