<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing: a recipe says what goes into one unit (one can, one bag) of a product.
 * A production batch uses the raw materials and puts the finished product in stock.
 * Laboratory: test results for raw materials that arrive and for batches that are made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();          // the finished product
            $table->string('name');
            $table->string('method', 20)->default('liquid');      // liquid (blended in a tank) or powder (dry blend)
            $table->decimal('pack_weight', 18, 4)->default(0);    // weight of one unit, e.g. 20 kg in a can
            $table->decimal('extra_cost', 18, 4)->default(0);     // labour, power ... per unit, main currency
            $table->unsignedSmallInteger('shelf_life_months')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();          // raw material or packing
            $table->decimal('qty', 18, 6);                        // needed for ONE unit of the product
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->foreignId('recipe_id')->constrained();
            $table->foreignId('item_id')->constrained();          // the finished product
            $table->foreignId('warehouse_id')->constrained();     // raw materials are taken from here
            $table->foreignId('to_warehouse_id')->constrained('warehouses'); // finished product goes here
            $table->decimal('qty', 18, 4);                        // units made
            $table->string('batch_no', 60);
            $table->date('expiry_date')->nullable();
            $table->decimal('cost_base', 18, 2)->default(0);      // materials + extra cost, main currency
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->string('status', 20)->default('posted');      // posted, cancelled
            $table->string('qc_status', 20)->default('pending');  // pending, passed, failed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('qty', 18, 4);
            $table->decimal('cost_base', 18, 2)->default(0);
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->string('subject', 20);                        // product (a batch we made) or raw (material that arrived)
            $table->foreignId('item_id')->constrained();
            $table->string('batch_no', 60)->nullable();
            $table->foreignId('production_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->json('results');                              // [{parameter, standard, result, ok}]
            $table->string('status', 20);                         // passed, failed
            $table->string('tested_by', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // extra production cost (labour, power) that is added to the cost of the products made
        DB::table('accounts')->insert([
            'account_type_id' => DB::table('account_types')->where('key', 'expense')->value('id'),
            'name' => 'Production costs moved into products',
            'currency_id' => DB::table('currencies')->where('is_base', true)->value('id'),
            'system_key' => 'production_cost',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->where('system_key', 'production_cost')->delete();
        foreach (['lab_tests', 'production_lines', 'production_batches', 'recipe_lines', 'recipes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
