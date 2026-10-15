<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cloud_accounts', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_accounts', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
