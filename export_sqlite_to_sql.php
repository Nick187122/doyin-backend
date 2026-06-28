<?php

/**
 * SQLite → SQL Export Script (for PostgreSQL)
 * 
 * Reads data from SQLite and generates SQL INSERT statements
 * that can be run in Supabase's SQL editor.
 *
 * Usage: php export_sqlite_to_sql.php
 * Output: Creates doyin_data.sql in the same directory
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "  SQLite → PostgreSQL SQL Export\n";
echo "========================================\n\n";

// Connect to SQLite
$sqlite = DB::connection('sqlite');

$sqliteDbPath = database_path('database.sqlite');
if (!file_exists($sqliteDbPath)) {
    echo "❌ SQLite database not found at: $sqliteDbPath\n";
    exit(1);
}

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

// Boolean columns in each table that need conversion from 0/1 to true/false
$booleanColumns = [
    'users' => ['must_change_password'],
    'categories' => ['is_pump', 'has_ideal_power'],
    'products' => ['in_stock'],
    'hero_images' => ['is_active'],
    'user_interactions' => ['is_read'],
    'salespersons' => ['is_active'],
];

$output = "-- =======================================\n";
$output .= "-- DOYIN KENYA Data Migration\n";
$output .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Source: SQLite → Target: PostgreSQL\n";
$output .= "-- =======================================\n\n";

$output .= "-- First, disable triggers and constraints for faster import\n";
$output .= "SET session_replication_role = 'replica';\n\n";

$totalRecords = 0;

foreach ($tables as $table => $columns) {
    echo "📦 Exporting: $table... ";
    
    try {
        $count = $sqlite->table($table)->count();
        if ($count === 0) {
            echo "(0 records, skipped)\n";
            continue;
        }
        
        $records = $sqlite->table($table)->get();
        $cols = implode(', ', array_map(fn($c) => '"' . $c . '"', $columns));
        
        $tableBooleans = $booleanColumns[$table] ?? [];
        
        $output .= "-- $table ($count records)\n";
        
        foreach ($records as $record) {
            $values = [];
            foreach ($columns as $col) {
                $val = $record->$col;
                
                if ($val === null) {
                    $values[] = 'NULL';
                } elseif (in_array($col, $tableBooleans)) {
                    // Convert SQLite integer boolean to PostgreSQL boolean literal
                    $values[] = ($val == 1 || $val === true || $val === '1') ? 'true' : 'false';
                } elseif (is_numeric($val) && !in_array($col, ['name', 'email', 'description', 'image_path', 'title', 'type', 'content', 'key', 'value', 'phone_number', 'password', 'remember_token', 'password_change_otp', 'active_device_token'])) {
                    $values[] = $val;
                } else {
                    // Escape single quotes for PostgreSQL
                    $escaped = str_replace("'", "''", $val);
                    $values[] = "'" . $escaped . "'";
                }
            }
            
            $output .= "INSERT INTO \"$table\" ($cols) VALUES (" . implode(', ', $values) . ");\n";
            $totalRecords++;
        }
        
        // Add sequence update for this table
        if ($count > 0) {
            $maxId = $sqlite->table($table)->max('id');
            if ($maxId) {
                $output .= "SELECT setval('{$table}_id_seq', {$maxId});\n";
            }
        }
        
        $output .= "\n";
        
        echo "✅ ($count records)\n";
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

$output .= "-- Re-enable constraints\n";
$output .= "SET session_replication_role = 'origin';\n\n";

$output .= "-- Migration complete! $totalRecords total records imported.\n";

// Write to file
$outputPath = __DIR__ . '/doyin_data.sql';
file_put_contents($outputPath, $output);

echo "\n========================================\n";
echo "  Export complete!\n";
echo "========================================\n";
echo "\n📄 SQL file saved to: $outputPath\n";
echo "\nNext steps:\n";
echo "1. Open your Supabase Dashboard → SQL Editor\n";
echo "2. Copy the contents of doyin_data.sql\n";
echo "3. Paste and run in Supabase SQL Editor\n";
echo "4. Verify your data is there!\n\n";
