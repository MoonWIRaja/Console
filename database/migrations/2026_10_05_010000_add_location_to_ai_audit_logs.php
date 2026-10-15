<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table) {
            // Where the chat happened: panel general chat, panel server chat, Discord DM or a Discord channel.
            $table->string('location', 160)->nullable()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
