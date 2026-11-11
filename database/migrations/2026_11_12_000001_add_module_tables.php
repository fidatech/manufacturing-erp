<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // short lists the company fills in itself: departments, designations, document types, asset categories
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['type', 'name']);
        });

        // special price of an item for one customer, in the customer's currency
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 18, 6);
            $table->timestamps();
            $table->unique(['customer_id', 'item_id']);
        });

        // human-resource records of every kind: attendance, leave, appraisal, contract, shift, overtime, allowance, deduction
        Schema::create('hr_records', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('employee_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('date');
            $table->date('end_date')->nullable();
            $table->string('title', 150)->nullable();
            $table->string('status', 30)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'employee_id', 'date']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->string('category', 150)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::dropIfExists('hr_records');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('lookups');
    }
};
