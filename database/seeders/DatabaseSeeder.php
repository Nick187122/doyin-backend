<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'                => 'Doyin Admin',
                'password'            => Hash::make($password),
                'must_change_password' => true,
                'active_device_token' => null,
            ]
        );
    }
}
