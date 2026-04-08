<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_subscriptions')) {
            return;
        }

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('server_id')->nullable()->unique();
            $table->unsignedInteger('billing_node_config_id');
            $table->unsignedInteger('billing_game_profile_id')->nullable();
            $table->unsignedInteger('billing_order_id')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('server_name');
            $table->string('node_name');
            $table->string('game_name');
            $table->unsignedInteger('cpu_cores');
            $table->unsignedInteger('memory_gb');
            $table->unsignedInteger('disk_gb');
            $table->decimal('price_per_vcore', 10, 2);
            $table->decimal('price_per_gb_ram', 10, 2);
            $table->decimal('price_per_10gb_disk', 10, 2);
            $table->decimal('recurring_total', 10, 2);
            $table->unsignedTinyInteger('renewal_period_months')->default(1);
            $table->timestamp('renews_at');
            $table->timestamp('renewal_reminder_sent_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('upgraded_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('deletion_scheduled_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->foreign('billing_node_config_id')->references('id')->on('billing_node_configs')->onDelete('cascade');
            $table->foreign('billing_game_profile_id')->references('id')->on('billing_game_profiles')->nullOnDelete();
            $table->foreign('billing_order_id')->references('id')->on('billing_orders')->nullOnDelete();

            $table->index(['status', 'renews_at']);
            $table->index(['status', 'deletion_scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
