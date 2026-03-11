<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_refunds')) {
            return;
        }

        Schema::create('billing_refunds', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payment_id');
            $table->string('refund_number')->unique();
            $table->string('provider_refund_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('requested');
            $table->unsignedInteger('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('billing_payments')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refunds');
    }
};
