<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Inspection;

use App\Models\EmergencyResponse\MasterData\ChecklistTemplateItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InspectionResult extends Model
{
    use HasUuids;

    protected $table = 'er_inspection_results';

    public const COMPLIANCE_VALUES = [
        'sesuai' => 'Sesuai',
        'tidak_sesuai' => 'Tidak Sesuai',
        'tidak_berlaku' => 'Tidak Berlaku',
    ];

    protected $fillable = [
        'inspection_id', 'checklist_template_item_id', 'sort_order', 'item_text_snapshot',
        'answer_type_snapshot', 'answer_value', 'notes', 'photo_before_path', 'photo_after_path',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    public function templateItem()
    {
        return $this->belongsTo(ChecklistTemplateItem::class, 'checklist_template_item_id');
    }

    public function isNonCompliant(): bool
    {
        return $this->answer_type_snapshot === 'compliance' && $this->answer_value === 'tidak_sesuai';
    }
}
