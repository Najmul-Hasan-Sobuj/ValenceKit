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
        Schema::create('mcp_tools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->nullable()->unique();
            $table->string('class_name')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('provider')->nullable();
            $table->string('transport')->default('http');
            $table->string('required_scope')->default('mcp:tools.read');
            $table->string('health_status')->default('unknown');
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_tools');
    }
};
