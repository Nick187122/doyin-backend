<?php
require '/var/www/doyin-backend/vendor/autoload.php';
$app = require '/var/www/doyin-backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.sqlite_import' => [
    'driver' => 'sqlite',
    'database' => '/home/ubuntu/database.sqlite',
    'prefix' => '',
]]);

use Illuminate\Support\Facades\DB;

$tables = ['users', 'categories', 'products', 'settings', 'hero_images', 'user_interactions'];

DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0;');

foreach ($tables as $table) {
    echo "Migrating $table...\n";
    DB::connection('mysql')->table($table)->truncate();
    $rows = DB::connection('sqlite_import')->table($table)->get();
    foreach ($rows as $row) {
        DB::connection('mysql')->table($table)->insert((array) $row);
    }
}

DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Migration Done!\n";
