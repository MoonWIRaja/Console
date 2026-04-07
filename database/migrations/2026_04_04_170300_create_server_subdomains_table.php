<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('server_subdomains', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreignId('domain_id')->constrained('subdomain_domains')->cascadeOnDelete();
            $table->foreignId('subdomain_record_id')->constrained('subdomain_records')->cascadeOnDelete();
            $table->string('hostname_label');
            $table->string('full_domain');
            $table->string('record_type', 16);
            $table->string('resolved_target')->nullable();
            $table->json('provider_record_ids')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'hostname_label'], 'server_subdomains_domain_label_unique');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_subdomains');
    }
};
