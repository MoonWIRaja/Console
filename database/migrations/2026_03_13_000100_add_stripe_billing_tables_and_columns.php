<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_customers')) {
            Schema::create('billing_customers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('provider', 32)->default('stripe');
                $table->string('provider_customer_id')->unique();
                $table->string('email_snapshot')->nullable();
                $table->string('name_snapshot')->nullable();
                $table->string('phone_snapshot')->nullable();
                $table->json('address_snapshot')->nullable();
                $table->json('tax_ids_snapshot')->nullable();
                $table->string('default_payment_method_type', 32)->nullable();
                $table->string('default_payment_method_brand', 32)->nullable();
                $table->string('default_payment_method_last4', 8)->nullable();
                $table->timestamp('portal_ready_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('billing_subscription_revisions')) {
            Schema::create('billing_subscription_revisions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('subscription_id');
                $table->unsignedInteger('source_invoice_id')->nullable();
                $table->unsignedInteger('source_order_id')->nullable();
                $table->unsignedInteger('previous_revision_id')->nullable();
                $table->string('revision_type', 32);
                $table->unsignedInteger('cpu_cores');
                $table->unsignedInteger('memory_gb');
                $table->unsignedInteger('disk_gb');
                $table->decimal('recurring_total', 12, 2);
                $table->string('stripe_price_id')->nullable();
                $table->json('stripe_price_snapshot')->nullable();
                $table->timestamp('applied_at');
                $table->timestamps();

                $table->foreign('subscription_id')->references('id')->on('billing_subscriptions')->cascadeOnDelete();
                $table->foreign('source_invoice_id')->references('id')->on('billing_invoices')->nullOnDelete();
                $table->foreign('source_order_id')->references('id')->on('billing_orders')->nullOnDelete();
                $table->foreign('previous_revision_id')->references('id')->on('billing_subscription_revisions')->nullOnDelete();
            });
        }

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            foreach ([
                'provider_subscription_id' => fn () => $table->string('provider_subscription_id')->nullable()->after('gateway_token_reference'),
                'provider_subscription_item_id' => fn () => $table->string('provider_subscription_item_id')->nullable()->after('provider_subscription_id'),
                'provider_price_id' => fn () => $table->string('provider_price_id')->nullable()->after('provider_subscription_item_id'),
                'provider_status' => fn () => $table->string('provider_status', 64)->nullable()->after('provider_price_id'),
                'provider_current_period_start' => fn () => $table->timestamp('provider_current_period_start')->nullable()->after('provider_status'),
                'provider_current_period_end' => fn () => $table->timestamp('provider_current_period_end')->nullable()->after('provider_current_period_start'),
                'provider_cancel_at' => fn () => $table->timestamp('provider_cancel_at')->nullable()->after('provider_current_period_end'),
                'migration_source' => fn () => $table->string('migration_source', 32)->nullable()->after('provider_cancel_at'),
                'migration_state' => fn () => $table->string('migration_state', 64)->nullable()->after('migration_source'),
            ] as $column => $callback) {
                if (!Schema::hasColumn('billing_subscriptions', $column)) {
                    $callback();
                }
            }
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            foreach ([
                'provider' => fn () => $table->string('provider', 32)->nullable()->after('subscription_id'),
                'provider_invoice_id' => fn () => $table->string('provider_invoice_id')->nullable()->after('provider'),
                'provider_checkout_session_id' => fn () => $table->string('provider_checkout_session_id')->nullable()->after('provider_invoice_id'),
                'provider_payment_intent_id' => fn () => $table->string('provider_payment_intent_id')->nullable()->after('provider_checkout_session_id'),
                'hosted_invoice_url' => fn () => $table->text('hosted_invoice_url')->nullable()->after('provider_payment_intent_id'),
                'invoice_pdf_url' => fn () => $table->text('invoice_pdf_url')->nullable()->after('hosted_invoice_url'),
                'provider_status' => fn () => $table->string('provider_status', 64)->nullable()->after('invoice_pdf_url'),
            ] as $column => $callback) {
                if (!Schema::hasColumn('billing_invoices', $column)) {
                    $callback();
                }
            }
        });

        Schema::table('billing_payments', function (Blueprint $table) {
            foreach ([
                'provider_payment_intent_id' => fn () => $table->string('provider_payment_intent_id')->nullable()->after('provider_transaction_id'),
                'provider_charge_id' => fn () => $table->string('provider_charge_id')->nullable()->after('provider_payment_intent_id'),
                'payment_method_type' => fn () => $table->string('payment_method_type', 32)->nullable()->after('provider_charge_id'),
                'payment_method_brand' => fn () => $table->string('payment_method_brand', 32)->nullable()->after('payment_method_type'),
                'payment_method_last4' => fn () => $table->string('payment_method_last4', 8)->nullable()->after('payment_method_brand'),
            ] as $column => $callback) {
                if (!Schema::hasColumn('billing_payments', $column)) {
                    $callback();
                }
            }
        });

        Schema::table('billing_payment_attempts', function (Blueprint $table) {
            foreach ([
                'provider_session_id' => fn () => $table->string('provider_session_id')->nullable()->after('checkout_reference'),
                'attempt_mode' => fn () => $table->string('attempt_mode', 32)->nullable()->after('provider_session_id'),
            ] as $column => $callback) {
                if (!Schema::hasColumn('billing_payment_attempts', $column)) {
                    $callback();
                }
            }
        });

        Schema::table('billing_refunds', function (Blueprint $table) {
            foreach ([
                'provider_charge_id' => fn () => $table->string('provider_charge_id')->nullable()->after('provider_refund_id'),
                'provider_payment_intent_id' => fn () => $table->string('provider_payment_intent_id')->nullable()->after('provider_charge_id'),
                'provider_refund_status' => fn () => $table->string('provider_refund_status', 64)->nullable()->after('provider_payment_intent_id'),
                'refund_scope' => fn () => $table->string('refund_scope', 32)->nullable()->after('provider_refund_status'),
                'source_revision_id' => fn () => $table->unsignedInteger('source_revision_id')->nullable()->after('refund_scope'),
            ] as $column => $callback) {
                if (!Schema::hasColumn('billing_refunds', $column)) {
                    $callback();
                }
            }
            if (Schema::hasColumn('billing_refunds', 'source_revision_id')
                && !$this->foreignKeyExists('billing_refunds', 'billing_refunds_source_revision_id_foreign')) {
                $table->foreign('source_revision_id')->references('id')->on('billing_subscription_revisions')->nullOnDelete();
            }
        });

        Schema::table('billing_gateway_events', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_gateway_events', 'provider_event_id')) {
                $table->string('provider_event_id')->nullable()->after('event_type');
            }
        });

        $this->deduplicateGatewayEventProviderIds();
        if (!$this->indexExists('billing_gateway_events', 'billing_gateway_events_provider_provider_event_id_unique')) {
            Schema::table('billing_gateway_events', function (Blueprint $table) {
                $table->unique(['provider', 'provider_event_id'], 'billing_gateway_events_provider_provider_event_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('billing_gateway_events', function (Blueprint $table) {
            if (Schema::hasColumn('billing_gateway_events', 'provider_event_id')
                && $this->indexExists('billing_gateway_events', 'billing_gateway_events_provider_provider_event_id_unique')) {
                $table->dropUnique('billing_gateway_events_provider_provider_event_id_unique');
            }
            if (Schema::hasColumn('billing_gateway_events', 'provider_event_id')) {
                $table->dropColumn('provider_event_id');
            }
        });

        Schema::table('billing_refunds', function (Blueprint $table) {
            foreach (['source_revision_id', 'refund_scope', 'provider_refund_status', 'provider_payment_intent_id', 'provider_charge_id'] as $column) {
                if (Schema::hasColumn('billing_refunds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('billing_payment_attempts', function (Blueprint $table) {
            foreach (['provider_session_id', 'attempt_mode'] as $column) {
                if (Schema::hasColumn('billing_payment_attempts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('billing_payments', function (Blueprint $table) {
            foreach (['provider_payment_intent_id', 'provider_charge_id', 'payment_method_type', 'payment_method_brand', 'payment_method_last4'] as $column) {
                if (Schema::hasColumn('billing_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            foreach (['provider', 'provider_invoice_id', 'provider_checkout_session_id', 'provider_payment_intent_id', 'hosted_invoice_url', 'invoice_pdf_url', 'provider_status'] as $column) {
                if (Schema::hasColumn('billing_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            foreach ([
                'provider_subscription_id',
                'provider_subscription_item_id',
                'provider_price_id',
                'provider_status',
                'provider_current_period_start',
                'provider_current_period_end',
                'provider_cancel_at',
                'migration_source',
                'migration_state',
            ] as $column) {
                if (Schema::hasColumn('billing_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('billing_subscription_revisions');
        Schema::dropIfExists('billing_customers');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    private function deduplicateGatewayEventProviderIds(): void
    {
        if (!Schema::hasTable('billing_gateway_events') || !Schema::hasColumn('billing_gateway_events', 'provider_event_id')) {
            return;
        }

        $duplicates = DB::table('billing_gateway_events')
            ->select('provider', 'provider_event_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('provider_event_id')
            ->groupBy('provider', 'provider_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('billing_gateway_events')
                ->where('provider', $duplicate->provider)
                ->where('provider_event_id', $duplicate->provider_event_id)
                ->orderBy('id')
                ->pluck('id');

            $keepFirst = true;
            foreach ($ids as $id) {
                if ($keepFirst) {
                    $keepFirst = false;
                    continue;
                }

                DB::table('billing_gateway_events')
                    ->where('id', $id)
                    ->update([
                        'provider_event_id' => sprintf('%s-dup-%d', $duplicate->provider_event_id, $id),
                    ]);
            }
        }
    }
};
