<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_coupons', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('billing_coupons', 'assigned_user_id')) {
                $table->unsignedInteger('assigned_user_id')->nullable()->after('expires_at');
                $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('assigned_user_id');
            }
            if (!Schema::hasColumn('billing_coupons', 'batch_id')) {
                // Groups the many per-user codes created from a single "assign to users"
                // action, so the admin table can show them as one entry instead of a
                // wall of otherwise-identical rows.
                $table->string('batch_id', 36)->nullable()->after('assigned_user_id');
                $table->index('batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('billing_coupons', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
            if (Schema::hasColumn('billing_coupons', 'assigned_user_id')) {
                $table->dropForeign(['assigned_user_id']);
                $table->dropColumn('assigned_user_id');
            }
            if (Schema::hasColumn('billing_coupons', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
