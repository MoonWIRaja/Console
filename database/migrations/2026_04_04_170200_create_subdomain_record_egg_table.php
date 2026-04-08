<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subdomain_record_egg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subdomain_record_id')->constrained('subdomain_records')->cascadeOnDelete();
            $table->unsignedInteger('egg_id');
            $table->timestamps();

            $table->unique(['subdomain_record_id', 'egg_id'], 'subdomain_record_egg_unique');
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdomain_record_egg');
    }
};
