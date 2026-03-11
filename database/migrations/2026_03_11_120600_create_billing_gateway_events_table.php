<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_gateway_events')) {
            return;
        }

        Schema::create('billing_gateway_events', function (Blueprint $table) {
            $table->increments('id');
            $table->string('provider', 32);
            $table->string('event_type', 64);
            $table->string('provider_event_id')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('dedupe_key')->unique();
            $table->string('status', 32)->default('received');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_transaction_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_gateway_events');
    }
};
