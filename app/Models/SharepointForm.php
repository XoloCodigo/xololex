<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharepointForm extends Model
{
    protected $fillable = [
        'report_id',
        'lawyer_id',
        'company_id',
        'service_type',
        'hours_spent',
        'urgency_level',
        'requires_followup',
        'followup_date',
        'additional_notes',
        'status',
        'sharepoint_item_id',
        'pdf_path',
        'sharepoint_url',
    ];

    protected function casts(): array
    {
        return [
            'hours_spent' => 'decimal:2',
            'requires_followup' => 'boolean',
            'followup_date' => 'date',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
