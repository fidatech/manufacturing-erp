<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrRecord extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'employee_id');
    }

    public function auditLabel(): string
    {
        return $this->type.': '.($this->employee?->name ?? '');
    }
}
