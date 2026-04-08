<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_tax_rules')) {
            return;
        }

        Schema::create('billing_tax_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_business')->nullable();
            $table->boolean('tax_id_required')->nullable();
            $table->string('rate_type', 16)->default('percentage');
            $table->decimal('rate_value', 12, 4)->default(0);
            $table->boolean('apply_to_new_orders')->default(true);
            $table->boolean('apply_to_renewals')->default(true);
            $table->boolean('apply_to_upgrades')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_tax_rules');
    }
};
