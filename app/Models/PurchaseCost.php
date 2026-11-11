<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseCost extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_id');
    }
}
