<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->longText('credentials')->nullable()->after('api_key');
            $table->string('account')->nullable()->after('name');
            $table->json('settings')->nullable()->after('credentials');
            $table->timestamp('token_expires_at')->nullable()->after('settings');
            $table->text('last_error')->nullable()->after('models_synced_at');
            $table->string('base_url', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['credentials', 'account', 'settings', 'token_expires_at', 'last_error']);
        });
    }
};
