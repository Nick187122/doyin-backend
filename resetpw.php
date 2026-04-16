<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = env('ADMIN_EMAIL');
$password = env('ADMIN_PASSWORD');

if (! $email || ! $password) {
    fwrite(STDERR, "ADMIN_EMAIL and ADMIN_PASSWORD must be set in the environment.\n");
    exit(1);
}

\App\Models\User::updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Doyin Admin',
        'password' => \Illuminate\Support\Facades\Hash::make($password),
        'must_change_password' => false,
        'active_device_token' => null,
    ]
);

echo "Admin user synced from environment credentials.\n";
