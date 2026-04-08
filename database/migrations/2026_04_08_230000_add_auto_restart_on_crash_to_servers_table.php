<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('servers') || Schema::hasColumn('servers', 'auto_restart_on_crash')) {
            return;
        }

        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('auto_restart_on_crash')->default(false)->after('split_root_server_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servers') || !Schema::hasColumn('servers', 'auto_restart_on_crash')) {
            return;
        }

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('auto_restart_on_crash');
        });
    }
};
