<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function auditLabel(): string
    {
        return $this->period;
    }
}
