<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['is_base' => 'boolean', 'is_active' => 'boolean'];

    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }
}
