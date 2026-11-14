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
        Schema::create('contact_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('rfc_message_id')->nullable()->unique();
            $table->string('gmail_message_id')->nullable()->unique();
            $table->boolean('incoming')->default(false);
            $table->boolean('notification')->default(false);
            $table->timestamps();
            $table->index(['contact_message_id', 'notification', 'created_at', 'id'], 'contact_replies_history_index');
        });
        DB::table('permissions')->insertOrIgnore(['name' => 'contact.reply', 'guard_name' => 'admin', 'group_name' => 'Contact', 'created_at' => now(), 'updated_at' => now()]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        cache()->forget('admin_all_permissions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_replies');
    }
};
