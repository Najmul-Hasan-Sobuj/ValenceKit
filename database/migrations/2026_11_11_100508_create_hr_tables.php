<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->json('weekdays');
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->string('timezone')->default('Asia/Dhaka');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->unique();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 100)->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('designation');
            $table->foreignId('manager_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->unique()->constrained('admins')->nullOnDelete();
            $table->date('joined_on');
            $table->date('ended_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('emergency_contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'department_id']);
        });
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->json('snapshot');
            $table->unique(['employee_id', 'effective_from']);
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('leave_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('days');
            $table->string('reason');
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('dates');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'start_date']);
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->json('schedule');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['attendance_corrections', 'attendances', 'leave_requests', 'leave_allocations', 'leave_types', 'employee_schedules', 'employees', 'holidays', 'work_schedules', 'departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
