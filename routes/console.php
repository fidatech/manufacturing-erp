<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// On the 1st of every month, last month's profit is added to the shareholders' accounts.
// The server needs one cron line:  * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
\Illuminate\Support\Facades\Schedule::command('profit:distribute')->monthlyOn(1, '01:00')->timezone('Asia/Kabul')->withoutOverlapping();
