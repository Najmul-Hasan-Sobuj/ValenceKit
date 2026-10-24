<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\McpToolSyncService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminSeeder::class,
            CategorySeeder::class,
        ]);

        app(McpToolSyncService::class)->sync();

        // User::factory(10)->create();

        $userEmail = config('auth.bootstrap_user_email') ?? 'test@example.com';
        $userPassword = config('auth.bootstrap_user_password');

        User::factory()->create([
            'name' => 'Test User',
            'email' => $userEmail,
            ...($userPassword ? ['password' => Hash::make($userPassword)] : []),
        ]);
    }
}
