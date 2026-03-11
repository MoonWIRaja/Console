<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_payments')) {
            return;
        }

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id');
            $table->string('provider', 32);
            $table->string('payment_number')->unique();
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_order_id')->nullable();
            $table->string('provider_payment_method')->nullable();
            $table->string('provider_status', 64)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('MYR');
            $table->string('status', 32)->default('initiated');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_gateway_response')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('cascade');
            $table->index(['provider', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
