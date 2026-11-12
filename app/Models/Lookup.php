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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Designations with their department, for a pick list: ['Operator' => 'Operator · Production']. */
    public static function designations(): array
    {
        $options = [];
        foreach (static::with('parent')->where('type', 'designation')->orderBy('name')->get() as $row) {
            $options[$row->name] = isset($options[$row->name]) ? $row->name : $row->name.($row->parent ? ' · '.$row->parent->name : '');
        }

        return $options;
    }

    /** Names of one list, for a pick list: Lookup::options('department'). */
    public static function options(string $type): array
    {
        return static::where('type', $type)->orderBy('name')->pluck('name', 'name')->all();
    }
}
