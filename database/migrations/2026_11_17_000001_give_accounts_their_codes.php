<?php

use App\Models\Account;
use App\Support\AccountCodes;
use Illuminate\Database\Migrations\Migration;

/** Chart of accounts codes for the accounts made before codes existed (oldest first). */
return new class extends Migration
{
    public function up(): void
    {
        Account::whereNull('code')->orderByRaw('system_key IS NULL')->orderBy('id')->each(function (Account $account) {
            if ($code = AccountCodes::for($account)) {
                $account->code = $code;
                $account->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // the codes stay: they do no harm
    }
};
