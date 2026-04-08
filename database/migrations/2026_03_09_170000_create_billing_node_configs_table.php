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
        if (Schema::hasTable('billing_node_configs')) {
            return;
        }

        Schema::create('billing_node_configs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('node_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('cpu_stock')->default(0);
            $table->unsignedInteger('memory_stock_gb')->default(0);
            $table->unsignedInteger('disk_stock_gb')->default(0);
            $table->boolean('show_remaining_capacity')->default(true);
            $table->decimal('price_per_vcore', 10, 2)->default(0);
            $table->decimal('price_per_gb_ram', 10, 2)->default(0);
            $table->decimal('price_per_10gb_disk', 10, 2)->default(0);
            $table->unsignedInteger('default_allocation_limit')->default(0);
            $table->unsignedInteger('default_database_limit')->default(0);
            $table->unsignedInteger('default_backup_limit')->default(0);
            $table->integer('default_swap')->default(0);
            $table->unsignedSmallInteger('default_io')->default(500);
            $table->boolean('default_oom_disabled')->default(true);
            $table->boolean('start_on_completion')->default(true);
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_node_configs');
    }
};
