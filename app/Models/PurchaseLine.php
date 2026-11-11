<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseLine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['mfg_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
