<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Report extends Model
{
    protected $fillable = [
        'folio',
        'lawyer_id',
        'company_name',
        'visit_reason',
        'contact_met',
        'contact_position',
        'findings',
        'risks',
        'recommendations',
        'observations',
        'visit_date',
        'status',
        'word_path',
        'pdf_path',
        'sharepoint_url',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'attachments' => 'array',
        ];
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Lawyer::class);
    }

public function sharepointForm(): HasOne
    {
        return $this->hasOne(SharepointForm::class);
    }

    public static function generateFolio(): string
    {
        $last = static::latest('id')->value('folio');
        $number = $last ? (int) substr($last, 4) + 1 : 1;

        return 'RL-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
