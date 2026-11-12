<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One currency exchange: an amount sold in one currency and the amount bought in another. */
class CurrencyExchange extends Model
{
    public const OPERATIONS = ['multiply', 'divide'];

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'cancelled_at' => 'datetime'];

    public function sellAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sell_account_id');
    }

    public function buyAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'buy_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
