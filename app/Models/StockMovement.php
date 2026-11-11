<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'mfg_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d', 'is_void' => 'boolean'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
