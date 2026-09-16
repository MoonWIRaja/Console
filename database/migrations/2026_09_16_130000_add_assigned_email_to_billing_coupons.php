<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_coupons', 'assigned_email')) {
                // A personal coupon can be created for an email that has no account
                // yet - assigned_user_id stays null until that person registers with
                // this address, and redemption is checked against the email instead.
                $table->string('assigned_email', 191)->nullable()->after('assigned_user_id');
                $table->index('assigned_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('billing_coupons', 'assigned_email')) {
                $table->dropColumn('assigned_email');
            }
        });
    }
};
