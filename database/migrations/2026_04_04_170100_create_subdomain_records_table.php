<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subdomain_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('subdomain_domains')->cascadeOnDelete();
            $table->string('name');
            $table->string('record_type', 16);
            $table->unsignedInteger('ttl')->nullable();
            $table->boolean('proxied')->default(false);
            $table->string('service')->nullable();
            $table->string('protocol')->nullable();
            $table->unsignedInteger('priority')->nullable();
            $table->unsignedInteger('weight')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdomain_records');
    }
};
