<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountType extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['is_party' => 'boolean', 'is_money' => 'boolean', 'is_system' => 'boolean'];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
