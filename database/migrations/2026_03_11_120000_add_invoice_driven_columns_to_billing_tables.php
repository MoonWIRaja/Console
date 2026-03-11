<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_orders', 'order_type')) {
                $table->string('order_type', 32)->default('new_server')->after('approved_by');
            }

            if (!Schema::hasColumn('billing_orders', 'billing_invoice_id')) {
                $table->unsignedInteger('billing_invoice_id')->nullable()->after('billing_game_profile_id');
            }

            if (!Schema::hasColumn('billing_orders', 'billing_profile_snapshot')) {
                $table->json('billing_profile_snapshot')->nullable()->after('environment');
            }

            if (!Schema::hasColumn('billing_orders', 'payment_verified_at')) {
                $table->timestamp('payment_verified_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('billing_orders', 'provision_attempted_at')) {
                $table->timestamp('provision_attempted_at')->nullable()->after('payment_verified_at');
            }

            if (!Schema::hasColumn('billing_orders', 'provision_failure_code')) {
                $table->string('provision_failure_code', 64)->nullable()->after('provision_attempted_at');
            }

            if (!Schema::hasColumn('billing_orders', 'provision_failure_message')) {
                $table->text('provision_failure_message')->nullable()->after('provision_failure_code');
            }
        });

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('status');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'gateway_provider')) {
                $table->string('gateway_provider', 32)->nullable()->after('auto_renew');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'gateway_customer_reference')) {
                $table->string('gateway_customer_reference')->nullable()->after('gateway_provider');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'gateway_token_reference')) {
                $table->string('gateway_token_reference')->nullable()->after('gateway_customer_reference');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'next_invoice_at')) {
                $table->timestamp('next_invoice_at')->nullable()->after('renews_at');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'grace_suspend_at')) {
                $table->timestamp('grace_suspend_at')->nullable()->after('next_invoice_at');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'grace_delete_at')) {
                $table->timestamp('grace_delete_at')->nullable()->after('grace_suspend_at');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'last_paid_invoice_id')) {
                $table->unsignedInteger('last_paid_invoice_id')->nullable()->after('billing_order_id');
            }

            if (!Schema::hasColumn('billing_subscriptions', 'failed_payment_count')) {
                $table->unsignedInteger('failed_payment_count')->default(0)->after('last_paid_invoice_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $columns = [
                'auto_renew',
                'gateway_provider',
                'gateway_customer_reference',
                'gateway_token_reference',
                'next_invoice_at',
                'grace_suspend_at',
                'grace_delete_at',
                'last_paid_invoice_id',
                'failed_payment_count',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('billing_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('billing_orders', function (Blueprint $table) {
            $columns = [
                'order_type',
                'billing_invoice_id',
                'billing_profile_snapshot',
                'payment_verified_at',
                'provision_attempted_at',
                'provision_failure_code',
                'provision_failure_message',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('billing_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
