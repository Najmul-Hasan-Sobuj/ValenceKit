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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('module');          // 'finance', 'product', etc.
            $table->string('type')->nullable(); // 'income' | 'expense' | null (module-specific)
            $table->string('slug');            // 'cloud_infra' — stable key, references category_key in transactions
            $table->string('name');            // 'Cloud & Infrastructure'
            $table->string('icon')->nullable(); // Lucide icon name: 'Server'
            $table->string('color')->nullable(); // '#6366f1'
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['module', 'slug']);
            $table->index(['module', 'type']);
            $table->index(['module', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
