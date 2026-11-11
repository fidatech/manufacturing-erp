<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'dispatched_date' => 'date:Y-m-d', 'delivered_date' => 'date:Y-m-d'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function receivedIn(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'received_in_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
