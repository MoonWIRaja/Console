<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_discord_agents', function (Blueprint $table) {
            $table->json('runtime_state')->nullable()->after('last_fingerprint');
            $table->timestamp('last_sync_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('server_discord_agents', function (Blueprint $table) {
            $table->dropColumn(['runtime_state', 'last_sync_at']);
        });
    }
};
