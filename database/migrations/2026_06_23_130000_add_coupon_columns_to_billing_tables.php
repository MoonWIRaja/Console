<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['billing_invoices', 'billing_orders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'coupon_code')) {
                    $table->string('coupon_code', 64)->nullable();
                }
                if (!Schema::hasColumn($table->getTable(), 'discount_total')) {
                    $table->decimal('discount_total', 12, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['billing_invoices', 'billing_orders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'discount_total')) {
                    $table->dropColumn('discount_total');
                }
                if (Schema::hasColumn($table->getTable(), 'coupon_code')) {
                    $table->dropColumn('coupon_code');
                }
            });
        }
    }
};
