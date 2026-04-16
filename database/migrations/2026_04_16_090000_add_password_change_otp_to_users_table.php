<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_change_otp')->nullable()->after('active_device_token');
            $table->timestamp('password_change_otp_expires_at')->nullable()->after('password_change_otp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_change_otp',
                'password_change_otp_expires_at',
            ]);
        });
    }
};
