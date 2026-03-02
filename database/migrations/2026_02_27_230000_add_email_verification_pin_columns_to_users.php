<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_email_verified')->default(true)->after('use_totp');
            $table->string('email_verification_pin')->nullable()->after('is_email_verified');
            $table->timestampTz('email_verification_expires_at')->nullable()->after('email_verification_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_email_verified',
                'email_verification_pin',
                'email_verification_expires_at',
            ]);
        });
    }
};

