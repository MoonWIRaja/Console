<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_payment_attempts')) {
            return;
        }

        Schema::create('billing_payment_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id');
            $table->unsignedInteger('payment_id')->nullable();
            $table->string('provider', 32);
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status', 32)->default('initiated');
            $table->string('checkout_reference')->unique();
            $table->timestamp('redirected_at')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('raw_request_payload')->nullable();
            $table->json('raw_callback_payload')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('billing_payments')->nullOnDelete();
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payment_attempts');
    }
};
