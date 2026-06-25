<?php

/**
 * SQLite to PostgreSQL Data Migration Script
 * 
 * Usage: php migrate_to_postgresql.php
 * 
 * Prerequisites:
 * 1. Update your .env with Supabase PostgreSQL credentials
 * 2. Run: php artisan migrate --force
 * 3. Run this script: php migrate_to_postgresql.php
 * 4. Verify data in PostgreSQL
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "========================================\n";
echo "  SQLite → PostgreSQL Data Migration\n";
echo "========================================\n\n";

// Verify we're connected to PostgreSQL first
$driver = DB::connection()->getDriverName();
echo "Current DB driver: $driver\n";

if ($driver === 'sqlite') {
    echo "⚠️  ERROR: You're still connected to SQLite.\n";
    echo "   Update your .env file with PostgreSQL credentials first.\n";
    exit(1);
}

if ($driver !== 'pgsql') {
    echo "⚠️  ERROR: Connected to '$driver', not PostgreSQL.\n";
    exit(1);
}

echo "✅ Connected to PostgreSQL. Starting migration...\n\n";

// Tables to migrate in order (respecting foreign keys)
$tables = [
    'users' => ['id', 'name', 'email', 'email_verified_at', 'password', 'must_change_password',
                'password_change_otp', 'password_change_otp_expires_at', 'active_device_token',
                'remember_token', 'created_at', 'updated_at'],
    'categories' => ['id', 'name', 'is_pump', 'has_ideal_power', 'created_at', 'updated_at'],
    'products' => ['id', 'category_id', 'name', 'description', 'image_path',
                   'max_flow_rate', 'max_height', 'recommended_depth', 'ideal_power',
                   'in_stock', 'views_count', 'created_at', 'updated_at'],
    'hero_images' => ['id', 'image_path', 'title', 'is_active', 'order', 'created_at', 'updated_at'],
    'settings' => ['id', 'key', 'value', 'created_at', 'updated_at'],
    'user_interactions' => ['id', 'type', 'name', 'email', 'content', 'is_read', 'created_at', 'updated_at'],
    'salespersons' => ['id', 'name', 'phone_number', 'is_active', 'created_at', 'updated_at'],
];

// Store SQLite connection
$sqlite = DB::connection('sqlite');

// Check if SQLite connection is configured
$sqliteDbPath = database_path('database.sqlite');
if (!file_exists($sqliteDbPath)) {
    echo "⚠️  SQLite database not found at: $sqliteDbPath\n";
    echo "   Skipping migration (assuming PostgreSQL already has data).\n";
    exit(0);
}

echo "Reading data from SQLite...\n\n";

foreach ($tables as $table => $columns) {
    echo "📦 Migrating: $table... ";
    
    try {
        // Check if table exists in PostgreSQL
        if (!Schema::connection('pgsql')->hasTable($table)) {
            echo "⚠️  Table '$table' doesn't exist in PostgreSQL. Run migrations first!\n";
            continue;
        }
        
        // Check if table has data in SQLite
        $count = $sqlite->table($table)->count();
        if ($count === 0) {
            echo "✅ (0 records, nothing to migrate)\n";
            continue;
        }
        
        // Check if PostgreSQL already has data
        $pgsqlCount = DB::table($table)->count();
        if ($pgsqlCount > 0) {
            echo "⚠️  Table already has $pgsqlCount records in PostgreSQL. Skipping.\n";
            continue;
        }
        
        // Fetch all records from SQLite
        $records = $sqlite->table($table)->get();
        
        // Convert to arrays and insert into PostgreSQL
        $chunks = array_chunk($records->map(fn($r) => (array) $r)->toArray(), 100);
        foreach ($chunks as $chunk) {
            DB::table($table)->insert($chunk);
        }
        
        // Reset auto-increment sequence
        $maxId = DB::table($table)->max('id');
        if ($maxId) {
            DB::statement("SELECT setval('{$table}_id_seq', {$maxId})");
        }
        
        echo "✅ ($count records migrated)\n";
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n========================================\n";
echo "  Migration complete!\n";
echo "========================================\n";
echo "\nNext steps:\n";
echo "1. Verify your data in the new PostgreSQL database\n";
echo "2. Run: php artisan tinker --execute='echo \"All good!\";'\n";
echo "3. Update your frontend .env to point to the new backend URL\n";
echo "4. Deploy via Render\n\n";
