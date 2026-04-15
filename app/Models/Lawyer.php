<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lawyer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function sharepointForms(): HasMany
    {
        return $this->hasMany(SharepointForm::class);
    }

    public function activeConversation()
    {
        return $this->conversations()->latest()->first();
    }
}
