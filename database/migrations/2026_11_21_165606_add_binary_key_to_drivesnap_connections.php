<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivesnap_connections', function (Blueprint $table): void {
            $table->string('binary_key', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('drivesnap_connections', function (Blueprint $table): void {
            $table->dropColumn('binary_key');
        });
    }
};
