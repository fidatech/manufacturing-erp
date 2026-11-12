<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Currency exchange slips: what was sold, at which rate, and what was bought.
 * The money movement itself is a journal entry that points back to the slip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->foreignId('sell_account_id')->constrained('accounts');   // the currency given away leaves this account
            $table->foreignId('buy_account_id')->constrained('accounts');    // the currency received enters this account
            $table->decimal('sell_amount', 18, 2);
            $table->decimal('rate', 18, 6);
            $table->string('operation', 10);                                 // multiply or divide
            $table->decimal('buy_amount', 18, 2);
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};
