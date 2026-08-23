<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChecklistTemplateItem extends Model
{
    use HasUuids;

    protected $table = 'er_checklist_template_items';

    protected $fillable = [
        'checklist_template_id', 'sort_order', 'item_text', 'answer_type', 'is_required',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    public function checklistTemplate()
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }
}
