<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ticket_attachments')) {
            return;
        }

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_message_id');
            $table->string('disk', 64)->default('local');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('discord_attachment_id', 32)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();

            $table->foreign('ticket_message_id')->references('id')->on('ticket_messages')->onDelete('cascade');
            $table->index(['ticket_message_id']);
            $table->index(['discord_attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
