<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A designation belongs to a department. The same job name may exist in two departments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('lookups')->nullOnDelete();
        });
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropUnique(['type', 'name']);
            $table->unique(['type', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropUnique(['type', 'parent_id', 'name']);
            $table->unique(['type', 'name']);
        });
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
