<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_discord_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->longText('bot_token_encrypted')->nullable();
            $table->string('guild_id', 32)->nullable();
            $table->string('chat_channel_id', 32)->nullable();
            $table->string('console_channel_id', 32)->nullable();
            $table->string('admin_channel_id', 32)->nullable();
            $table->string('link_channel_id', 32)->nullable();
            $table->boolean('chat_bridge_enabled')->default(true);
            $table->boolean('console_bridge_enabled')->default(true);
            $table->boolean('linking_enabled')->default(true);
            $table->boolean('whitelist_requires_link')->default(false);
            $table->json('features')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('server_discord_agents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('install_status', 32)->default('not_installed');
            $table->string('connection_status', 32)->default('offline');
            $table->longText('agent_secret_encrypted')->nullable();
            $table->string('agent_version', 64)->nullable();
            $table->string('adapter', 64)->nullable();
            $table->string('detected_game_type', 64)->nullable();
            $table->unsignedTinyInteger('detection_confidence')->default(0);
            $table->json('detection_sources')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('last_fingerprint')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('restart_requested_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('server_player_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('player_id', 191);
            $table->string('uuid', 191)->nullable();
            $table->string('name', 191);
            $table->string('status', 32)->default('offline');
            $table->integer('ping')->default(0);
            $table->string('role', 64)->default('player');
            $table->boolean('is_operator')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('banned')->default(false);
            $table->string('discord_user_id', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'player_id']);
            $table->index(['server_id', 'status']);
            $table->index(['server_id', 'discord_user_id']);
        });

        Schema::create('server_player_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('player_id', 191);
            $table->string('player_uuid', 191)->nullable();
            $table->string('player_name', 191);
            $table->string('discord_user_id', 32);
            $table->string('discord_username', 191)->nullable();
            $table->string('link_code', 32)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'player_id']);
            $table->unique(['server_id', 'discord_user_id']);
            $table->index(['server_id', 'status']);
        });

        Schema::create('server_discord_agent_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('player_id', 191)->nullable();
            $table->string('discord_user_id', 32)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'event_type']);
            $table->index(['server_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_discord_agent_events');
        Schema::dropIfExists('server_player_links');
        Schema::dropIfExists('server_player_snapshots');
        Schema::dropIfExists('server_discord_agents');
        Schema::dropIfExists('server_discord_integrations');
    }
};
