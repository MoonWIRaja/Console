<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('preset', 64)->default('custom');
            $table->string('base_url', 512);
            $table->text('api_key')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('models_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_id');
            $table->string('label')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_tools')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->unique(['provider_id', 'model_id']);
        });

        Schema::create('ai_skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('description', 512)->nullable();
            $table->longText('content');
            $table->string('scope', 16)->default('both');
            $table->boolean('enabled')->default(true);
            $table->string('source_url', 1024)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->string('channel', 16)->default('panel');
            $table->string('external_id', 191)->nullable();
            $table->string('title')->default('New chat');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->index(['user_id', 'channel', 'updated_at']);
            $table->index(['channel', 'external_id']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_pending_actions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->unsignedInteger('server_id');
            $table->string('tool_call_id', 191);
            $table->string('tool', 64);
            $table->json('arguments');
            $table->string('status', 16)->default('pending');
            $table->longText('result')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::create('ai_user_memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('fact', 500);
            $table->string('source', 16)->default('panel');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_user_memories');
        Schema::dropIfExists('ai_pending_actions');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_skills');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
