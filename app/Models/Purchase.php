<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'received_date' => 'date:Y-m-d'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(PurchaseCost::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
