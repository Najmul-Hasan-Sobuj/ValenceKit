<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('description');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->date('issued_on');
            $table->date('due_on');
            $table->timestamps();
            $table->index('due_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
