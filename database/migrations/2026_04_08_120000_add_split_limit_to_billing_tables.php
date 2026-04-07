<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_node_configs') && !Schema::hasColumn('billing_node_configs', 'default_split_limit')) {
            Schema::table('billing_node_configs', function (Blueprint $table) {
                $table->unsignedInteger('default_split_limit')->default(0);
            });
        }

        if (Schema::hasTable('billing_orders') && !Schema::hasColumn('billing_orders', 'split_limit')) {
            Schema::table('billing_orders', function (Blueprint $table) {
                $table->unsignedInteger('split_limit')->default(0);
            });
        }

        if (Schema::hasTable('billing_subscriptions') && !Schema::hasColumn('billing_subscriptions', 'split_limit')) {
            Schema::table('billing_subscriptions', function (Blueprint $table) {
                $table->unsignedInteger('split_limit')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_subscriptions') && Schema::hasColumn('billing_subscriptions', 'split_limit')) {
            Schema::table('billing_subscriptions', function (Blueprint $table) {
                $table->dropColumn('split_limit');
            });
        }

        if (Schema::hasTable('billing_orders') && Schema::hasColumn('billing_orders', 'split_limit')) {
            Schema::table('billing_orders', function (Blueprint $table) {
                $table->dropColumn('split_limit');
            });
        }

        if (Schema::hasTable('billing_node_configs') && Schema::hasColumn('billing_node_configs', 'default_split_limit')) {
            Schema::table('billing_node_configs', function (Blueprint $table) {
                $table->dropColumn('default_split_limit');
            });
        }
    }
};
