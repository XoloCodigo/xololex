<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    protected $fillable = [
        'lawyer_id',
        'phone',
        'flow',
        'step',
        'data',
        'report_id',
        'sharepoint_form_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function sharepointForm(): BelongsTo
    {
        return $this->belongsTo(SharepointForm::class);
    }

    public function isIdle(): bool
    {
        return $this->flow === 'idle';
    }

    public function setStep(string $flow, string $step, array $mergeData = []): void
    {
        $this->update([
            'flow' => $flow,
            'step' => $step,
            'data' => array_merge($this->data ?? [], $mergeData),
        ]);
    }

    public function reset(): void
    {
        $this->update([
            'flow' => 'idle',
            'step' => 'start',
            'data' => null,
            'report_id' => null,
            'sharepoint_form_id' => null,
        ]);
    }
}
