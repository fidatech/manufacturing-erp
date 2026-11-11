<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lookup extends Model
{
    use Audited;

    protected $guarded = [];

    /** Names of one list, for a pick list: Lookup::options('department'). */
    public static function options(string $type): array
    {
        return static::where('type', $type)->orderBy('name')->pluck('name', 'name')->all();
    }
}
