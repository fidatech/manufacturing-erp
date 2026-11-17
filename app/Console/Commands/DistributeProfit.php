<?php

namespace App\Console\Commands;

use App\Exceptions\UserError;
use App\Services\ProfitService;
use Illuminate\Console\Command;

/** Share last month's profit between the shareholders. Runs by itself on the 1st of every month. */
class DistributeProfit extends Command
{
    protected $signature = 'profit:distribute {--month= : The month to share, YYYY-MM. Default: last month}';

    protected $description = 'Add last month\'s net profit to the shareholders\' accounts by their share';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('The month must look like 2026-10.');

            return self::FAILURE;
        }
        if ($month >= now()->format('Y-m')) {
            $this->error('Only a month that has ended can be shared.');

            return self::FAILURE;
        }

        try {
            [$entry, $message] = ProfitService::distributeMonth($month);
        } catch (UserError $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }
        $entry ? $this->info($message) : $this->line($message);

        return self::SUCCESS;
    }
}
