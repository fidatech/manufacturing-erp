<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance month by month: for every employee the days present, absent, on leave and holidays.
 * (The marks of the older day-by-day sheet stay in hr_records and are not touched.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('month');                              // always the first day of the month
            $table->decimal('present', 4, 1)->default(0);       // half days are allowed: 22.5
            $table->decimal('absent', 4, 1)->default(0);
            $table->decimal('leave', 4, 1)->default(0);
            $table->decimal('holidays', 4, 1)->default(0);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_months');
    }
};
