<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase requests (a supplier is not known yet), the supplier's quotation number and bill number
 * on purchases and sales, and the weight of one unit of an item, used to share purchase costs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->string('quotation_no', 60)->nullable()->after('supplier_invoice_no');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->string('quotation_no', 60)->nullable()->after('rate');
            $table->string('bill_no', 60)->nullable()->after('quotation_no');
        });
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('weight', 18, 4)->default(0)->after('packing'); // kg in one unit
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('weight'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['quotation_no', 'bill_no']));
        Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn('quotation_no'));
    }
};
