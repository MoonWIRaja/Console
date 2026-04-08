<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('billing_orders')) {
            return;
        }

        Schema::create('billing_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('billing_node_config_id');
            $table->unsignedInteger('billing_game_profile_id')->nullable();
            $table->unsignedInteger('node_id');
            $table->unsignedInteger('egg_id');
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('server_name');
            $table->string('node_name');
            $table->string('game_name');
            $table->unsignedInteger('cpu_cores');
            $table->unsignedInteger('memory_gb');
            $table->unsignedInteger('disk_gb');
            $table->decimal('price_per_vcore', 10, 2);
            $table->decimal('price_per_gb_ram', 10, 2);
            $table->decimal('price_per_10gb_disk', 10, 2);
            $table->decimal('cpu_total', 10, 2);
            $table->decimal('memory_total', 10, 2);
            $table->decimal('disk_total', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('docker_image')->nullable();
            $table->text('startup')->nullable();
            $table->longText('environment')->nullable();
            $table->unsignedInteger('allocation_limit')->default(0);
            $table->unsignedInteger('database_limit')->default(0);
            $table->unsignedInteger('backup_limit')->default(0);
            $table->integer('swap')->default(0);
            $table->unsignedSmallInteger('io')->default(500);
            $table->boolean('oom_disabled')->default(true);
            $table->boolean('start_on_completion')->default(true);
            $table->text('order_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('billing_node_config_id')->references('id')->on('billing_node_configs')->onDelete('cascade');
            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('cascade');
            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_orders');
    }
};
