<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - A reference on money entries: the TT number of a bank or sarafi transfer, a cheque number ...
 * - The system account "Dead cash": money written off because it will not come back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('description');
        });

        $currency = DB::table('currencies')->where('is_base', true)->value('id');
        $type = DB::table('account_types')->where('key', 'expense')->value('id');
        if ($currency && $type && ! DB::table('accounts')->where('system_key', 'dead_cash')->exists()) {
            DB::table('accounts')->insert([
                'account_type_id' => $type, 'code' => DB::table('accounts')->where('code', '5700')->exists() ? null : '5700',
                'name' => 'Dead cash (money written off)', 'currency_id' => $currency, 'system_key' => 'dead_cash',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('system_key', 'dead_cash')->whereNotExists(fn ($q) => $q->from('journal_lines')->whereColumn('journal_lines.account_id', 'accounts.id'))->delete();
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
