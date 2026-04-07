<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('split_limit')->default(0)->after('backup_limit');
            $table->unsignedBigInteger('split_parent_server_id')->nullable()->after('split_limit');
            $table->unsignedBigInteger('split_root_server_id')->nullable()->after('split_parent_server_id');

            $table->index('split_parent_server_id');
            $table->index('split_root_server_id');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['split_parent_server_id']);
            $table->dropIndex(['split_root_server_id']);
            $table->dropColumn(['split_limit', 'split_parent_server_id', 'split_root_server_id']);
        });
    }
};
