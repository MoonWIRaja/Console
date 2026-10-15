<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cloud_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->text('access_token')->nullable();
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('root_folder_id')->nullable();
            $table->unsignedBigInteger('quota_limit')->nullable();
            $table->unsignedBigInteger('quota_usage')->default(0);
            $table->timestamp('quota_checked_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('email');
        });

        Schema::create('cloud_user_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreignId('account_id')->constrained('cloud_accounts')->cascadeOnDelete();
            $table->string('drive_folder_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'account_id']);
        });

        Schema::create('cloud_files', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('user_id');
            $table->foreignId('account_id')->constrained('cloud_accounts')->cascadeOnDelete();
            $table->string('drive_file_id');
            $table->string('name');
            $table->string('mime', 191)->default('application/octet-stream');
            $table->unsignedBigInteger('size');
            $table->string('source', 16)->default('upload');
            $table->char('share_token', 40)->nullable()->unique();
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('cloud_file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('cloud_files')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['file_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_file_shares');
        Schema::dropIfExists('cloud_files');
        Schema::dropIfExists('cloud_user_folders');
        Schema::dropIfExists('cloud_accounts');
    }
};
