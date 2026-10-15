<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            // Auto-compaction: older turns are summarised so long chats keep fitting the model's context.
            $table->longText('summary')->nullable()->after('title');
            $table->unsignedBigInteger('summary_until_id')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['summary', 'summary_until_id']);
        });
    }
};
