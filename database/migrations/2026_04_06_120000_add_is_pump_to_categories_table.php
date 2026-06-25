<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('categories', 'is_pump')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('is_pump')->default(true)->after('name');
            });
        }

        // Use DB::raw('true') for PostgreSQL compatibility (boolean vs integer)
        DB::table('categories')->update(['is_pump' => DB::raw('true')]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_pump');
        });
    }
};
