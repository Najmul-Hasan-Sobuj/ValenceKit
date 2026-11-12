<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('employee_name');
            $table->string('period', 7);
            $table->unsignedBigInteger('base_minor');
            $table->unsignedBigInteger('allowance_minor')->default(0);
            $table->unsignedBigInteger('deduction_minor')->default(0);
            $table->string('currency', 3);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
