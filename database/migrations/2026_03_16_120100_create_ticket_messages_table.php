<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ticket_messages')) {
            return;
        }

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_id');
            $table->string('author_type', 16);
            $table->unsignedInteger('author_user_id')->nullable();
            $table->string('author_display_name')->nullable();
            $table->string('author_avatar_url', 2048)->nullable();
            $table->string('origin', 32)->default('console');
            $table->longText('body')->nullable();
            $table->string('discord_message_id', 32)->nullable();
            $table->string('discord_sync_status', 32)->default('pending');
            $table->timestamp('discord_synced_at')->nullable();
            $table->text('discord_sync_error')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('author_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['ticket_id', 'created_at']);
            $table->index(['discord_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
