<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['meta' => 'array', 'is_active' => 'boolean'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** The account the system itself posts to: Account::system('sales'). */
    public static function system(string $key): self
    {
        static $cache = [];

        return $cache[$key] ??= static::where('system_key', $key)->firstOrFail();
    }

    /** Accounts of one kind, e.g. Account::ofType('customer'). */
    public function scopeOfType($query, string|array $keys)
    {
        return $query->whereHas('type', fn ($q) => $q->whereIn('key', (array) $keys));
    }

    /** Cash boxes, banks and sarafis: accounts money can be paid from or received in. */
    public function scopeMoney($query)
    {
        return $query->whereHas('type', fn ($q) => $q->where('is_money', true));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function metaValue(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * A balance in plain words. Positive means debit (they owe us / we have),
     * negative means credit (we owe them).
     */
    public function describeBalance(mixed $balance): string
    {
        $code = $this->currency?->code ?? '';
        $amount = \App\Support\Num::format(\App\Support\Num::abs($balance), 2).' '.$code;

        if (\App\Support\Num::isZero($balance)) {
            return '0.00 '.$code;
        }
        $positive = \App\Support\Num::isPositive($balance);
        $type = $this->type;

        if ($type?->is_money) {
            return $positive ? $amount : $amount.' · '.__('we owe');
        }
        if ($type?->key === 'shareholder') {
            return $amount.' · '.($positive ? __('owes the company') : __('capital'));
        }
        if ($type?->is_party) {
            return $amount.' · '.($positive ? __('owes us') : __('we owe'));
        }

        return ($positive ? '' : '-').$amount;
    }

    /** Name with currency, as shown in pick lists. */
    public function label(): string
    {
        return $this->name.' ('.($this->currency?->code ?? '').')';
    }
}
