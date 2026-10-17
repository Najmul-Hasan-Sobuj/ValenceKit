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
        Schema::create('tracking_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('clarity_enabled')->default(false);
            $table->string('clarity_project_id')->nullable();
            $table->boolean('google_analytics_enabled')->default(false);
            $table->string('google_analytics_id')->nullable();
            $table->boolean('google_tag_manager_enabled')->default(false);
            $table->string('google_tag_manager_id')->nullable();
            $table->boolean('facebook_pixel_enabled')->default(false);
            $table->string('facebook_pixel_id')->nullable();
            $table->boolean('custom_scripts_enabled')->default(true);
            $table->mediumText('custom_head_scripts')->nullable();
            $table->mediumText('custom_body_scripts')->nullable();
            $table->boolean('exclude_admins')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_settings');
    }
};
