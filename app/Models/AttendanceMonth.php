<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One employee's attendance in one month: days present, absent, on leave and holidays. */
class AttendanceMonth extends Model
{
    public const COLUMNS = ['present', 'absent', 'leave', 'holidays'];

    protected $guarded = [];

    protected $casts = ['month' => 'date:Y-m-d'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'employee_id');
    }
}
