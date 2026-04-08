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
        if (Schema::hasTable('billing_game_profiles')) {
            return;
        }

        Schema::create('billing_game_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('billing_node_config_id');
            $table->unsignedInteger('egg_id');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('docker_image')->nullable();
            $table->text('startup')->nullable();
            $table->longText('environment')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('billing_node_config_id')->references('id')->on('billing_node_configs')->onDelete('cascade');
            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_game_profiles');
    }
};
