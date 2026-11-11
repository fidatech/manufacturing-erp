<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRate extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function auditLabel(): string
    {
        return ($this->currency?->code ?? '').': '.$this->rate;
    }
}
