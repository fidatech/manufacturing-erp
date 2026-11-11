<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'manager_account_id');
    }
}
