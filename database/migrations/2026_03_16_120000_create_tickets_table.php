<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            return;
        }

        Schema::create('tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ticket_number')->unique();
            $table->unsignedInteger('user_id');
            $table->string('category', 32);
            $table->string('source', 32)->default('console');
            $table->string('status', 32)->default('waiting_for_staff');
            $table->string('subject');
            $table->unsignedInteger('assigned_admin_id')->nullable();
            $table->unsignedInteger('billing_order_id')->nullable();
            $table->unsignedInteger('billing_invoice_id')->nullable();
            $table->unsignedInteger('billing_payment_id')->nullable();
            $table->unsignedInteger('billing_subscription_id')->nullable();
            $table->string('requester_discord_user_id', 32)->nullable();
            $table->string('requester_discord_name')->nullable();
            $table->string('requester_discord_avatar', 2048)->nullable();
            $table->string('discord_thread_id', 32)->nullable();
            $table->string('discord_parent_channel_id', 32)->nullable();
            $table->string('discord_sync_status', 32)->default('pending');
            $table->timestamp('discord_last_synced_at')->nullable();
            $table->text('discord_last_error')->nullable();
            $table->timestamp('last_user_message_at')->nullable();
            $table->timestamp('last_admin_message_at')->nullable();
            $table->timestamp('user_last_read_at')->nullable();
            $table->timestamp('staff_last_read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_admin_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('billing_order_id')->references('id')->on('billing_orders')->nullOnDelete();
            $table->foreign('billing_invoice_id')->references('id')->on('billing_invoices')->nullOnDelete();
            $table->foreign('billing_payment_id')->references('id')->on('billing_payments')->nullOnDelete();
            $table->foreign('billing_subscription_id')->references('id')->on('billing_subscriptions')->nullOnDelete();

            $table->index(['user_id', 'status', 'updated_at']);
            $table->index(['category', 'status']);
            $table->index(['billing_invoice_id', 'category', 'status']);
            $table->index(['billing_payment_id', 'category', 'status']);
            $table->index(['assigned_admin_id', 'status']);
            $table->unique(['billing_invoice_id', 'category', 'closed_at'], 'tickets_invoice_category_closed_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
