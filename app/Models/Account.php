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
        if (\App\Support\Num::isZero($balance)) {
            return '0.00 '.$code;
        }
        $amount = \App\Support\Num::format(\App\Support\Num::abs($balance), 2).' '.$code;
        $words = $this->balanceWords($balance);
        if ($words !== '' || $this->type?->is_money || $this->type?->is_party || $this->type?->key === 'shareholder') {
            return $amount.($words !== '' ? ' · '.$words : '');
        }

        return (\App\Support\Num::isPositive($balance) ? '' : '-').$amount;
    }

    /** What a balance means in plain words: "owes us", "we owe" ... Empty when there is nothing to say. */
    public function balanceWords(mixed $balance): string
    {
        if (\App\Support\Num::isZero($balance)) {
            return '';
        }
        $positive = \App\Support\Num::isPositive($balance);
        $type = $this->type;

        if ($type?->is_money) {
            return $positive ? '' : __('we owe');
        }
        if ($type?->key === 'shareholder') {
            return $positive ? __('owes the company') : __('capital');
        }
        if ($type?->is_party) {
            return $positive ? __('owes us') : __('we owe');
        }

        return '';
    }

    /** First letters of the name, for the round badge on a statement. */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    /** Name with currency, as shown in pick lists. */
    public function label(): string
    {
        return $this->name.' ('.($this->currency?->code ?? '').')';
    }
}
