<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivesnap_connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('installation_id')->unique();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('folder_id')->nullable();
            $table->boolean('schedule_enabled')->default(false);
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->timestamps();
        });
        Schema::create('drivesnap_backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('requested_by_type')->nullable();
            $table->string('requested_by_id', 191)->nullable();
            $table->index(['requested_by_type', 'requested_by_id']);
            $table->string('status', 20)->default('queued')->index();
            $table->boolean('upload_to_drive')->default(true);
            $table->string('driver', 20)->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('drive_file_id')->nullable();
            $table->text('upload_url')->nullable();
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivesnap_backups');
        Schema::dropIfExists('drivesnap_connections');
    }
};
