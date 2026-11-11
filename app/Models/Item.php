<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    /** Name with code and packing, as shown in pick lists. */
    public function label(): string
    {
        return trim($this->name.($this->code ? ' ('.$this->code.')' : '').($this->packing ? ' - '.$this->packing : ''));
    }
}
