<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gmail_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->boolean('singleton')->default(true)->unique();
            $table->string('email');
            $table->text('refresh_token');
            $table->timestamps();
        });

        DB::table('permissions')->insertOrIgnore(['name' => 'contact.manage', 'guard_name' => 'admin', 'group_name' => 'Contact', 'created_at' => now(), 'updated_at' => now()]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        cache()->forget('admin_all_permissions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmail_mailboxes');
    }
};
