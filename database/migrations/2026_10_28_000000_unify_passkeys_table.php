<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('passkeys') && ! Schema::hasColumn('passkeys', 'user_type')) {
            Schema::table('passkeys', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['user_id']);
                }

                $table->string('user_type')->default(User::class)->after('id');
                $table->index(['user_type', 'user_id']);
            });

            DB::table('passkeys')->whereNull('user_type')->orWhere('user_type', '')->update([
                'user_type' => User::class,
            ]);
        }

        if (Schema::hasTable('admin_passkeys')) {
            $adminPasskeys = DB::table('admin_passkeys')->get();

            foreach ($adminPasskeys as $adminPasskey) {
                $exists = DB::table('passkeys')
                    ->where('credential_id', $adminPasskey->credential_id)
                    ->exists();

                if (! $exists) {
                    DB::table('passkeys')->insert([
                        'user_type' => Admin::class,
                        'user_id' => $adminPasskey->admin_id,
                        'name' => $adminPasskey->name,
                        'credential_id' => $adminPasskey->credential_id,
                        'credential' => $adminPasskey->credential,
                        'last_used_at' => $adminPasskey->last_used_at,
                        'created_at' => $adminPasskey->created_at,
                        'updated_at' => $adminPasskey->updated_at,
                    ]);
                }
            }

            Schema::dropIfExists('admin_passkeys');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('admin_passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('admin_id');
        });

        if (Schema::hasTable('passkeys') && Schema::hasColumn('passkeys', 'user_type')) {
            Schema::table('passkeys', function (Blueprint $table) {
                $table->dropIndex(['user_type', 'user_id']);
                $table->dropColumn('user_type');
            });
        }
    }
};
