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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);
            $table->string('source_id');
            $table->string('name');
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('website')->nullable();
            $table->text('maps_url')->nullable();
            $table->string('category')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('query')->nullable();
            $table->json('details')->nullable();
            $table->unique(['source', 'source_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
