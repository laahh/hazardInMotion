<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HsecmTasklistEvidence extends Model
{
    protected $table = 'hsecm_tasklist_evidences';

    protected $fillable = [
        'tasklist_item_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'submission_batch',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'submission_batch' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(HsecmTasklistItem::class, 'tasklist_item_id');
    }

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
