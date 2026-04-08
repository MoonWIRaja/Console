<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_invoices')) {
            return;
        }

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('invoice_number')->unique();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('billing_profile_id')->nullable();
            $table->unsignedInteger('billing_order_id')->nullable();
            $table->unsignedInteger('subscription_id')->nullable();
            $table->string('type', 32);
            $table->string('currency', 8)->default('MYR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('status', 32)->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('billing_profile_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('billing_profile_id')->references('id')->on('billing_profiles')->nullOnDelete();
            $table->foreign('billing_order_id')->references('id')->on('billing_orders')->nullOnDelete();
            $table->foreign('subscription_id')->references('id')->on('billing_subscriptions')->nullOnDelete();

            $table->index(['user_id', 'status', 'due_at']);
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
