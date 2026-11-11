<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'voided_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
