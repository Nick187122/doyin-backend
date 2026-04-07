<?php
require '/var/www/doyin-backend/vendor/autoload.php';
$app = require '/var/www/doyin-backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hash = \Illuminate\Support\Facades\Hash::make('password');

\App\Models\User::updateOrCreate(
    ['email' => 'admin@doyinkenya.com'],
    [
        'name' => 'Doyin Admin',
        'password' => $hash,
        'must_change_password' => 0
    ]
);
echo "User recreated and password set to password.\\n";
