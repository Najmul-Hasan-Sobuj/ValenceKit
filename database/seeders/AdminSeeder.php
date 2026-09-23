<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the default admin account.
     */
    public function run(): void
    {
        $email = config('admin.bootstrap_email');
        $password = config('admin.bootstrap_password');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || strlen($password) < 12) {
            throw new \InvalidArgumentException('Set ADMIN_BOOTSTRAP_EMAIL and a strong ADMIN_BOOTSTRAP_PASSWORD (at least 12 characters) before seeding admins.');
        }

        Admin::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
    }
}
