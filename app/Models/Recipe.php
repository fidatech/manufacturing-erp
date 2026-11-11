<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /** Cost of the materials for one unit at today's average costs, plus the extra cost. Main currency. */
    public function unitCost(): string
    {
        $cost = (string) $this->extra_cost;
        foreach ($this->lines as $line) {
            $cost = \App\Support\Num::add($cost, \App\Support\Num::mul($line->qty, $line->item->avg_cost));
        }

        return \App\Support\Num::round($cost, 4);
    }

    /**
     * For liquids: the water that fills one unit up to its weight.
     * Water is not kept in stock; it is what is left after everything weighed in kilograms or liters.
     */
    public function water(): string
    {
        if ($this->method !== 'liquid' || ! \App\Support\Num::isPositive($this->pack_weight)) {
            return '0';
        }
        $weighed = '0';
        foreach ($this->lines as $line) {
            if (in_array(strtolower((string) $line->item->unit?->symbol), ['kg', 'l'], true)) {
                $weighed = \App\Support\Num::add($weighed, $line->qty);
            }
        }
        $water = \App\Support\Num::sub($this->pack_weight, $weighed);

        return \App\Support\Num::isPositive($water) ? \App\Support\Num::qty($water) : '0';
    }
}
