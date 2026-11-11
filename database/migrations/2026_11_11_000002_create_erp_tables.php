<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The whole ERP schema.
 *
 * Three engines carry everything:
 *   accounts + journal_entries + journal_lines   every money movement
 *   items + stock_movements                      every stock movement
 *   purchases / sales / stock_docs / ...         the papers that cause those movements
 *
 * Money is decimal(18,2), quantities decimal(18,4), costs and rates decimal(18,6).
 * "base" columns are amounts in the main currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title');
            $table->string('category', 100)->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // rate = how many units of this currency equal 1 unit of the main currency
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 18, 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['currency_id', 'date']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('symbol', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->string('nature', 10); // asset, liability, equity, income, expense
            $table->boolean('is_party')->default(false);  // a person or company with phone and address
            $table->boolean('is_money')->default(false);  // cash, bank, sarafi: money can be paid from it
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_type_id')->constrained();
            $table->string('code', 30)->nullable()->unique();
            $table->string('name');
            $table->foreignId('currency_id')->constrained();
            $table->string('system_key', 50)->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('company')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable(); // extra fields of a type: bank account number, job title, salary, share ...
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['account_type_id', 'is_active']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('location')->nullable();
            $table->foreignId('manager_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20); // product or raw
            $table->timestamps();
            $table->unique(['name', 'type']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // product or raw
            $table->string('code', 50)->nullable()->unique();
            $table->string('name');
            $table->foreignId('item_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->string('packing', 100)->nullable();   // e.g. "Can 20 kg"
            $table->decimal('sale_price', 18, 2)->default(0);  // in the main currency
            $table->decimal('avg_cost', 18, 6)->default(0);    // weighted average, main currency
            $table->decimal('min_stock', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->string('type', 30)->index(); // receipt, payment, expense, sale, purchase ...
            $table->string('description', 500)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->date('date')->index();                 // copy of the entry date, for fast statements
            $table->decimal('debit', 18, 2)->default(0);   // in the account's currency
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('base_debit', 18, 2)->default(0);  // in the main currency
            $table->decimal('base_credit', 18, 2)->default(0);
            $table->string('memo', 500)->nullable();
            $table->boolean('is_void')->default(false);
            $table->index(['account_id', 'is_void', 'date']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('batch_no', 60)->default('');
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty_in', 18, 4)->default(0);
            $table->decimal('qty_out', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0); // main currency
            $table->string('type', 30); // purchase, sale, transfer, count, opening, return ...
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('memo', 500)->nullable();
            $table->boolean('is_void')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['item_id', 'warehouse_id', 'is_void']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();   // order, invoice, return
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->foreignId('supplier_id')->constrained('accounts');
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('currency_id')->constrained();
            $table->decimal('rate', 18, 6)->default(1);
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, posted, cancelled; orders: open, converted
            $table->date('received_date')->nullable();   // goods arrived (GRN); null = on the way
            $table->string('grn_number', 30)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);        // supplier's bill, purchase currency
            $table->decimal('costs_base', 18, 2)->default(0);   // transport, customs ... main currency
            $table->decimal('paid', 18, 2)->default(0);         // paid on the spot, purchase currency
            $table->foreignId('paid_from_id')->nullable()->constrained('accounts');
            $table->foreignId('parent_id')->nullable()->constrained('purchases')->nullOnDelete(); // order or returned invoice
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->string('batch_no', 60)->default('');
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('price', 18, 6);            // purchase currency
            $table->decimal('total', 18, 2);
            $table->decimal('unit_cost_base', 18, 6)->default(0); // price + share of extra costs, main currency
        });

        Schema::create('purchase_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // transport, customs, commission ...
            $table->decimal('amount', 18, 2);             // in the currency of the account it was paid from
            $table->foreignId('paid_from_id')->constrained('accounts');
            $table->decimal('amount_base', 18, 2)->default(0);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();   // quotation, invoice, return
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->foreignId('customer_id')->constrained('accounts');
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('currency_id')->constrained();
            $table->decimal('rate', 18, 6)->default(1);
            $table->string('status', 20)->default('draft')->index(); // draft, posted, cancelled; quotations: open, converted
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('received', 18, 2)->default(0);   // paid on the spot
            $table->foreignId('received_in_id')->nullable()->constrained('accounts');
            $table->decimal('previous_balance', 18, 2)->default(0); // customer balance before this bill, for the print
            $table->decimal('cost_base', 18, 2)->default(0);        // cost of the goods, main currency
            $table->string('delivery_status', 20)->default('pending'); // pending, dispatched, delivered
            $table->date('dispatched_date')->nullable();
            $table->date('delivered_date')->nullable();
            $table->string('transport_no', 100)->nullable();
            $table->string('driver', 150)->nullable();
            $table->string('delivery_address')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('sales')->nullOnDelete(); // quotation or returned invoice
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->string('description')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('price', 18, 6);   // sale currency; 0 = free item
            $table->decimal('total', 18, 2);
            $table->decimal('cost_base', 18, 2)->default(0);
        });

        // stock transfers, physical counts and opening stock
        Schema::create('stock_docs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // transfer, count, opening
            $table->string('number', 30)->unique();
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses');
            $table->string('status', 20)->default('posted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_doc_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_doc_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->string('batch_no', 60)->default('');
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty', 18, 4)->default(0);         // moved, or counted
            $table->decimal('system_qty', 18, 4)->default(0);  // counts: what the system had
            $table->decimal('unit_cost', 18, 6)->default(0);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('class', 20);      // fixed or current
            $table->string('kind', 20);       // tangible or intangible
            $table->date('purchase_date');
            $table->decimal('quantity', 18, 2)->default(1);
            $table->decimal('cost', 18, 2);            // in the currency it was paid in
            $table->foreignId('currency_id')->constrained();
            $table->decimal('cost_base', 18, 2);       // main currency
            $table->decimal('salvage_base', 18, 2)->default(0);
            $table->unsignedSmallInteger('life_months')->default(0); // 0 = does not lose value
            $table->foreignId('paid_from_id')->nullable()->constrained('accounts');
            $table->string('status', 20)->default('active'); // active, disposed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);  // 2026-11
            $table->decimal('amount_base', 18, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['asset_id', 'period']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();  // 2026-11 (Gregorian month)
            $table->date('date');
            $table->string('status', 20)->default('draft'); // draft, posted
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('accounts');
            $table->decimal('salary', 18, 2)->default(0);     // employee's currency
            $table->decimal('bonus', 18, 2)->default(0);
            $table->decimal('deduction', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);
            $table->string('note')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'payroll_lines', 'payrolls', 'asset_depreciations', 'assets', 'stock_doc_lines', 'stock_docs',
            'sale_lines', 'sales', 'purchase_costs', 'purchase_lines', 'purchases', 'stock_movements',
            'journal_lines', 'journal_entries', 'items', 'item_categories', 'warehouses', 'accounts',
            'account_types', 'units', 'exchange_rates', 'currencies', 'attachments', 'audit_logs', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
