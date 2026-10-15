<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // PDPA: no chat text is stored here, only who / where / what action and its target.
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedInteger('server_id')->nullable()->index();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('channel', 16);
            $table->string('event', 48)->index();
            $table->string('tool', 64)->nullable();
            $table->string('target', 512)->nullable();
            $table->string('status', 24)->nullable();
            $table->string('model', 128)->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
    }
};
