<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_invoices', 'reminder_state')) {
                $table->json('reminder_state')->nullable()->after('billing_profile_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('billing_invoices', 'reminder_state')) {
                $table->dropColumn('reminder_state');
            }
        });
    }
};
